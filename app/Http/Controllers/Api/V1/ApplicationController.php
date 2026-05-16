<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Application\StoreApplicationRequest;
use App\Http\Requests\Application\UpdateApplicationRequest;
use App\Http\Requests\Application\UpdateApplicationStatusRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\GrantRound;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
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
        if ($statusFilter && in_array($statusFilter, ['draft', 'submitted', 'under_review', 'approved', 'rejected'])) {
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

        $application->delete();

        return response()->json(null, 204);
    }

    public function submit(Request $request, Application $application): JsonResponse
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

        // Audit trail and applicant inbox entry. Transactional email is wired in Step 11.
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

        $application->load('grantRound');

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }

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

        return response()->json([
            'data' => new ApplicationResource($application),
        ]);
    }
}
