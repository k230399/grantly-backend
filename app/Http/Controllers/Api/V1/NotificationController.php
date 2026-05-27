<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/v1/notifications
    // Lists the auth user's notifications, newest first, paginated 15-per-page. Accepts
    // ?unread=true to filter to unread only. Includes an independent unread_count so the
    // bell badge stays correct regardless of which page the caller is on.
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Scope every read to the auth user. Eager-load just the columns the bell needs
        // for click-through routing so the response stays slim.
        $query = Notification::where('user_id', $user->id)
            ->with(['application:id,ref_number,created_at'])
            ->orderByDesc('created_at');

        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        $paginator = $query->paginate(15);

        // Separate count so the bell badge stays correct even when the caller paginates past page 1.
        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($n) => $this->toArray($n))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'unread_count' => $unreadCount,
        ]);
    }

    // PATCH /api/v1/notifications/{notification}/read
    // Marks a single notification as read. Ownership-gated: the auth user must own the row.
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this notification.',
                ],
            ], 403);
        }

        $notification->update(['is_read' => true]);
        $notification->load(['application:id,ref_number,created_at']);

        return response()->json([
            'data' => $this->toArray($notification),
        ]);
    }

    // PATCH /api/v1/notifications/read-all
    // Bulk-marks every unread notification for the auth user as read. Returns the number
    // of rows that flipped; reads zero when the inbox was already clear.
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'data' => ['updated' => $updated],
        ]);
    }

    // Shapes a Notification row into the response payload the frontend expects.
    private function toArray(Notification $n): array
    {
        $application = $n->application;

        return [
            'id'             => $n->id,
            'type'           => $n->type,
            'message'        => $n->message,
            'is_read'        => (bool) $n->is_read,
            'application_id' => $n->application_id,
            'application'    => $application ? [
                'id'               => $application->id,
                'reference_number' => $application->reference_number,
            ] : null,
            'created_at'     => $n->created_at?->toIso8601String(),
        ];
    }
}
