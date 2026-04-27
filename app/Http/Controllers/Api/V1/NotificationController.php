<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles the in-app notification inbox for the authenticated user.
 * Users can list their notifications and mark them as read.
 * Endpoint prefix: /api/v1/notifications
 */
class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     *
     * Returns all notifications for the authenticated user, newest first.
     * Optionally filter to only unread notifications via ?unread=true
     *
     * @param Request $request — may include query param: unread (boolean)
     * @return JsonResponse — array of notification objects
     */
    public function index(Request $request): JsonResponse
    {
        // TODO (Step 11): scope to auth user, filter by is_read if requested, paginate
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * PATCH /api/v1/notifications/{notification}/read
     *
     * Marks a single notification as read (sets is_read = true).
     *
     * @param Notification $notification — the notification to mark as read
     * @return JsonResponse — the updated notification
     */
    public function markRead(Notification $notification): JsonResponse
    {
        // TODO (Step 11): check ownership, set is_read = true
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * PATCH /api/v1/notifications/read-all
     *
     * Marks all unread notifications for the authenticated user as read.
     * Used when the user opens their notification panel.
     *
     * @param Request $request — no body required; acts on the authenticated user's notifications
     * @return JsonResponse — count of notifications marked as read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        // TODO (Step 11): bulk update all unread notifications for auth user
        return response()->json(['message' => 'Not yet implemented'], 501);
    }
}
