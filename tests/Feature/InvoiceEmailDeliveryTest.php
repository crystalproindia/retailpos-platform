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
use App\Models\User;
use App\Services\Crm\InvoiceEmailAttachmentService;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Notifications\EmailDeliveryService;
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
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->actingAs($manager)
            ->get(route('sales.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('was sent with its PDF attachment');

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
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('Invoice PDF attachment could not be generated.', $delivery->failure_reason);
        $this->assertNotNull($delivery->failed_at);
        $this->assertNotNull($delivery->next_retry_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->actingAs($manager)
            ->get(route('sales.invoices.show', $delivery->related_id))
            ->assertOk()
            ->assertSee('could not be delivered');
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
