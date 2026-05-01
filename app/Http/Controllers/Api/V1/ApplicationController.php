<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\StoreApplicationRequest;
use App\Http\Requests\Application\UpdateApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\GrantRound;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles all application endpoints.
 * Applicants can create, edit, and submit their own applications.
 * Admins can list all applications, view details, and change statuses.
 * Endpoint prefix: /api/v1/applications
 */
class ApplicationController extends Controller
{
    /**
     * GET /api/v1/applications
     *
     * Returns a list of applications.
     * Applicants see only their own applications.
     * Admins see all applications with filtering/search/pagination.
     *
     * @param Request $request — may include query params: status, grant_round_id, search, page
     * @return JsonResponse — paginated list of applications
     */
    public function index(Request $request): JsonResponse
    {
        // The auth.supabase middleware guarantees a user is present here
        $user = $request->user();

        // Admins get the full picture: every application across every round, with the
        // applicant and the round eager-loaded for the listing UI, plus a documents count.
        // Applicants get a narrower scope (only their own apps) and a smaller payload
        // (only the round, since they already know they're the applicant).
        if ($user->role === 'admin') {
            $query = Application::with(['applicant', 'grantRound'])->withCount('documents');
        } else {
            $query = Application::with('grantRound')
                // Scope to the current user's applications only — applicants must never
                // see another applicant's data.
                ->where('applicant_id', $user->id);
        }

        // Optional ?status= filter — only applies when the value is one of the known
        // lifecycle states. Anything else is silently ignored to avoid 500-ing on
        // typos in the URL.
        $statusFilter = $request->query('status');
        if ($statusFilter && in_array($statusFilter, ['draft', 'submitted', 'under_review', 'approved', 'rejected'])) {
            $query->where('status', $statusFilter);
        }

        // Optional ?grant_round_id= filter — useful for admins viewing all apps for a round
        $grantRoundId = $request->query('grant_round_id');
        if ($grantRoundId) {
            $query->where('grant_round_id', $grantRoundId);
        }

        // Optional ?search= — case-insensitive partial match on project name.
        // ILIKE is Postgres-specific; using LIKE here keeps it portable but case-sensitive.
        // Project names are short and user-typed, so a simple LIKE is sufficient for now.
        $search = $request->query('search');
        if ($search) {
            $query->where('project_name', 'like', '%' . $search . '%');
        }

        // Newest first — applicants want to see their most recent draft at the top
        $applications = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            // Each application is shaped by ApplicationResource before being serialised
            'data' => ApplicationResource::collection($applications),
            // Pagination metadata so the frontend knows how many pages exist
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page'    => $applications->lastPage(),
                'per_page'     => $applications->perPage(),
                'total'        => $applications->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/applications/{id}
     *
     * Returns the full details of a single application.
     * Applicants can only view their own applications.
     *
     * @param Request $request — used to read the authenticated user
     * @param Application $application — automatically resolved from the route parameter
     * @return JsonResponse — application object with related grant round, documents, and status history
     */
    public function show(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        // Ownership check — applicants can only see their own applications.
        // Admins skip this check since they can view any application.
        if ($user->role !== 'admin' && $application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        // Eager-load everything the detail page needs in one go to avoid N+1 queries
        $application->load(['applicant', 'grantRound', 'documents', 'statusHistory']);

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }

    /**
     * POST /api/v1/applications
     *
     * Creates a new draft application for the authenticated applicant.
     * Requires a valid open grant round ID.
     *
     * @param StoreApplicationRequest $request — body: grant_round_id, project_name, project_description, funding_requested, total_project_budget
     * @return JsonResponse — the newly created application (status = draft)
     */
    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $user = $request->user();

        // Role check — only applicants create applications. Admins manage rounds and
        // review submissions; they don't apply for funding themselves.
        if ($user->role !== 'applicant') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only applicants can create applications.',
                ],
            ], 403);
        }

        // Look up the round so we can check its status and the multiple-applications policy.
        // The request validator already confirmed the ID exists, so findOrFail is safe.
        $grantRound = GrantRound::findOrFail($request->grant_round_id);

        // Round must be currently accepting applications.
        // is_published = visible to applicants; status = open means actively accepting.
        // A round in draft or closed state shouldn't be receiving new applications.
        if ($grantRound->status !== 'open' || ! $grantRound->is_published) {
            return response()->json([
                'error' => [
                    'code'    => 'grant_round_not_open',
                    'message' => 'This grant round is not currently accepting applications.',
                ],
            ], 422);
        }

        // Duplicate check — by default a user can only have one application per round.
        // The round's allow_multiple_applications flag opts out of this restriction.
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

        // Create the application as a draft. status is forced to 'draft' here regardless
        // of what the client sends — applicants cannot directly submit on create; they
        // must use the dedicated submit endpoint after filling everything in.
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

        // Load the round so the response includes its title/schema for the frontend
        $application->load('grantRound');

        // 201 Created — the standard HTTP status for a successful resource creation
        return response()->json([
            'data' => new ApplicationResource($application),
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/applications/{id}
     *
     * Updates a draft application.
     * Only allowed while the application is in "draft" status.
     * Once submitted, applications are locked.
     *
     * @param UpdateApplicationRequest $request — body contains the fields to update
     * @param Application $application — the application to update
     * @return JsonResponse — the updated application
     */
    public function update(UpdateApplicationRequest $request, Application $application): JsonResponse
    {
        $user = $request->user();

        // Ownership check — applicants can only edit their own applications.
        // Admins also cannot edit applications via this endpoint (they change status
        // through a separate flow); allowing it here would let them silently rewrite
        // an applicant's answers.
        if ($application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        // Once submitted, the application is locked — the audit trail must remain
        // accurate, and admins should review what was actually submitted.
        if ($application->status !== 'draft') {
            return response()->json([
                'error' => [
                    'code'    => 'not_editable',
                    'message' => 'This application has been submitted and can no longer be edited.',
                ],
            ], 422);
        }

        // only() picks the named keys from the request body — anything not sent is left
        // untouched, giving us PATCH semantics (partial updates).
        $application->update($request->only([
            'project_name',
            'project_description',
            'funding_requested',
            'total_project_budget',
            'declaration_accepted',
            'form_data',
        ]));

        // Reload the round so the response payload reflects the latest state
        $application->load('grantRound');

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }

    /**
     * DELETE /api/v1/applications/{id}
     *
     * Deletes a draft application (applicant only).
     * Submitted applications cannot be deleted.
     *
     * @param Request $request — used to read the authenticated user
     * @param Application $application — the application to delete
     * @return JsonResponse — 204 No Content on success
     */
    public function destroy(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        // Same ownership rule as update — only the applicant can delete their own draft.
        if ($application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        // Submitted applications form part of the audit record and must not be deleted.
        // The applicant should contact an admin if they need to withdraw.
        if ($application->status !== 'draft') {
            return response()->json([
                'error' => [
                    'code'    => 'not_deletable',
                    'message' => 'This application has been submitted and can no longer be deleted.',
                ],
            ], 422);
        }

        $application->delete();

        // 204 No Content — standard HTTP response for a successful delete with no body
        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/applications/{id}/submit
     *
     * Submits a draft application — transitions status from "draft" to "submitted".
     * Validates that all required fields are filled and declaration_accepted = true.
     * Sets submitted_at to the current timestamp.
     * Triggers a confirmation email via Resend (Step 11).
     *
     * @param Request $request — used to read the authenticated user
     * @param Application $application — the application to submit
     * @return JsonResponse — the updated application with status = submitted
     */
    public function submit(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        // Ownership check — only the applicant can submit their own application
        if ($application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        // Submit is a one-way transition — only drafts can be submitted.
        // An already-submitted application would otherwise overwrite its own submitted_at
        // and create a confusing duplicate audit entry.
        if ($application->status !== 'draft') {
            return response()->json([
                'error' => [
                    'code'    => 'already_submitted',
                    'message' => 'This application has already been submitted.',
                ],
            ], 422);
        }

        // Completeness check — the database lets you save partial drafts, but submit
        // demands every required field is present. We check empty() rather than is_null()
        // so empty strings ("") also fail validation.
        $missingFields = [];
        if (empty($application->project_name))        $missingFields[] = 'project_name';
        if (empty($application->project_description)) $missingFields[] = 'project_description';
        if (is_null($application->funding_requested)) $missingFields[] = 'funding_requested';
        if (is_null($application->total_project_budget)) $missingFields[] = 'total_project_budget';
        // The declaration tickbox must be explicitly true — the applicant has to
        // actively confirm their submission is accurate.
        if (! $application->declaration_accepted)     $missingFields[] = 'declaration_accepted';

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

        // The round may have closed while the applicant was filling in the form.
        // Re-check now so we don't accept a submission against a closed round.
        $grantRound = $application->grantRound;
        if ($grantRound->status !== 'open') {
            return response()->json([
                'error' => [
                    'code'    => 'grant_round_closed',
                    'message' => 'This grant round is no longer accepting applications.',
                ],
            ], 422);
        }

        // Flip status and stamp the submission time. submitted_at is the canonical
        // "this was submitted" timestamp — distinct from updated_at which can change
        // for any reason.
        $application->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        // Append an entry to the audit trail so admins can see who submitted and when.
        // changed_at is the meaningful business timestamp; created_at would be near-identical
        // but is metadata about the row itself rather than the event.
        ApplicationStatusHistory::create([
            'application_id'  => $application->id,
            'changed_by'      => $user->id,
            'previous_status' => 'draft',
            'new_status'      => 'submitted',
            'notes'           => null,
            'changed_at'      => now(),
        ]);

        // Drop a notification into the applicant's inbox so they have an in-app
        // confirmation that the submission was received. The transactional email
        // (Resend) is wired up in Step 11.
        Notification::create([
            'user_id'        => $user->id,
            'application_id' => $application->id,
            'type'           => 'application_submitted',
            // Reference number is the human-friendly id (e.g. APP-2026-000042)
            'message'        => "Your application {$application->reference_number} has been submitted.",
            'is_read'        => false,
        ]);

        // Reload the round so the response payload reflects the updated status
        $application->load('grantRound');

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }
}
