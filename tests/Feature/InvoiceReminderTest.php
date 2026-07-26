<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceReminderStage;
use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Jobs\Notifications\SendNotificationDeliveryJob;
use App\Mail\CommandCenterEmail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyEmailSetting;
use App\Models\Crm\CrmInvoice;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Crm\InvoiceReminderService;
use App\Services\Crm\InvoiceReminderSettingsService;
use App\Services\Crm\InvoiceService;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_manager_can_manage_tenant_reminder_settings_but_sales_and_staff_cannot(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $manager = $this->user(UserRole::Manager, $administrator->company, $administrator->branch);
        $sales = $this->user(UserRole::Sales, $administrator->company, $administrator->branch);
        $staff = $this->user(UserRole::Staff, $administrator->company, $administrator->branch);

        $this->actingAs($administrator)->get(route('sales.invoices.reminders.settings'))->assertOk()->assertSee('Invoice reminders');
        $setting = app(InvoiceReminderSettingsService::class)->ensure($administrator->company);
        $this->actingAs($manager)->put(route('sales.invoices.reminders.settings.update'), $this->settingsPayload($setting, ['automatic_enabled' => true]))->assertRedirect()->assertSessionHas('status');
        $this->assertTrue($setting->fresh()->automatic_enabled);
        $this->actingAs($sales)->get(route('sales.invoices.reminders.settings'))->assertForbidden();
        $this->actingAs($staff)->get(route('sales.invoices.reminders.settings'))->assertForbidden();
    }

    public function test_reminder_settings_reject_unsafe_content_duplicate_timing_and_restore_defaults(): void
    {
        $manager = $this->user(UserRole::Manager);
        $setting = app(InvoiceReminderSettingsService::class)->ensure($manager->company);
        $payload = $this->settingsPayload($setting);
        $payload['rules']['due_soon']['subject'] = '<script>alert(1)</script>';
        $payload['rules']['due_today']['offset_days'] = $payload['rules']['due_soon']['offset_days'];

        $this->actingAs($manager)->put(route('sales.invoices.reminders.settings.update'), $payload)->assertSessionHasErrors(['rules.'.$payload['rules']['due_soon']['stage'].'.subject', 'rules']);
        $this->actingAs($manager)->post(route('sales.invoices.reminders.settings.restore'))->assertRedirect()->assertSessionHas('status');
        $this->assertFalse($setting->fresh()->automatic_enabled);
        $this->assertSame(-3, $setting->fresh('rules')->rules->first(fn ($rule) => $rule->stage === InvoiceReminderStage::DueSoon)->offset_days);
    }

    public function test_automatic_eligibility_supports_due_soon_due_today_and_overdue_stages(): void
    {
        $manager = $this->user(UserRole::Manager);
        $service = app(InvoiceReminderService::class);
        $settings = app(InvoiceReminderSettingsService::class)->ensure($manager->company);
        $settings->update(['automatic_enabled' => true]);

        foreach ([[InvoiceReminderStage::DueSoon, 3], [InvoiceReminderStage::DueToday, 0], [InvoiceReminderStage::Overdue, -3]] as [$stage, $daysFromToday]) {
            $invoice = $this->issuedInvoice($manager, now()->addDays($daysFromToday)->toDateString());
            $decision = $service->automaticEligibility($invoice->fresh()->load(['company', 'creator']));
            $this->assertTrue($decision['eligible']);
            $this->assertSame($stage, $decision['rule']->stage);
        }
    }

    public function test_paid_cancelled_zero_balance_invalid_recipient_and_inactive_company_invoices_are_skipped(): void
    {
        $manager = $this->user(UserRole::Manager);
        $settings = app(InvoiceReminderSettingsService::class)->ensure($manager->company);
        $settings->update(['automatic_enabled' => true]);
        $service = app(InvoiceReminderService::class);

        foreach ([
            ['balance_due' => 0, 'status' => InvoiceStatus::Paid],
            ['status' => InvoiceStatus::Cancelled],
            ['billing_email' => 'not-an-email'],
        ] as $changes) {
            $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());
            $invoice->update($changes);
            $this->assertFalse($service->automaticEligibility($invoice->fresh()->load(['company', 'creator']))['eligible']);
        }

        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());
        $manager->company->update(['is_active' => false]);
        $this->assertFalse($service->automaticEligibility($invoice->fresh()->load(['company', 'creator']))['eligible']);
    }

    public function test_partially_paid_invoice_reminder_uses_current_outstanding_balance(): void
    {
        Queue::fake();
        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $settings = app(InvoiceReminderSettingsService::class)->ensure($manager->company);
        $settings->update(['automatic_enabled' => true]);
        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());
        $invoice->update(['amount_paid' => 2500, 'balance_due' => 9300, 'status' => InvoiceStatus::PartiallyPaid]);

        $result = app(InvoiceReminderService::class)->queueAutomatic($invoice->fresh()->load(['company', 'creator']));

        $this->assertTrue($result['queued']);
        $this->assertSame('INR 9,300.00', $result['delivery']->payload['details']['Outstanding balance']);
        $this->assertSame('automatic', $result['delivery']->reminder_source);
        Queue::assertPushed(SendNotificationDeliveryJob::class);
    }

    public function test_scheduled_command_is_idempotent_dry_run_safe_and_accepts_a_company_filter(): void
    {
        Queue::fake();
        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $settings = app(InvoiceReminderSettingsService::class)->ensure($manager->company);
        $settings->update(['automatic_enabled' => true]);
        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());

        $this->artisan('invoices:dispatch-reminders', ['--company' => $manager->company_id, '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->artisan('invoices:dispatch-reminders', ['--company' => $manager->company_id])->assertSuccessful();
        $this->assertDatabaseCount('notification_deliveries', 1);
        $this->artisan('invoices:dispatch-reminders', ['--company' => $manager->company_id])->assertSuccessful();
        $this->assertDatabaseCount('notification_deliveries', 1);
        $this->assertDatabaseHas('notification_deliveries', ['related_id' => $invoice->id, 'reminder_stage' => 'due_soon', 'reminder_source' => 'automatic']);
    }

    public function test_manual_reminder_is_authorized_distinct_from_the_invoice_email_and_rate_protected(): void
    {
        Queue::fake();
        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());

        $this->actingAs($manager)->get(route('sales.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Send payment reminder')
            ->assertSee('data-invoice-reminder-modal', false);

        $this->actingAs($manager)->post(route('sales.invoices.reminder', $invoice), ['stage' => 'due_soon', 'attach_pdf' => true, 'note' => 'Please contact us if payment is already in process.'])
            ->assertRedirect()->assertSessionHas('status', 'Payment reminder queued for delivery.');
        $delivery = NotificationDelivery::query()->sole();
        $this->assertSame('invoice_reminder_due_soon', $delivery->template_key);
        $this->assertSame('manual', $delivery->reminder_source);
        $this->assertSame('due_soon', $delivery->reminder_stage);
        $this->assertStringContainsString('payment is already in process', $delivery->payload['message']);
        $this->assertNotNull($invoice->fresh()->public_token_hash);
        $this->actingAs($manager)->post(route('sales.invoices.reminder', $invoice), ['stage' => 'due_soon', 'attach_pdf' => true])->assertSessionHasErrors('invoice');
    }

    public function test_cross_tenant_and_restricted_users_cannot_send_manual_reminders(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());
        $other = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);

        $this->actingAs($other)->post(route('sales.invoices.reminder', $invoice), ['stage' => 'due_soon'])->assertNotFound();
        $this->actingAs($sales)->post(route('sales.invoices.reminder', $invoice), ['stage' => 'due_soon'])->assertNotFound();
        $this->actingAs($staff)->post(route('sales.invoices.reminder', $invoice), ['stage' => 'due_soon'])->assertForbidden();
    }

    public function test_reminder_uses_the_existing_active_pdf_attachment_and_secure_link_without_changing_financial_status(): void
    {
        Queue::fake();
        Mail::fake();
        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());
        $status = $invoice->status;
        $result = app(InvoiceReminderService::class)->queueManual($invoice, $manager, InvoiceReminderStage::DueSoon, true);

        app(EmailDeliveryService::class)->send($result['delivery']);
        Mail::assertSent(CommandCenterEmail::class, function (CommandCenterEmail $mail) use ($invoice): bool {
            $this->assertCount(1, $mail->attachmentData);
            $this->assertStringStartsWith('%PDF-', $mail->attachmentData[0]['contents']);
            $this->assertNotNull($mail->actionUrl);
            $this->assertSame('INR '.number_format((float) $invoice->balance_due, 2), $mail->details['Outstanding balance']);

            return $mail->hasTo($invoice->billing_email);
        });
        $this->assertSame($status, $invoice->fresh()->status);
    }

    public function test_manual_reminder_can_send_without_a_pdf_attachment(): void
    {
        Queue::fake();
        Mail::fake();
        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());

        $delivery = app(InvoiceReminderService::class)->queueManual($invoice, $manager, InvoiceReminderStage::DueSoon, false)['delivery'];
        app(EmailDeliveryService::class)->send($delivery);

        Mail::assertSent(CommandCenterEmail::class, function (CommandCenterEmail $mail): bool {
            $this->assertCount(0, $mail->attachmentData);

            return true;
        });
    }

    public function test_permanent_reminder_failure_blocks_future_automatic_stages(): void
    {
        $manager = $this->user(UserRole::Manager);
        $settings = app(InvoiceReminderSettingsService::class)->ensure($manager->company);
        $settings->update(['automatic_enabled' => true]);
        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());
        NotificationDelivery::query()->create([
            'company_id' => $manager->company_id,
            'related_type' => $invoice->getMorphClass(),
            'related_id' => $invoice->id,
            'event_key' => 'email.invoice_reminder_due_soon',
            'template_key' => 'invoice_reminder_due_soon',
            'reminder_stage' => 'due_soon',
            'reminder_source' => 'automatic',
            'channel' => 'email',
            'recipient' => $invoice->billing_email,
            'status' => 'bounced',
            'idempotency_key' => 'bounced-reminder-'.$invoice->id,
        ]);

        $this->assertFalse(app(InvoiceReminderService::class)->automaticEligibility($invoice->fresh()->load(['company', 'creator']))['eligible']);
    }

    public function test_payment_after_an_automatic_reminder_is_queued_cancels_the_delivery_without_changing_financial_state(): void
    {
        Queue::fake();
        Mail::fake();
        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $settings = app(InvoiceReminderSettingsService::class)->ensure($manager->company);
        $settings->update(['automatic_enabled' => true]);
        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());
        $delivery = app(InvoiceReminderService::class)->queueAutomatic($invoice->fresh()->load(['company', 'creator']))['delivery'];
        $invoice->update(['amount_paid' => $invoice->grand_total, 'balance_due' => 0, 'status' => InvoiceStatus::Paid]);

        (new SendNotificationDeliveryJob($delivery->id))->handle(app(EmailDeliveryService::class), app(\App\Services\Notifications\EmailDeliveryLifecycleService::class));

        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_a_queued_automatic_reminder_is_cancelled_when_the_recipient_changes(): void
    {
        Queue::fake();
        Mail::fake();
        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $settings = app(InvoiceReminderSettingsService::class)->ensure($manager->company);
        $settings->update(['automatic_enabled' => true]);
        $invoice = $this->issuedInvoice($manager, now()->addDays(3)->toDateString());
        $delivery = app(InvoiceReminderService::class)->queueAutomatic($invoice->fresh()->load(['company', 'creator']))['delivery'];
        $invoice->update(['billing_email' => 'updated@example.test']);

        (new SendNotificationDeliveryJob($delivery->id))->handle(app(EmailDeliveryService::class), app(\App\Services\Notifications\EmailDeliveryLifecycleService::class));

        $this->assertSame('cancelled', $delivery->fresh()->status);
        Mail::assertNothingSent();
    }

    private function settingsPayload($setting, array $overrides = []): array
    {
        $rules = [];
        foreach ($setting->fresh('rules')->rules as $rule) {
            $rules[$rule->stage->value] = [
                'stage' => $rule->stage->value,
                'enabled' => true,
                'offset_days' => $rule->offset_days,
                'attach_pdf' => true,
                'include_secure_link' => true,
                'subject' => $rule->subject,
                'intro_message' => $rule->intro_message,
            ];
        }

        return ['automatic_enabled' => false, 'minimum_cooldown_hours' => 24, 'rules' => $rules, ...$overrides];
    }

    private function issuedInvoice(User $user, string $dueDate): CrmInvoice
    {
        $invoice = app(InvoiceService::class)->create($user, [
            'billing_name' => 'Asha Retail',
            'billing_email' => 'asha@example.test',
            'currency' => 'INR',
            'issue_date' => now()->toDateString(),
            'due_date' => $dueDate,
            'items' => [[
                'name' => 'RetailPOS licence',
                'quantity' => 1,
                'unit_price' => 10_000,
                'discount_amount' => 0,
                'tax_rate' => 18,
            ]],
        ]);

        return app(InvoiceService::class)->issue($invoice, $user);
    }

    private function configureEmail(User $user): void
    {
        CompanyEmailSetting::query()->create([
            'company_id' => $user->company_id,
            'is_enabled' => true,
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'from_name' => $user->company->name,
            'from_address' => 'billing@example.test',
        ]);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
