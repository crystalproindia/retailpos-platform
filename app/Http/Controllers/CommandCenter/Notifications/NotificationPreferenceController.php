<?php

namespace App\Http\Controllers\CommandCenter\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Support\Events\EventCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request, NotificationService $notificationService): View
    {
        return view('command-center.notifications.preferences.index', [
            'groups' => $notificationService->groupedPreferences($request->user()),
            'channels' => config('events.channels'),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request, EventCatalog $eventCatalog, AuditLogger $auditLogger): RedirectResponse
    {
        $preferences = $request->validated('preferences') ?? [];

        foreach ($eventCatalog->all() as $eventKey => $definition) {
            if (! ($definition['user_preference_enabled'] ?? false)) {
                continue;
            }

            $row = $preferences[$eventKey] ?? [];

            NotificationPreference::updateOrCreate(
                [
                    'company_id' => $request->user()->company_id,
                    'user_id' => $request->user()->id,
                    'event_key' => $eventKey,
                ],
                [
                    'database_enabled' => (bool) ($row['database_enabled'] ?? false),
                    'email_enabled' => (bool) ($row['email_enabled'] ?? false),
                    'whatsapp_enabled' => false,
                    'sms_enabled' => false,
                    'push_enabled' => false,
                    'webhook_enabled' => false,
                    'quiet_hours_enabled' => (bool) ($row['quiet_hours_enabled'] ?? false),
                    'quiet_hours_start' => $row['quiet_hours_start'] ?? null,
                    'quiet_hours_end' => $row['quiet_hours_end'] ?? null,
                    'timezone' => $row['timezone'] ?? config('app.timezone'),
                ],
            );
        }

        $auditLogger->record('notification.preferences.updated', null, 'Notification preferences updated', [
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Notification preferences saved.');
    }

    public function reset(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        NotificationPreference::query()
            ->where('company_id', $request->user()->company_id)
            ->where('user_id', $request->user()->id)
            ->delete();

        $auditLogger->record('notification.preferences.reset', null, 'Notification preferences reset', [
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Notification preferences reset to defaults.');
    }
}
