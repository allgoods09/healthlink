<?php

namespace App\Http\Controllers;

use App\Support\ExportDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->string('status')->toString();
        $layout = $user->role === 'admin' ? 'layouts.admin' : 'layouts.portal';

        $query = $this->listingQuery($request);

        return view('notifications.index', [
            'layout' => $layout,
            'notifications' => $query->paginate(20)->withQueryString(),
            'statusFilter' => $status === 'unread' ? 'unread' : 'all',
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $columns = [
            'Title' => fn (DatabaseNotification $notification) => $notification->data['title'] ?? 'HealthLink notification',
            'Status' => fn (DatabaseNotification $notification) => $notification->read_at ? 'Read' : 'Unread',
            'Received At' => fn (DatabaseNotification $notification) => $notification->created_at?->format('Y-m-d H:i:s'),
        ];

        return ExportDownload::make($format, 'My Notifications', 'Notifications', 'my_notifications', $columns, $this->listingQuery($request)->get(), ['Status' => $request->input('status') === 'unread' ? 'Unread' : 'All'], 'Current user only', DatabaseNotification::class);
    }

    private function listingQuery(Request $request)
    {
        $status = $request->string('status')->toString();

        return $request->user()->notifications()
            ->when($status === 'unread', fn ($builder) => $builder->whereNull('read_at'))
            ->orderByRaw('case when read_at is null then 0 else 1 end')
            ->latest()
            ->orderByDesc('id');
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
