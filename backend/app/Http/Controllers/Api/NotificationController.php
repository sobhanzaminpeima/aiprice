<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in user's own in-app notifications (the "database" channel). */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $user->notifications()
                ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
                ->when($request->filled('kind'), fn ($q) => $q->where('data->kind', (string) $request->query('kind')))
                ->when($request->filled('category'), fn ($q) => $q->where('data->category', (string) $request->query('category')))
                ->paginate(min(50, max(5, (int) $request->query('per_page', 20)))),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();
        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['unread_count' => 0]);
    }
}
