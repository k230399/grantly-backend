<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
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
        // TODO (Step 7 / 9): scope to applicant vs admin, add filters
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * GET /api/v1/applications/{id}
     *
     * Returns the full details of a single application.
     * Applicants can only view their own applications.
     *
     * @param Application $application — automatically resolved from the route parameter
     * @return JsonResponse — application object with related grant round, documents, and status history
     */
    public function show(Application $application): JsonResponse
    {
        // TODO (Step 7 / 10): authorisation check, eager-load relationships
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * POST /api/v1/applications
     *
     * Creates a new draft application for the authenticated applicant.
     * Requires a valid open grant round ID.
     *
     * @param Request $request — body: grant_round_id, project_name, project_description, funding_requested, total_project_budget
     * @return JsonResponse — the newly created application (status = draft)
     */
    public function store(Request $request): JsonResponse
    {
        // TODO (Step 7): validate round is open, create draft application
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * PUT/PATCH /api/v1/applications/{id}
     *
     * Updates a draft application.
     * Only allowed while the application is in "draft" status.
     * Once submitted, applications are locked.
     *
     * @param Request $request — body contains the fields to update
     * @param Application $application — the application to update
     * @return JsonResponse — the updated application
     */
    public function update(Request $request, Application $application): JsonResponse
    {
        // TODO (Step 7): check status = draft, check ownership, validate + update
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * DELETE /api/v1/applications/{id}
     *
     * Deletes a draft application (applicant only).
     * Submitted applications cannot be deleted.
     *
     * @param Application $application — the application to delete
     * @return JsonResponse — 204 No Content on success
     */
    public function destroy(Application $application): JsonResponse
    {
        // TODO (Step 7): check status = draft, check ownership, delete
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * POST /api/v1/applications/{id}/submit
     *
     * Submits a draft application — transitions status from "draft" to "submitted".
     * Validates that all required fields and declaration_accepted = true.
     * Sets submitted_at to the current timestamp.
     * Triggers a confirmation email via Resend (Step 11).
     *
     * @param Application $application — the application to submit
     * @return JsonResponse — the updated application with status = submitted
     */
    public function submit(Application $application): JsonResponse
    {
        // TODO (Step 7): validate completeness, set status + submitted_at, trigger email
        return response()->json(['message' => 'Not yet implemented'], 501);
    }
}
