<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notifications\IndexNotificationsRequest;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notifications read API (PLAN_CARTOES_EMPRESTIMOS §5).
 */
class NotificationController extends Controller
{
    public function index(IndexNotificationsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $query = $request->user()
            ->notifications()
            ->latest();

        if ($request->unreadOnly()) {
            $query->whereNull('read_at');
        }

        $items = $query->limit($request->limit())->get();

        return response()->json([
            'data' => NotificationResource::collection($items)->resolve(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $count = $request->user()->unreadNotifications()->count();

        return response()->json([
            'data' => [
                'count' => $count,
            ],
        ]);
    }

    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'data' => (new NotificationResource($notification->fresh()))->resolve(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $unread = $request->user()->unreadNotifications;
        $marked = $unread->count();
        $unread->markAsRead();

        return response()->json([
            'data' => [
                'marked' => $marked,
            ],
        ]);
    }
}
