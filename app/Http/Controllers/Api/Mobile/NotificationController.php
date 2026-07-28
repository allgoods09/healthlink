<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->transformNotification($notification))
            ->values();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function read(Request $request, string $notificationId): JsonResponse
    {
        $notification = $this->findNotification($request, $notificationId);

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
            $notification->refresh();
        }

        return response()->json([
            'success' => true,
            'notification' => $this->transformNotification($notification),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'unread_count' => 0,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformNotification(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'HealthLink notification',
            'body' => $notification->data['body'] ?? '',
            'level' => $notification->data['level'] ?? 'info',
            'category' => $notification->data['category'] ?? 'general',
            'icon' => $notification->data['icon'] ?? 'notifications-outline',
            'action_url' => $notification->data['action_url'] ?? null,
            'action_label' => $notification->data['action_label'] ?? null,
            'sender_name' => $notification->data['sender_name'] ?? null,
            'created_at' => optional($notification->created_at)->toIso8601String(),
            'read_at' => optional($notification->read_at)->toIso8601String(),
        ];
    }

    private function findNotification(Request $request, string $notificationId): DatabaseNotification
    {
        return $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();
    }
}
