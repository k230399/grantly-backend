<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewNote\StoreReviewNoteRequest;
use App\Http\Requests\ReviewNote\UpdateReviewNoteRequest;
use App\Http\Resources\ReviewNoteResource;
use App\Models\Application;
use App\Models\ReviewNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles review notes that admins leave on applications during review.
 * Review notes are strictly admin-only — applicants never see them.
 * Applicant-facing comms is handled separately via status-change notes
 * on the application_status_history audit trail.
 *
 * Endpoints:
 *   GET    /applications/{application}/review-notes
 *   POST   /applications/{application}/review-notes
 *   PATCH  /review-notes/{note}
 *   DELETE /review-notes/{note}
 */
class ReviewNoteController extends Controller
{
    /**
     * GET /applications/{application}/review-notes
     *
     * Lists all review notes on an application, newest first. Admin-only.
     */
    public function index(Request $request, Application $application): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can view review notes.',
                ],
            ], 403);
        }

        $notes = $application->reviewNotes()
            ->with('reviewer')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => ReviewNoteResource::collection($notes),
        ]);
    }

    /**
     * POST /applications/{application}/review-notes
     *
     * Creates a new review note. Admin only.
     * The reviewer is always the authenticated admin — the client cannot set it.
     */
    public function store(StoreReviewNoteRequest $request, Application $application): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can write review notes.',
                ],
            ], 403);
        }

        $note = ReviewNote::create([
            'application_id' => $application->id,
            'reviewer_id'    => $user->id,
            'note_content'   => $request->note_content,
        ]);

        $note->load('reviewer');

        return response()->json([
            'data' => new ReviewNoteResource($note),
        ], 201);
    }

    /**
     * PATCH /review-notes/{note}
     *
     * Updates a note's content. Only the original author can edit their own
     * notes — prevents one admin from silently overwriting another's reasoning.
     */
    public function update(UpdateReviewNoteRequest $request, ReviewNote $note): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can edit review notes.',
                ],
            ], 403);
        }

        if ($note->reviewer_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You can only edit your own review notes.',
                ],
            ], 403);
        }

        $note->update(['note_content' => $request->note_content]);
        $note->load('reviewer');

        return response()->json([
            'data' => new ReviewNoteResource($note),
        ]);
    }

    /**
     * DELETE /review-notes/{note}
     *
     * Deletes a review note. Admin-only and authorship-restricted, matching update().
     */
    public function destroy(Request $request, ReviewNote $note): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can delete review notes.',
                ],
            ], 403);
        }

        if ($note->reviewer_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You can only delete your own review notes.',
                ],
            ], 403);
        }

        $note->delete();

        return response()->json(null, 204);
    }
}
