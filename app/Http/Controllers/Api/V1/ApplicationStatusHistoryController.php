<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only view of the status change audit trail for an application.
 * Both admins and applicants can view status history for applications they have access to.
 * Endpoint prefix: /api/v1/applications/{application}/status-history
 */
class ApplicationStatusHistoryController extends Controller
{
    /**
     * GET /api/v1/applications/{application}/status-history
     *
     * Returns the full timeline of status changes for an application, oldest first.
     * Each entry includes who made the change, when, and any notes they left.
     *
     * @param Request $request — used to read the authenticated user
     * @param Application $application — the application whose history to return
     * @return JsonResponse — array of status history entries ordered by changed_at ascending
     */
    public function index(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();

        // Ownership check — applicants can only see history for their own applications.
        // Admins can view any application's history.
        if ($user->role !== 'admin' && $application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        // The relationship on the model already orders by changed_at ASC (oldest first),
        // so the timeline reads top-to-bottom in chronological order.
        $history = $application->statusHistory;

        return response()->json([
            // Map each history row into a flat shape — we don't expose the audit row's
            // own UUID and bookkeeping timestamps because callers only care about the
            // status transition itself.
            'data' => $history->map(fn ($entry) => [
                'id'              => $entry->id,
                'previous_status' => $entry->previous_status,
                'new_status'      => $entry->new_status,
                'notes'           => $entry->notes,
                'changed_by'      => $entry->changed_by,
                // ISO 8601 so the frontend can localise the timestamp
                'changed_at'      => $entry->changed_at?->toIso8601String(),
            ]),
        ]);
    }
}
