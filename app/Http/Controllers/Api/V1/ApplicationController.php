<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\StoreApplicationRequest;
use App\Http\Requests\Application\UpdateApplicationRequest;
use App\Http\Requests\Application\UpdateApplicationStatusRequest;
use App\Http\Resources\ApplicationResource;
use App\Mail\ApplicationStatusChanged;
use App\Mail\ApplicationSubmitted;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\GrantRound;
use App\Models\Notification;
use App\Models\User;
use App\Services\AbrLookupService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class ApplicationController extends Controller
{
    public function __construct(private readonly SupabaseStorageService $storage)
    {
    }

    // GET /api/v1/applications
    // Lists applications, scoped per role: applicants see their own only, admins see all
    // with applicant + round eager-loaded. Supports ?status=, ?grant_round_id=, ?search=
    // filters and is paginated 15-per-page, newest first.
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Admins see every application with applicant context; applicants see only their own.
        if ($user->role === 'admin') {
            $query = Application::with(['applicant', 'grantRound'])->withCount('documents');
        } else {
            $query = Application::with('grantRound')
                ->where('applicant_id', $user->id);
        }

        // Optional filters. Unknown status values are ignored rather than 500.
        $statusFilter = $request->query('status');
        if ($statusFilter && in_array($statusFilter, ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'withdrawn'])) {
            $query->where('status', $statusFilter);
        }

        $grantRoundId = $request->query('grant_round_id');
        if ($grantRoundId) {
            $query->where('grant_round_id', $grantRoundId);
        }

        $search = $request->query('search');
        if ($search) {
            $query->where('project_name', 'like', '%' . $search . '%');
        }

        $applications = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'data' => ApplicationResource::collection($applications),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page'    => $applications->lastPage(),
                'per_page'     => $applications->perPage(),
                'total'        => $applications->total(),
            ],
        ]);
    }

    // GET /api/v1/applications/{application}
    // Returns one application with grant round, applicant, documents, and status history
    // eager-loaded. Applicants only see their own; admins see any.
    public function show(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        // Applicants can only see their own applications; admins see any.
        if ($user->role !== 'admin' && $application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        $application->load(['applicant', 'grantRound', 'documents', 'statusHistory']);

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }

    // POST /api/v1/applications
    // Creates a draft application. Applicant-only. The grant round must be currently
    // open + published, and the round's allow_multiple_applications flag is respected.
    // Status always starts as 'draft' regardless of what the client sends.
    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'applicant') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only applicants can create applications.',
                ],
            ], 403);
        }

        // The round must be live and visible to applicants.
        $grantRound = GrantRound::findOrFail($request->grant_round_id);
        if ($grantRound->status !== 'open' || ! $grantRound->is_published) {
            return response()->json([
                'error' => [
                    'code'    => 'grant_round_not_open',
                    'message' => 'This grant round is not currently accepting applications.',
                ],
            ], 422);
        }

        // One application per applicant per round, unless the round opts in to multiples.
        if (! $grantRound->allow_multiple_applications) {
            $existingApplication = Application::where('grant_round_id', $grantRound->id)
                ->where('applicant_id', $user->id)
                ->exists();

            if ($existingApplication) {
                return response()->json([
                    'error' => [
                        'code'    => 'duplicate_application',
                        'message' => 'You already have an application for this grant round.',
                    ],
                ], 422);
            }
        }

        // Status is always 'draft' on create. Submission is a separate, deliberate action.
        $application = Application::create([
            'applicant_id'         => $user->id,
            'grant_round_id'       => $grantRound->id,
            'project_name'         => $request->project_name,
            'project_description'  => $request->project_description,
            'funding_requested'    => $request->funding_requested,
            'total_project_budget' => $request->total_project_budget,
            'declaration_accepted' => $request->boolean('declaration_accepted', false),
            'form_data'            => $request->form_data,
            'status'               => 'draft',
        ]);

        $application->load('grantRound');

        return response()->json([
            'data' => new ApplicationResource($application),
        ], 201);
    }

    // PUT/PATCH /api/v1/applications/{application}
    // Updates a draft application. Applicant-only and draft-only: once submitted the row
    // is locked. PATCH semantics — only the fields you send are changed.
    public function update(UpdateApplicationRequest $request, Application $application): JsonResponse
    {
        $user = $request->user();

        // Only the applicant can edit their own draft. Admins use a separate status endpoint.
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

        $application->update($request->only([
            'project_name',
            'project_description',
            'funding_requested',
            'total_project_budget',
            'declaration_accepted',
            'form_data',
        ]));

        $application->load('grantRound');

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }

    // DELETE /api/v1/applications/{application}
    // Permanently discards a draft application (and its uploaded documents in Supabase
    // Storage). Applicant-only and draft-only — submitted applications are audit records
    // and cannot be removed; use withdraw or status-change instead.
    public function destroy(Request $request, Application $application): JsonResponse
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

        // Submitted applications are part of the audit record and cannot be deleted.
        if ($application->status !== 'draft') {
            return response()->json([
                'error' => [
                    'code'    => 'not_deletable',
                    'message' => 'This application has been submitted and can no longer be deleted.',
                ],
            ], 422);
        }

        // Collect storage paths before the DB cascade wipes application_documents rows.
        // Without this, files in Supabase Storage would be orphaned (no DB pointer left).
        $storagePaths = $application->documents()->pluck('storage_path');

        $application->delete();

        // Storage cleanup is best-effort: a transient Supabase failure must not leave the
        // application half-deleted in the DB. Orphans are recoverable, dual-write rollback is not.
        foreach ($storagePaths as $path) {
            try {
                $this->storage->delete($path);
            } catch (Throwable) {
                // Swallow.
            }
        }

        return response()->json(null, 204);
    }

    // POST /api/v1/applications/{application}/submit
    // One-way transition from draft to submitted. Pre-flight: required project fields,
    // declaration, every required document (both round-level and custom-question), and the
    // round is still open. Side effects: status history entry, applicant + admin notifications,
    // and a queued ApplicationSubmitted email via Resend.
    public function submit(Request $request, Application $application, AbrLookupService $abr): JsonResponse
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
                    'code'    => 'already_submitted',
                    'message' => 'This application has already been submitted.',
                ],
            ], 422);
        }

        // Drafts can be saved partially. Submit demands every required field is filled.
        $missingFields = [];
        if (empty($application->project_name))        $missingFields[] = 'project_name';
        if (empty($application->project_description)) $missingFields[] = 'project_description';
        if (is_null($application->funding_requested)) $missingFields[] = 'funding_requested';
        if (is_null($application->total_project_budget)) $missingFields[] = 'total_project_budget';
        if (! $application->declaration_accepted)     $missingFields[] = 'declaration_accepted';

        // Every required custom-question field must be answered. Documents are validated against
        // application_documents rows linked by form_field_id; every other type is validated against
        // the answer stored under form_data[field.id].
        $schema = $application->grantRound->application_form_schema;
        if (is_array($schema) && isset($schema['fields']) && is_array($schema['fields'])) {
            $uploadedFieldIds = $application->documents()
                ->whereNotNull('form_field_id')
                ->pluck('form_field_id')
                ->all();

            $formData = is_array($application->form_data) ? $application->form_data : [];

            foreach ($schema['fields'] as $field) {
                if (empty($field['required'])) {
                    continue;
                }

                $fieldId = $field['id'] ?? null;
                if (! $fieldId) {
                    continue;
                }

                $type    = $field['type'] ?? null;
                $label   = $field['label'] ?? null;
                $missing = $type === 'document'
                    ? ! in_array($fieldId, $uploadedFieldIds, true)
                    : $this->isAnswerEmpty($formData[$fieldId] ?? null);

                if ($missing) {
                    $missingFields[] = $label ?: $fieldId;
                }
            }
        }

        // Organisations must supply an ABN. We only require its presence in the missing-fields
        // pass; the live ABR re-verify (which can return a typed error) runs just below so its
        // message is not flattened into the generic incomplete_application list.
        if ($application->applicant_type === 'organisation' && empty($application->abn)) {
            $missingFields[] = 'ABN';
        }

        if (! empty($missingFields)) {
            return response()->json([
                'error' => [
                    'code'    => 'incomplete_application',
                    'message' => 'Your application is missing required information and cannot be submitted.',
                    'details' => [
                        'missing_fields' => $missingFields,
                    ],
                ],
            ], 422);
        }

        // For organisations, re-verify the ABN against the ABR server-side so the gate cannot be
        // bypassed by hitting the API directly. Mirrors ProfileController: a misconfigured or
        // unreachable register is a soft pass (the format rule already ran), while a not-found or
        // cancelled ABN blocks submission.
        if ($application->applicant_type === 'organisation') {
            $abnError = $this->verifyAbn($abr, (string) $application->abn);
            if ($abnError !== null) {
                return $abnError;
            }
        }

        // The round may have closed while the applicant was filling in the form.
        $grantRound = $application->grantRound;
        if ($grantRound->status !== 'open') {
            return response()->json([
                'error' => [
                    'code'    => 'grant_round_closed',
                    'message' => 'This grant round is no longer accepting applications.',
                ],
            ], 422);
        }

        $application->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        // Convenience backfill: if the organisation applicant has no ABN on their profile yet,
        // copy this application's verified details across. We never overwrite an existing profile
        // value, so a deliberate profile edit always wins.
        if ($application->applicant_type === 'organisation' && empty($user->abn) && ! empty($application->abn)) {
            $user->forceFill([
                'abn'               => $application->abn,
                'organisation_name' => $user->organisation_name ?: $application->organisation_name,
            ])->save();
        }

        // Audit trail and applicant inbox entry.
        ApplicationStatusHistory::create([
            'application_id'  => $application->id,
            'changed_by'      => $user->id,
            'previous_status' => 'draft',
            'new_status'      => 'submitted',
            'notes'           => null,
            'changed_at'      => now(),
        ]);

        Notification::create([
            'user_id'        => $user->id,
            'application_id' => $application->id,
            'type'           => 'application_submitted',
            'message'        => "Your application {$application->reference_number} has been submitted.",
            'is_read'        => false,
        ]);

        $application->load(['grantRound', 'applicant']);

        // Admin fan-out: every admin gets an in-app notification that a new application landed.
        // Done inline because admin counts are small; revisit with a queued job if that changes.
        $roundTitle = $application->grantRound->title ?? 'a grant round';
        $applicantName = $user->full_name ?: 'An applicant';
        foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
            Notification::create([
                'user_id'        => $adminId,
                'application_id' => $application->id,
                'type'           => 'application_submitted',
                'message'        => "{$applicantName} submitted application {$application->reference_number} to {$roundTitle}.",
                'is_read'        => false,
            ]);
        }

        // Transactional confirmation email via Resend. Wrapped so a Resend outage
        // never blocks a legitimate submission (the in-app notification + DB row are the source of truth).
        try {
            Mail::to($application->applicant->email)->send(new ApplicationSubmitted($application));
        } catch (Throwable $e) {
            Log::warning('ApplicationSubmitted email failed', [
                'application_id' => $application->id,
                'error'          => $e->getMessage(),
            ]);
        }

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }

    // POST /api/v1/applications/{application}/withdraw
    // Pulls a submitted/under_review application out of the pipeline. Applicant-only.
    // Optional 'reason' is stored on the audit-log entry. Side effects mirror submit:
    // status history + applicant notification + admin fan-out. Withdraw is email-free.
    public function withdraw(Request $request, Application $application): JsonResponse
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

        // Withdraw is only available while the application is still in the review pipeline.
        // Drafts use discard (DELETE) instead; approved/rejected are terminal and locked.
        if (! in_array($application->status, ['submitted', 'under_review'], true)) {
            return response()->json([
                'error' => [
                    'code'    => 'not_withdrawable',
                    'message' => 'This application cannot be withdrawn from its current status.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousStatus = $application->status;

        $application->update(['status' => 'withdrawn']);

        // The reason is stored on the audit-log entry so admins can see why the applicant pulled out.
        ApplicationStatusHistory::create([
            'application_id'  => $application->id,
            'changed_by'      => $user->id,
            'previous_status' => $previousStatus,
            'new_status'      => 'withdrawn',
            'notes'           => $validated['reason'] ?? null,
            'changed_at'      => now(),
        ]);

        Notification::create([
            'user_id'        => $user->id,
            'application_id' => $application->id,
            'type'           => 'application_withdrawn',
            'message'        => "Your application {$application->reference_number} has been withdrawn.",
            'is_read'        => false,
        ]);

        // Admin fan-out for the same event so reviewers know the queue shrank.
        $applicantName = $user->full_name ?: 'the applicant';
        foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
            Notification::create([
                'user_id'        => $adminId,
                'application_id' => $application->id,
                'type'           => 'application_withdrawn',
                'message'        => "Application {$application->reference_number} was withdrawn by {$applicantName}.",
                'is_read'        => false,
            ]);
        }

        $application->load('grantRound');

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }

    // PATCH /api/v1/applications/{application}/status
    // Admin-only status change as part of the review workflow. Free-form: any valid status
    // is allowed, including reopening terminal states (the UI flags this with a warning).
    // Side effects: status history entry, applicant notification, and a queued
    // ApplicationStatusChanged email via Resend.
    public function updateStatus(UpdateApplicationStatusRequest $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can change application status.',
                ],
            ], 403);
        }

        $previousStatus = $application->status;
        $newStatus      = $request->status;

        // No-op changes are rejected so the audit trail only contains real transitions.
        if ($previousStatus === $newStatus) {
            return response()->json([
                'error' => [
                    'code'    => 'no_status_change',
                    'message' => 'The application is already in this status.',
                ],
            ], 422);
        }

        $application->update(['status' => $newStatus]);

        ApplicationStatusHistory::create([
            'application_id'  => $application->id,
            'changed_by'      => $user->id,
            'previous_status' => $previousStatus,
            'new_status'      => $newStatus,
            'notes'           => $request->notes,
            'changed_at'      => now(),
        ]);

        $readableStatus = str_replace('_', ' ', $newStatus);
        Notification::create([
            'user_id'        => $application->applicant_id,
            'application_id' => $application->id,
            'type'           => 'application_status_changed',
            'message'        => "Your application {$application->reference_number} is now {$readableStatus}.",
            'is_read'        => false,
        ]);

        $application->load(['applicant', 'grantRound']);

        // Transactional status-change email. Same swallow-on-failure pattern as submit:
        // the source of truth is the DB row + in-app notification.
        try {
            Mail::to($application->applicant->email)->send(new ApplicationStatusChanged(
                $application,
                $previousStatus,
                $newStatus,
                $request->notes,
            ));
        } catch (Throwable $e) {
            Log::warning('ApplicationStatusChanged email failed', [
                'application_id' => $application->id,
                'new_status'     => $newStatus,
                'error'          => $e->getMessage(),
            ]);
        }

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }

    // Used by submit() to decide whether a required custom-question answer counts as missing.
    // Numbers like 0 are valid; only null/empty-string/empty-array/whitespace-only count as empty.
    private function isAnswerEmpty(mixed $answer): bool
    {
        if (is_null($answer)) {
            return true;
        }

        if (is_string($answer)) {
            return trim($answer) === '';
        }

        if (is_array($answer)) {
            // multi_choice ships an array of selected option values; treat whitespace-only entries as absent.
            foreach ($answer as $value) {
                if (is_string($value) ? trim($value) !== '' : ! is_null($value)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    // Re-verifies an organisation's ABN against the ABR at submit time. Returns a JsonResponse to
    // bail with, or null when the ABN is valid + active. Mirrors ProfileController::verifyAbn: a
    // missing/unreachable register is a soft pass so an ABR outage cannot block a real submission.
    private function verifyAbn(AbrLookupService $abr, string $abn): ?JsonResponse
    {
        try {
            $abr->lookup($abn);
            return null;
        } catch (RuntimeException $e) {
            $code = $e->getMessage();

            if ($code === 'not_configured' || $code === 'lookup_failed') {
                return null;
            }

            $message = match ($code) {
                'not_found'      => 'This ABN was not found on the Australian Business Register.',
                'cancelled'      => 'This ABN is on the register but is no longer active.',
                'invalid_format' => 'ABN must be exactly 11 digits.',
                default          => 'Could not verify this ABN against the Australian Business Register. Please try again.',
            };

            return response()->json([
                'error' => [
                    'code'    => 'invalid_abn',
                    'message' => $message,
                    'details' => ['abn' => [$message]],
                ],
            ], 422);
        }
    }
}
