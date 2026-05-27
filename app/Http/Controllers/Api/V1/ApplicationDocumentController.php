<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationDocumentResource;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequest;
use App\Models\Notification;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ApplicationDocumentController extends Controller
{
    public function __construct(private readonly SupabaseStorageService $storage)
    {
    }

    // GET /applications/{application}/documents
    // Applicants see their own; admins see any. Each document includes a short-lived signed URL.
    public function index(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        $documents = $application->documents()->orderBy('uploaded_at', 'asc')->get();

        // Sign each row's storage_path so the client can download directly from Supabase.
        $documents->each(function (ApplicationDocument $document) {
            $document->download_url = $this->safeSign($document->storage_path);
        });

        return response()->json([
            'data' => ApplicationDocumentResource::collection($documents),
        ]);
    }

    // POST /applications/{application}/documents
    // Applicant only. Draft uploads use document_type or form_field_id. Submitted/under-review
    // applications also accept uploads linked to a pending DocumentRequest (admin-driven flow).
    public function store(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if ($application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            // 10 MB cap (NFR12). The mimes list mirrors the allowed file types in CLAUDE.md.
            'file'                => ['required', 'file', 'max:10240', 'mimes:pdf,docx,xlsx,jpg,jpeg,png'],
            // Every document is anchored to exactly one of the three: a round-level required-doc slot,
            // a custom-question document field, or an admin-driven document request.
            'document_type'       => ['required_without_all:form_field_id,document_request_id', 'nullable', 'string', 'max:50'],
            'form_field_id'       => ['required_without_all:document_type,document_request_id', 'nullable', 'uuid'],
            'document_request_id' => ['required_without_all:document_type,form_field_id', 'nullable', 'uuid'],
        ]);

        // Resolve and validate the linked document request when present. The request must belong
        // to this application and must still be pending; otherwise we fall back to the legacy
        // "draft-only" rule for the other two anchors.
        $documentRequest = null;
        if (! empty($validated['document_request_id'])) {
            $documentRequest = DocumentRequest::where('id', $validated['document_request_id'])
                ->where('application_id', $application->id)
                ->first();

            if (! $documentRequest) {
                return response()->json([
                    'error' => [
                        'code'    => 'document_request_not_found',
                        'message' => 'The document request could not be found on this application.',
                    ],
                ], 404);
            }

            if ($documentRequest->status !== 'pending') {
                return response()->json([
                    'error' => [
                        'code'    => 'request_not_pending',
                        'message' => 'This document request is no longer accepting uploads.',
                    ],
                ], 422);
            }
        } elseif ($application->status !== 'draft') {
            return response()->json([
                'error' => [
                    'code'    => 'not_editable',
                    'message' => 'This application has been submitted and can no longer be edited.',
                ],
            ], 422);
        }

        $file = $request->file('file');

        $documentId = (string) Str::uuid();
        $extension  = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $storagePath = "applications/{$application->id}/{$documentId}.{$extension}";

        try {
            $this->storage->upload($storagePath, $file);
        } catch (Throwable $e) {
            return response()->json([
                'error' => [
                    'code'    => 'upload_failed',
                    'message' => 'Could not upload the file. Please try again.',
                ],
            ], 502);
        }

        // Pick the document_type label that matches the upload's anchor.
        $documentType = $documentRequest !== null
            ? 'admin_request'
            : ($validated['document_type'] ?? 'custom_question');

        $document = ApplicationDocument::create([
            'id'                  => $documentId,
            'application_id'      => $application->id,
            'file_name'           => $file->getClientOriginalName(),
            'file_type'           => $extension,
            'storage_path'        => $storagePath,
            'document_type'       => $documentType,
            'form_field_id'       => $validated['form_field_id'] ?? null,
            'document_request_id' => $documentRequest?->id,
            'file_size_bytes'     => $file->getSize(),
            'uploaded_at'         => now(),
        ]);

        // Admin fan-out for request-driven uploads. The request stays pending until an admin
        // explicitly marks it fulfilled (per the manual-ack design); this notification surfaces
        // the file so the admin knows to review.
        if ($documentRequest !== null) {
            $applicantName = $user->full_name ?: 'The applicant';
            foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
                Notification::create([
                    'user_id'        => $adminId,
                    'application_id' => $application->id,
                    'type'           => 'document_uploaded',
                    'message'        => "{$applicantName} uploaded {$document->file_name} for application {$application->reference_number}: {$documentRequest->label}.",
                    'is_read'        => false,
                ]);
            }
        }

        $document->download_url = $this->safeSign($document->storage_path);

        return response()->json([
            'data' => new ApplicationDocumentResource($document),
        ], 201);
    }

    // DELETE /documents/{document}
    // Applicant only. Draft documents are always deletable. Documents linked to a pending
    // DocumentRequest are also deletable (so the applicant can replace before admin ack);
    // anything else is frozen once the application is submitted.
    public function destroy(Request $request, ApplicationDocument $document): JsonResponse
    {
        $user = $request->user();
        $application = $document->application;

        if ($application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this document.',
                ],
            ], 403);
        }

        $linkedRequest = $document->document_request_id
            ? DocumentRequest::find($document->document_request_id)
            : null;

        // A document linked to a still-pending request can be replaced even on a submitted app.
        // Without that link, the existing "draft only" rule applies.
        $allowDelete = $linkedRequest
            ? $linkedRequest->status === 'pending'
            : $application->status === 'draft';

        if (! $allowDelete) {
            return response()->json([
                'error' => [
                    'code'    => 'not_deletable',
                    'message' => $linkedRequest
                        ? 'This document request is no longer accepting changes.'
                        : 'This application has been submitted and its documents can no longer be deleted.',
                ],
            ], 422);
        }

        try {
            $this->storage->delete($document->storage_path);
        } catch (Throwable) {
            // Storage delete failure shouldn't block the row delete — the orphan is preferable to a stuck UI.
        }

        $document->delete();

        return response()->json(null, 204);
    }

    // Wraps signing so a transient Supabase failure leaves download_url=null instead of bubbling a 500.
    private function safeSign(string $path): ?string
    {
        try {
            return $this->storage->signedUrl($path);
        } catch (Throwable) {
            return null;
        }
    }
}
