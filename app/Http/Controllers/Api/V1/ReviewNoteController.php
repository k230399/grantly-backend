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

class ReviewNoteController extends Controller
{
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

        // Reviewer is always the authenticated admin; the client cannot set it.
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

        // Only the original author can edit, so admins can't silently overwrite each other's reasoning.
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
