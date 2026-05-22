<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationDocumentResource;
use App\Models\Application;
use App\Models\ApplicationDocument;
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
    // Applicant only, draft only. Multipart upload: file + document_type.
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

        if ($application->status !== 'draft') {
            return response()->json([
                'error' => [
                    'code'    => 'not_editable',
                    'message' => 'This application has been submitted and can no longer be edited.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            // 10 MB cap (NFR12). The mimes list mirrors the allowed file types in CLAUDE.md.
            'file'          => ['required', 'file', 'max:10240', 'mimes:pdf,docx,xlsx,jpg,jpeg,png'],
            // document_type identifies the slot for round-level "Required Documents".
            // form_field_id links the upload to a custom-question 'document' field on the schema.
            // At least one of the two must be present so every document is anchored to something.
            'document_type' => ['required_without:form_field_id', 'nullable', 'string', 'max:50'],
            'form_field_id' => ['required_without:document_type', 'nullable', 'uuid'],
        ]);

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

        $document = ApplicationDocument::create([
            'id'              => $documentId,
            'application_id'  => $application->id,
            'file_name'       => $file->getClientOriginalName(),
            'file_type'       => $extension,
            'storage_path'    => $storagePath,
            // Custom-question uploads use a fixed document_type since form_field_id identifies the slot.
            'document_type'   => $validated['document_type'] ?? 'custom_question',
            'form_field_id'   => $validated['form_field_id'] ?? null,
            'file_size_bytes' => $file->getSize(),
            'uploaded_at'     => now(),
        ]);

        $document->download_url = $this->safeSign($document->storage_path);

        return response()->json([
            'data' => new ApplicationDocumentResource($document),
        ], 201);
    }

    // DELETE /documents/{document}
    // Applicant only, draft only. Removes the object from Supabase Storage and the row.
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

        if ($application->status !== 'draft') {
            return response()->json([
                'error' => [
                    'code'    => 'not_deletable',
                    'message' => 'This application has been submitted and its documents can no longer be deleted.',
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
