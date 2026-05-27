<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationStatusHistoryController extends Controller
{
    // GET /api/v1/applications/{application}/status-history
    // Returns the full audit trail of status changes on an application, oldest first.
    // Applicants only see history on their own apps; admins see any. Read-only by design.
    public function index(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        // Applicants can only see history for their own applications; admins see any.
        if ($user->role !== 'admin' && $application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        // The model relationship already orders oldest-first so the timeline reads top-to-bottom.
        $history = $application->statusHistory;

        return response()->json([
            'data' => $history->map(fn ($entry) => [
                'id'              => $entry->id,
                'previous_status' => $entry->previous_status,
                'new_status'      => $entry->new_status,
                'notes'           => $entry->notes,
                'changed_by'      => $entry->changed_by,
                'changed_at'      => $entry->changed_at?->toIso8601String(),
            ]),
        ]);
    }
}
