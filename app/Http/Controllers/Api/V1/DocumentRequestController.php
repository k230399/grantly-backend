<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\DocumentRequested;
use App\Models\Application;
use App\Models\DocumentRequest;
use App\Models\Notification;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DocumentRequestController extends Controller
{
    public function __construct(private readonly SupabaseStorageService $storage)
    {
    }

    // GET /applications/{application}/document-requests
    // Applicants see their own; admins see any. Newest first. Each row carries the linked
    // ApplicationDocument (with a fresh signed URL) and the admin who asked.
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

        $requests = $application->documentRequests()
            ->with(['document', 'requestedBy:id,full_name'])
            ->orderByDesc('requested_at')
            ->get();

        return response()->json([
            'data' => $requests->map(fn (DocumentRequest $r) => $this->toArray($r))->all(),
        ]);
    }

    // POST /applications/{application}/document-requests
    // Admin only. Application must be in submitted or under_review.
    public function store(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can request documents.',
                ],
            ], 403);
        }

        if (! in_array($application->status, ['submitted', 'under_review'], true)) {
            return response()->json([
                'error' => [
                    'code'    => 'application_not_open_for_requests',
                    'message' => 'Document requests can only be created on submitted or under-review applications.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'label'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $documentRequest = DocumentRequest::create([
            'application_id' => $application->id,
            'requested_by'   => $user->id,
            'label'          => $validated['label'],
            'description'    => $validated['description'] ?? null,
            'status'         => 'pending',
            'requested_at'   => now(),
        ]);

        // Notify the applicant. The bell will surface it within the next poll cycle.
        Notification::create([
            'user_id'        => $application->applicant_id,
            'application_id' => $application->id,
            'type'           => 'document_requested',
            'message'        => "An admin requested an additional document for application {$application->reference_number}: {$documentRequest->label}.",
            'is_read'        => false,
        ]);

        // Transactional email to the applicant. Same swallow-on-failure pattern as submit/status:
        // the in-app notification + DB row are the source of truth, so a Resend outage must not
        // block creating the request.
        try {
            $application->loadMissing('applicant', 'grantRound');
            Mail::to($application->applicant->email)->send(new DocumentRequested($application, $documentRequest));
        } catch (Throwable $e) {
            Log::warning('DocumentRequested email failed', [
                'application_id'      => $application->id,
                'document_request_id' => $documentRequest->id,
                'error'               => $e->getMessage(),
            ]);
        }

        $documentRequest->load(['document', 'requestedBy:id,full_name']);

        return response()->json([
            'data' => $this->toArray($documentRequest),
        ], 201);
    }

    // PATCH /document-requests/{documentRequest}
    // Admin only. Allowed transitions: pending → fulfilled (file must be uploaded first),
    // pending → cancelled (always allowed). Fulfilled and cancelled are terminal.
    public function update(Request $request, DocumentRequest $documentRequest): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can update document requests.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:fulfilled,cancelled'],
        ]);

        if ($documentRequest->status !== 'pending') {
            return response()->json([
                'error' => [
                    'code'    => 'request_not_pending',
                    'message' => 'This document request has already been resolved.',
                ],
            ], 422);
        }

        if ($validated['status'] === 'fulfilled') {
            $documentRequest->load('document');
            if (! $documentRequest->document) {
                return response()->json([
                    'error' => [
                        'code'    => 'no_document_attached',
                        'message' => 'The applicant has not yet uploaded a file for this request.',
                    ],
                ], 422);
            }

            $documentRequest->update([
                'status'       => 'fulfilled',
                'fulfilled_at' => now(),
            ]);
        } else {
            $documentRequest->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        $documentRequest->load(['document', 'requestedBy:id,full_name']);

        return response()->json([
            'data' => $this->toArray($documentRequest),
        ]);
    }

    // Shapes a DocumentRequest into the response payload the frontend expects.
    private function toArray(DocumentRequest $r): array
    {
        $doc = $r->document;
        return [
            'id'             => $r->id,
            'application_id' => $r->application_id,
            'label'          => $r->label,
            'description'    => $r->description,
            'status'         => $r->status,
            'requested_at'   => $r->requested_at?->toIso8601String(),
            'fulfilled_at'   => $r->fulfilled_at?->toIso8601String(),
            'cancelled_at'   => $r->cancelled_at?->toIso8601String(),
            'requested_by'   => $r->requestedBy ? [
                'id'        => $r->requestedBy->id,
                'full_name' => $r->requestedBy->full_name,
            ] : null,
            'document'       => $doc ? [
                'id'            => $doc->id,
                'file_name'     => $doc->file_name,
                'file_type'     => $doc->file_type,
                'file_size_bytes' => (int) $doc->file_size_bytes,
                'download_url'  => $this->safeSign($doc->storage_path),
                'uploaded_at'   => $doc->uploaded_at?->toIso8601String(),
            ] : null,
        ];
    }

    // Same swallow-on-failure pattern as ApplicationDocumentController::safeSign.
    private function safeSign(string $path): ?string
    {
        try {
            return $this->storage->signedUrl($path);
        } catch (Throwable) {
            return null;
        }
    }
}
