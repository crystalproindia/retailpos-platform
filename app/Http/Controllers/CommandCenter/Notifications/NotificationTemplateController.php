<?php

namespace App\Http\Controllers\CommandCenter\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationTemplateRequest;
use App\Models\NotificationTemplate;
use App\Services\AuditLogger;
use App\Support\Events\EventCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    public function index(Request $request, EventCatalog $eventCatalog): View
    {
        return view('command-center.notifications.templates.index', [
            'templates' => NotificationTemplate::query()
                ->where(function ($query) use ($request): void {
                    $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id);
                })
                ->latest()
                ->paginate(15),
            'eventOptions' => $eventCatalog->all(),
        ]);
    }

    public function update(UpdateNotificationTemplateRequest $request, AuditLogger $auditLogger, int $template): RedirectResponse
    {
        $model = NotificationTemplate::query()
            ->where(function ($query) use ($request): void {
                $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id);
            })
            ->findOrFail($template);

        $model->update($request->validated() + [
            'is_active' => $request->boolean('is_active'),
            'version' => $model->version + 1,
        ]);

        $auditLogger->record('notification.template.updated', $model, 'Notification template updated');

        return back()->with('status', 'Notification template updated.');
    }
}
