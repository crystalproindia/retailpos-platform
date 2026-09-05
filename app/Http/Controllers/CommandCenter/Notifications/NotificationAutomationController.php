<?php

namespace App\Http\Controllers\CommandCenter\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationAutomationRequest;
use App\Services\AuditLogger;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\NotificationAutomationSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationAutomationController extends Controller
{
    public function edit(Request $request, NotificationAutomationSettingsService $settings, EmailDeliveryService $emails): View
    {
        return view('command-center.notifications.automation.edit', [
            'setting' => $settings->forCompany($request->user()->company),
            'emailConfiguration' => $emails->configuration($request->user()->company_id),
            'whatsAppAvailable' => false,
        ]);
    }

    public function update(UpdateNotificationAutomationRequest $request, NotificationAutomationSettingsService $settings, AuditLogger $audit): RedirectResponse
    {
        $setting = $settings->forCompany($request->user()->company);
        $data = $request->validated();
        foreach ([
            'low_stock_enabled', 'out_of_stock_enabled', 'reorder_enabled', 'payment_reminders_enabled',
            'customer_payment_emails_enabled', 'quotation_expiry_enabled', 'proforma_expiry_enabled',
            'purchase_reminders_enabled', 'internal_email_enabled', 'daily_summary_enabled', 'weekly_summary_enabled',
            'monthly_expense_summary_enabled', 'monthly_profit_and_loss_summary_enabled',
        ] as $boolean) {
            $data[$boolean] = $request->boolean($boolean);
        }
        $data['payment_before_due_days'] = $this->days((string) $data['payment_before_due_days']);
        $data['payment_overdue_days'] = $this->days((string) $data['payment_overdue_days']);
        $data['updated_by'] = $request->user()->id;
        $setting->update($data);

        $audit->record('notification.automation.updated', $setting, 'Notification automation settings updated', [
            'company_id' => $request->user()->company_id,
            'customer_payment_emails_enabled' => $setting->customer_payment_emails_enabled,
            'summary_time' => $setting->summary_time,
        ]);

        return back()->with('status', 'Notifications and automation settings saved.');
    }

    /** @return array<int, int> */
    private function days(string $value): array
    {
        return collect(explode(',', $value))->map(fn (string $day): int => (int) trim($day))
            ->filter(fn (int $day): bool => $day > 0 && $day <= 365)->unique()->sort()->values()->all();
    }
}
