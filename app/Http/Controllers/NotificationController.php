<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->string('status')->toString();
        $layout = $user->role === 'admin' ? 'layouts.admin' : 'layouts.portal';

        $query = $user->notifications()
            ->when($status === 'unread', fn ($builder) => $builder->whereNull('read_at'))
            ->orderByRaw('case when read_at is null then 0 else 1 end')
            ->latest();

        return view('notifications.index', [
            'layout' => $layout,
            'notifications' => $query->paginate(20)->withQueryString(),
            'statusFilter' => $status === 'unread' ? 'unread' : 'all',
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function open(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $this->findNotification($request, $notificationId);

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        $targetUrl = $notification->data['action_url'] ?? null;

        if (is_string($targetUrl) && trim($targetUrl) !== '') {
            return redirect()->to($targetUrl);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return back()->with('success', 'All notifications were marked as read.');
    }

    private function findNotification(Request $request, string $notificationId): DatabaseNotification
    {
        return $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();
    }
}
