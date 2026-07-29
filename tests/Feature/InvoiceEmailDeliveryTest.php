<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\Notifications\SendNotificationDeliveryJob;
use App\Mail\CommandCenterEmail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyEmailSetting;
use App\Models\Crm\CrmInvoice;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryEvent;
use App\Models\User;
use App\Services\Crm\InvoiceEmailAttachmentService;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\EmailDeliveryLifecycleService;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class InvoiceEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_invoice_design_generates_a_transient_pdf_attachment(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->issuedInvoice($manager);
        $delivery = $this->invoiceDelivery($invoice, $manager);
        $templates = app(InvoiceTemplateService::class);
        $attachments = app(InvoiceEmailAttachmentService::class);

        foreach (InvoiceTemplateService::KEYS as $templateKey) {
            $this->saveTemplate($templates, $manager, $templateKey);
            $attachment = $attachments->forDelivery($delivery->fresh());

            $this->assertNotNull($attachment);
            $this->assertSame('application/pdf', $attachment['mime']);
            $this->assertSame(app(InvoicePdfService::class)->filename($invoice), $attachment['filename']);
            $this->assertStringStartsWith('%PDF-', $attachment['contents']);
            $this->assertGreaterThan(1_000, strlen($attachment['contents']));
        }
    }

    public function test_invoice_email_queues_the_selected_design_as_an_attachment_and_keeps_the_secure_link(): void
    {
        Queue::fake();
        Mail::fake();
        Storage::fake('local');

        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $invoice = $this->issuedInvoice($manager);
        $this->saveTemplate(app(InvoiceTemplateService::class), $manager, 'executive_corporate_gst');

        $this->actingAs($manager)
            ->post(route('sales.invoices.send', $invoice), ['email' => 'asha@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Invoice email with PDF attachment queued.');

        Queue::assertPushed(SendNotificationDeliveryJob::class);

        $delivery = NotificationDelivery::query()->sole();
        $this->assertSame('queued', $delivery->status);
        $this->assertSame($manager->company_id, $delivery->company_id);
        $this->assertSame($invoice->getMorphClass(), $delivery->related_type);
        $this->assertSame($invoice->id, $delivery->related_id);
        $this->assertSame(InvoiceEmailAttachmentService::TYPE, $delivery->payload['attachment_type']);
        $this->assertLessThanOrEqual(96, strlen($delivery->idempotency_key));
        $this->assertStringContainsString('/i/', $delivery->payload['action_url']);
        $this->actingAs($manager)
            ->get(route('sales.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('queued for delivery with its PDF attachment');

        app(EmailDeliveryService::class)->send($delivery);

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertNull($delivery->delivered_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->actingAs($manager)
            ->get(route('sales.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('SMTP accepted the invoice email with its PDF attachment');

        Mail::assertSent(CommandCenterEmail::class, function (CommandCenterEmail $mail) use ($delivery, $invoice): bool {
            $businessName = $invoice->company->trade_name ?: $invoice->company->legal_name ?: $invoice->company->name;
            $this->assertSame('Your invoice from '.$businessName, $mail->heading);
            $this->assertSame($delivery->payload['action_url'], $mail->actionUrl);
            $this->assertStringContainsString('PDF invoice is attached', $mail->messageText);
            $this->assertCount(1, $mail->attachmentData);
            $this->assertSame(app(InvoicePdfService::class)->filename($invoice), $mail->attachmentData[0]['filename']);
            $this->assertSame('application/pdf', $mail->attachmentData[0]['mime']);
            $this->assertStringStartsWith('%PDF-', $mail->attachmentData[0]['contents']);

            return $mail->hasTo('asha@example.test');
        });
    }

    public function test_cross_tenant_and_unauthorised_users_cannot_send_invoice_email(): void
    {
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->issuedInvoice($manager);
        $otherManager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);

        $this->actingAs($otherManager)
            ->post(route('sales.invoices.send', $invoice), ['email' => 'asha@example.test'])
            ->assertNotFound();

        $this->actingAs($staff)
            ->post(route('sales.invoices.send', $invoice), ['email' => 'asha@example.test'])
            ->assertForbidden();
    }

    public function test_attachment_generation_failure_is_safely_tracked_without_persisting_a_pdf(): void
    {
        Storage::fake('local');
        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $delivery = $this->invoiceDelivery($this->issuedInvoice($manager), $manager);

        $this->mock(InvoiceEmailAttachmentService::class, function ($mock): void {
            $mock->shouldReceive('forDelivery')->once()->andThrow(new RuntimeException('render failure'));
        });
        $this->app->forgetInstance(EmailDeliveryService::class);

        try {
            app(EmailDeliveryService::class)->send($delivery);
            $this->fail('Expected invoice attachment generation to fail.');
        } catch (RuntimeException) {
            // The queued job will retry this transient failure using its existing backoff policy.
        }

        $delivery->refresh();
        $this->assertSame('temporarily_failed', $delivery->status);
        $this->assertSame('Invoice PDF attachment could not be generated.', $delivery->failure_reason);
        $this->assertNotNull($delivery->failed_at);
        $this->assertNotNull($delivery->next_retry_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->actingAs($manager)
            ->get(route('sales.invoices.show', $delivery->related_id))
            ->assertOk()
            ->assertSee('The latest invoice email could not be delivered');
    }

    public function test_sent_invoice_email_requires_a_signed_provider_event_before_it_is_marked_delivered(): void
    {
        Mail::fake();
        $manager = $this->user(UserRole::Manager);
        $this->configureEmail($manager);
        $delivery = $this->invoiceDelivery($this->issuedInvoice($manager), $manager);

        app(EmailDeliveryService::class)->send($delivery);
        $delivery->refresh()->update(['provider' => 'generic', 'provider_message_id' => 'invoice-message-100']);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->delivered_at);

        config(['email-delivery.webhook.enabled' => true, 'email-delivery.webhook.secret' => 'webhook-secret']);
        $payload = ['company_id' => $manager->company_id, 'event_id' => 'delivery-event-100', 'event_type' => 'delivered', 'provider_message_id' => 'invoice-message-100', 'timestamp' => now()->toIso8601String()];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'webhook-secret');

        $this->call('POST', '/api/email-delivery/generic/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RETAILPOS_EMAIL_SIGNATURE' => $signature], $body)
            ->assertOk()->assertJson(['accepted' => true, 'duplicate' => false]);
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->delivered_at);
        $this->assertSame(1, NotificationDeliveryEvent::query()->where('provider_event_id', 'delivery-event-100')->count());

        $this->call('POST', '/api/email-delivery/generic/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RETAILPOS_EMAIL_SIGNATURE' => $signature], $body)
            ->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(1, NotificationDeliveryEvent::query()->where('provider_event_id', 'delivery-event-100')->count());
    }

    public function test_unsigned_expired_and_cross_company_provider_events_are_rejected(): void
    {
        $manager = $this->user(UserRole::Manager);
        $other = $this->user(UserRole::Manager);
        $delivery = $this->invoiceDelivery($this->issuedInvoice($manager), $manager);
        $delivery->update(['status' => 'sent', 'provider' => 'generic', 'provider_message_id' => 'invoice-message-200']);
        config(['email-delivery.webhook.enabled' => true, 'email-delivery.webhook.secret' => 'webhook-secret']);

        $this->postJson('/api/email-delivery/generic/webhook', ['company_id' => $manager->company_id, 'event_id' => 'unsigned-event', 'event_type' => 'delivered', 'provider_message_id' => 'invoice-message-200', 'timestamp' => now()->toIso8601String()])->assertUnauthorized();

        $expired = ['company_id' => $other->company_id, 'event_id' => 'cross-company-event', 'event_type' => 'delivered', 'provider_message_id' => 'invoice-message-200', 'timestamp' => now()->subMinutes(10)->toIso8601String()];
        $body = json_encode($expired, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/email-delivery/generic/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RETAILPOS_EMAIL_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, 'webhook-secret')], $body)
            ->assertUnprocessable();
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_manual_resend_is_tenant_scoped_and_does_not_change_invoice_financial_status(): void
    {
        Queue::fake();
        $manager = $this->user(UserRole::Manager);
        $invoice = $this->issuedInvoice($manager);
        $delivery = $this->invoiceDelivery($invoice, $manager);
        $delivery->update(['status' => 'permanently_failed', 'failure_reason' => 'Email transport could not complete delivery.', 'failed_at' => now()]);

        $this->actingAs($manager)
            ->post(route('sales.invoices.email-deliveries.resend', [$invoice, $delivery]))
            ->assertRedirect()->assertSessionHas('status', 'Invoice email queued for resend with its PDF attachment.');
        $resent = NotificationDelivery::query()->where('id', '!=', $delivery->id)->sole();
        $this->assertSame('queued', $resent->status);
        $this->assertSame($invoice->id, $resent->related_id);
        $this->assertSame($invoice->status, $invoice->fresh()->status);
        Queue::assertPushed(SendNotificationDeliveryJob::class, fn (SendNotificationDeliveryJob $job): bool => $job->deliveryId === $resent->id);

        $other = $this->user(UserRole::Manager);
        $this->actingAs($other)->post(route('sales.invoices.email-deliveries.resend', [$invoice, $delivery]))->assertNotFound();
    }

    public function test_email_lifecycle_blocks_backward_delivery_transitions(): void
    {
        $manager = $this->user(UserRole::Manager);
        $delivery = $this->invoiceDelivery($this->issuedInvoice($manager), $manager);
        $lifecycle = app(EmailDeliveryLifecycleService::class);
        $processing = $lifecycle->transition($delivery, \App\Enums\Notifications\EmailDeliveryStatus::Processing, 'test.processing');
        $sent = $lifecycle->transition($processing, \App\Enums\Notifications\EmailDeliveryStatus::Sent, 'test.sent');

        $this->expectException(ValidationException::class);
        $lifecycle->transition($sent, \App\Enums\Notifications\EmailDeliveryStatus::Queued, 'test.invalid');
    }

    public function test_invalid_recipient_is_rejected_without_a_retry(): void
    {
        $manager = $this->user(UserRole::Manager);
        $delivery = $this->invoiceDelivery($this->issuedInvoice($manager), $manager);
        $delivery->update(['recipient' => 'not-an-email']);

        (new SendNotificationDeliveryJob($delivery->id))->handle(app(EmailDeliveryService::class), app(EmailDeliveryLifecycleService::class));

        $this->assertSame('rejected', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->next_retry_at);
    }

    public function test_due_temporary_failure_is_claimed_only_once_by_the_retry_scheduler(): void
    {
        Queue::fake();
        $manager = $this->user(UserRole::Manager);
        $delivery = $this->invoiceDelivery($this->issuedInvoice($manager), $manager);
        $delivery->update(['status' => 'temporarily_failed', 'attempt_count' => 1, 'next_retry_at' => now()->subMinute()]);

        $this->artisan('notifications:retry-failed-deliveries')->assertSuccessful();
        $this->artisan('notifications:retry-failed-deliveries')->assertSuccessful();

        Queue::assertPushed(SendNotificationDeliveryJob::class, 1);
        $this->assertTrue($delivery->fresh()->next_retry_at->isFuture());
    }

    public function test_provider_event_endpoint_is_disabled_by_default(): void
    {
        config(['email-delivery.webhook.enabled' => false]);

        $this->postJson('/api/email-delivery/generic/webhook', [])->assertNotFound();
    }

    private function issuedInvoice(User $user): CrmInvoice
    {
        $invoice = app(InvoiceService::class)->create($user, [
            'billing_name' => 'Asha Retail',
            'billing_email' => 'asha@example.test',
            'currency' => 'INR',
            'items' => [[
                'name' => 'RetailPOS licence',
                'quantity' => 1,
                'unit_price' => 10_000,
                'discount_amount' => 500,
                'tax_rate' => 18,
            ]],
        ]);

        return app(InvoiceService::class)->issue($invoice, $user);
    }

    private function invoiceDelivery(CrmInvoice $invoice, User $user): NotificationDelivery
    {
        return NotificationDelivery::query()->create([
            'company_id' => $invoice->company_id,
            'created_by' => $user->id,
            'related_type' => $invoice->getMorphClass(),
            'related_id' => $invoice->id,
            'event_key' => 'email.invoice_issued',
            'template_key' => 'invoice_issued',
            'channel' => 'email',
            'recipient' => 'asha@example.test',
            'subject' => 'RetailPOS Invoice - '.$invoice->invoice_number,
            'status' => 'queued',
            'idempotency_key' => 'invoice-pdf-'.$invoice->id,
            'payload' => ['attachment_type' => InvoiceEmailAttachmentService::TYPE],
            'queued_at' => now(),
        ]);
    }

    private function saveTemplate(InvoiceTemplateService $templates, User $user, string $templateKey): void
    {
        $templates->update($user->company, $user, [
            'template_key' => $templateKey,
            'brand_color' => '#0f766e',
            'copy_label' => 'original',
            'orientation' => 'portrait',
            'options' => $templates->defaultOptions(),
        ]);
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

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
    }
}
