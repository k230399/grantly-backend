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
     * @param Application $application — the application whose history to return
     * @return JsonResponse — array of status history entries ordered by changed_at ascending
     */
    public function index(Application $application): JsonResponse
    {
        // TODO (Step 10): authorisation check, return ordered status history
        return response()->json(['message' => 'Not yet implemented'], 501);
    }
}
