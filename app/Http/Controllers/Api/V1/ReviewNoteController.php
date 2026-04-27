<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ReviewNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles review notes that admins leave on applications.
 * Internal notes (is_internal = true) are only visible to admins.
 * Public notes (is_internal = false) are also visible to the applicant.
 * Endpoint prefix: /api/v1/applications/{application}/review-notes
 */
class ReviewNoteController extends Controller
{
    /**
     * GET /api/v1/applications/{application}/review-notes
     *
     * Returns all review notes on an application.
     * Admins see all notes. Applicants see only non-internal notes (is_internal = false).
     *
     * @param Application $application — the application whose notes to return
     * @return JsonResponse — array of review note objects
     */
    public function index(Application $application): JsonResponse
    {
        // TODO (Step 10): scope by role — hide internal notes from applicants
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * POST /api/v1/applications/{application}/review-notes
     *
     * Creates a new review note on an application (admin only).
     *
     * @param Request $request — body: note_content (string), is_internal (boolean)
     * @param Application $application — the application to attach the note to
     * @return JsonResponse — the newly created note
     */
    public function store(Request $request, Application $application): JsonResponse
    {
        // TODO (Step 10): check admin role, validate, create note
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * PUT/PATCH /api/v1/review-notes/{note}
     *
     * Updates the content or visibility of a review note (admin only).
     *
     * @param Request $request — body: note_content, is_internal
     * @param ReviewNote $note — the note to update
     * @return JsonResponse — the updated note
     */
    public function update(Request $request, ReviewNote $note): JsonResponse
    {
        // TODO (Step 10): check admin role, check ownership of note, validate, update
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * DELETE /api/v1/review-notes/{note}
     *
     * Deletes a review note (admin only).
     *
     * @param ReviewNote $note — the note to delete
     * @return JsonResponse — 204 No Content on success
     */
    public function destroy(ReviewNote $note): JsonResponse
    {
        // TODO (Step 10): check admin role, delete
        return response()->json(['message' => 'Not yet implemented'], 501);
    }
}
