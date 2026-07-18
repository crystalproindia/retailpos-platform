<?php

namespace App\Http\Controllers\CommandCenter\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\UpdateEmailIntegrationRequest;
use App\Models\NotificationDelivery;
use App\Repositories\Integrations\CompanyEmailSettingsRepository;
use App\Services\AuditLogger;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class EmailIntegrationController extends Controller
{
    public function index(Request $request, EmailDeliveryService $delivery): View
    {
        $companyId = $request->user()->company_id;

        return view('command-center.integrations.email.index', [
            'setting' => app(CompanyEmailSettingsRepository::class)->forCompany($companyId),
            'configuration' => $delivery->configuration($companyId),
            'lastSuccess' => NotificationDelivery::query()->where('company_id', $companyId)->where('channel', 'email')->where('status', 'sent')->latest('delivered_at')->first(),
            'lastFailure' => NotificationDelivery::query()->where('company_id', $companyId)->where('channel', 'email')->where('status', 'failed')->latest('failed_at')->first(),
        ]);
    }

    public function update(UpdateEmailIntegrationRequest $request, CompanyEmailSettingsRepository $settings, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $data['is_enabled'] = $request->boolean('is_enabled');
        $setting = $settings->save($request->user(), $data);
        $auditLogger->record('integrations.email.settings_saved', $setting, 'Email settings saved', ['company_id' => $request->user()->company_id]);

        return back()->with('status', 'Email delivery settings saved.');
    }

    public function removePassword(Request $request, CompanyEmailSettingsRepository $settings, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.email.manage'), 403);
        $setting = $settings->forCompany($request->user()->company_id);
        if ($setting) {
            $setting->update(['password' => null, 'updated_by' => $request->user()->id]);
            $auditLogger->record('integrations.email.password_removed', $setting, 'Saved SMTP password removed', ['company_id' => $request->user()->company_id]);
        }

        return back()->with('status', 'Saved SMTP password removed.');
    }

    public function disable(Request $request, CompanyEmailSettingsRepository $settings, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.email.manage'), 403);
        $setting = $settings->save($request->user(), ['is_enabled' => false]);
        $auditLogger->record('integrations.email.disabled', $setting, 'Email delivery disabled', ['company_id' => $request->user()->company_id]);

        return back()->with('status', 'Email delivery disabled for this company.');
    }

    public function test(Request $request, EmailDeliveryService $delivery, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('email.test.send'), 403);
        $validated = $request->validate(['recipient' => ['required', 'email:rfc', 'max:255']]);
        $key = 'email-test:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['recipient' => 'Please wait before requesting another test email.']);
        }
        RateLimiter::hit($key, 600);
        $email = $delivery->queue($request->user()->company_id, $validated['recipient'], 'RetailPOS email delivery test', 'test_email', ['heading' => 'Email delivery test', 'greeting' => 'Hello,', 'message' => 'This confirms that RetailPOS Command Center can deliver email for your company.'], createdBy: $request->user(), idempotencyKey: 'email-test:'.$request->user()->id.':'.now()->format('YmdHi'));
        $auditLogger->record('email.test_requested', $email, 'Test email requested', ['company_id' => $request->user()->company_id]);

        return back()->with($email->status === 'skipped_not_configured' ? 'error' : 'status', $email->status === 'skipped_not_configured' ? 'SMTP is not configured. The test was recorded but not sent.' : 'Test email queued.');
    }
}
