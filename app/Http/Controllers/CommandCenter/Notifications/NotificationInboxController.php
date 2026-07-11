<?php

namespace App\Http\Controllers\CommandCenter\Notifications;

use App\Http\Controllers\Controller;
use App\Repositories\Notifications\NotificationInboxRepository;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationInboxController extends Controller
{
    public function index(Request $request, NotificationInboxRepository $notificationRepository): View
    {
        return view('command-center.notifications.inbox.index', [
            'notifications' => $notificationRepository->paginateForUser($request->user(), $request->only(['status', 'search'])),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, NotificationInboxRepository $notificationRepository, AuditLogger $auditLogger, string $notification): RedirectResponse
    {
        $model = $notificationRepository->findForUser($request->user(), $notification);
        $model->markAsRead();
        $auditLogger->record('notification.marked_read', null, 'Notification marked read', [
            'company_id' => $request->user()->company_id,
            'notification_id' => $notification,
        ]);

        return back()->with('status', 'Notification marked as read.');
    }

    public function markUnread(Request $request, NotificationInboxRepository $notificationRepository, AuditLogger $auditLogger, string $notification): RedirectResponse
    {
        $model = $notificationRepository->findForUser($request->user(), $notification);
        $model->update(['read_at' => null]);
        $auditLogger->record('notification.marked_unread', null, 'Notification marked unread', [
            'company_id' => $request->user()->company_id,
            'notification_id' => $notification,
        ]);

        return back()->with('status', 'Notification marked as unread.');
    }

    public function markAllRead(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        $auditLogger->record('notification.all_marked_read', null, 'All notifications marked read', [
            'company_id' => $request->user()->company_id,
        ]);

        return back()->with('status', 'All notifications marked as read.');
    }

    public function destroy(Request $request, NotificationInboxRepository $notificationRepository, string $notification): RedirectResponse
    {
        $notificationRepository->findForUser($request->user(), $notification)->delete();

        return back()->with('status', 'Notification deleted.');
    }
}
