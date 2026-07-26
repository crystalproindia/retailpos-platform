<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\InvoiceReminderStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\UpdateInvoiceReminderSettingsRequest;
use App\Repositories\Crm\InvoiceReminderRepository;
use App\Services\Crm\InvoiceReminderSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceReminderSettingsController extends Controller
{
    public function index(Request $request, InvoiceReminderSettingsService $settings, InvoiceReminderRepository $reminders): View
    {
        $setting = $settings->ensure($request->user()->company);
        $dueSoonDays = abs((int) ($setting->rules->first(fn ($rule) => $rule->stage === InvoiceReminderStage::DueSoon)?->offset_days ?? -3));

        return view('command-center.crm.invoices.reminders.settings', [
            'setting' => $setting,
            'tenantToday' => now($request->user()->company->timezone ?: config('app.timezone')),
            'summary' => $reminders->summary(
                $request->user()->company_id,
                $dueSoonDays,
                $request->user()->company->timezone ?: config('app.timezone'),
            ),
            'stages' => InvoiceReminderStage::cases(),
        ]);
    }

    public function update(UpdateInvoiceReminderSettingsRequest $request, InvoiceReminderSettingsService $settings): RedirectResponse
    {
        $data = $request->validated();
        $data['automatic_enabled'] = $request->boolean('automatic_enabled');
        $data['rules'] = collect($data['rules'])->map(fn (array $rule): array => [
            ...$rule,
            'enabled' => $request->boolean('rules.'.$rule['stage'].'.enabled'),
            'attach_pdf' => $request->boolean('rules.'.$rule['stage'].'.attach_pdf'),
            'include_secure_link' => $request->boolean('rules.'.$rule['stage'].'.include_secure_link'),
        ])->values()->all();

        $settings->update($request->user()->company, $request->user(), $data);

        return back()->with('status', 'Invoice reminder settings saved.');
    }

    public function restore(Request $request, InvoiceReminderSettingsService $settings): RedirectResponse
    {
        abort_unless($request->user()->can('sales.reminders.manage'), 403);
        $settings->restoreDefaults($request->user()->company, $request->user());

        return back()->with('status', 'Recommended reminder defaults restored. Automatic reminders remain disabled until you enable them.');
    }
}
