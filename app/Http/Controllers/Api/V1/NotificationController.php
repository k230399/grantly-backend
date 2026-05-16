<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO (Step 11): scope to auth user, filter by is_read if requested, paginate
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    public function markRead(Notification $notification): JsonResponse
    {
        // TODO (Step 11): check ownership, set is_read = true
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        // TODO (Step 11): bulk update all unread notifications for auth user
        return response()->json(['message' => 'Not yet implemented'], 501);
    }
}
