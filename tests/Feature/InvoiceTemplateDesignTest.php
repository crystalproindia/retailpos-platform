<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\User;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTemplateDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_five_templates_render_authoritative_gst_snapshots(): void
    {
        [$user, $invoice] = $this->invoice();
        $service = app(InvoiceTemplateService::class);
        $pdf = app(InvoicePdfService::class);
        $renderedViews = [];
        foreach (InvoiceTemplateService::KEYS as $key) {
            $service->update($user->company, $user, ['template_key' => $key, 'brand_color' => '#0f766e', 'copy_label' => 'original', 'orientation' => 'portrait', 'options' => $service->defaultOptions()]);
            $render = $service->renderData($invoice->fresh()->load(['company', 'items']));
            $this->assertSame($key, $render['setting']->template_key);
            $this->assertSame(9.0, $render['tax_rows'][0]['cgst_rate']);
            $this->assertSame(9.0, $render['tax_rows'][0]['sgst_rate']);
            $this->assertEquals(0.0, $render['tax_rows'][0]['igst_rate']);
            $view = $pdf->templateView($key);
            $this->assertTrue(view()->exists($view));
            $markup = view($view, ['invoice' => $invoice->fresh()->load(['company', 'items']), 'render' => $render])->render();
            $this->assertStringContainsString('GST rate-wise summary', $markup);
            $renderedViews[] = hash('sha256', $markup);
            $this->assertNotEmpty($pdf->document($invoice->fresh())->output());
        }
        $this->assertCount(5, array_unique($renderedViews));
    }

    public function test_interstate_tax_uses_igst_and_required_gst_options_cannot_be_disabled(): void
    {
        [$user, $invoice] = $this->invoice(['cgst_amount' => 0, 'sgst_amount' => 0, 'igst_amount' => 180, 'cgst_total' => 0, 'sgst_total' => 0, 'igst_total' => 180]);
        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $this->assertSame(18.0, $render['tax_rows'][0]['igst_rate']);
        $this->actingAs($user)->put(route('sales.invoices.templates.update'), ['template_key' => 'premium_elegant', 'brand_color' => '#123456', 'copy_label' => 'original', 'orientation' => 'portrait', 'options' => ['show_gst_breakup' => 0, 'show_gst_summary' => 0, 'show_hsn_sac' => 0]])->assertRedirect();
        $options = app(InvoiceTemplateService::class)->setting($user->company)->options;
        $this->assertTrue($options['show_gst_breakup']); $this->assertTrue($options['show_gst_summary']); $this->assertTrue($options['show_hsn_sac']);
    }

    public function test_payment_qr_embeds_a_trusted_upi_amount_for_unpaid_and_partially_paid_invoices(): void
    {
        [$user, $invoice] = $this->invoice();
        $this->configurePaymentSource($user, 'merchant@upi');

        $render = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertStringContainsString('pa=merchant%40upi', $render['payment_qr_uri']);
        $this->assertStringContainsString('am=1180.00', $render['payment_qr_uri']);
        $this->assertStringStartsWith('data:image/png;base64,', $render['payment_qr_data_uri']);
        $this->assertStringContainsString('/Subtype /Image', app(InvoicePdfService::class)->document($invoice->fresh())->output());

        $invoice->update(['status' => InvoiceStatus::PartiallyPaid, 'amount_paid' => 180, 'balance_due' => 1000]);
        $partialRender = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertStringContainsString('am=1000.00', $partialRender['payment_qr_uri']);
        $this->assertNotNull($partialRender['payment_qr_data_uri']);
    }

    public function test_payment_qr_rejects_missing_invalid_and_paid_invoice_sources(): void
    {
        [$user, $invoice] = $this->invoice();
        $templates = app(InvoiceTemplateService::class);

        $this->assertNull($templates->renderData($invoice->load(['company', 'items']))['payment_qr_data_uri']);
        $this->configurePaymentSource($user, 'javascript:alert(1)');
        $this->assertNull($templates->renderData($invoice->fresh()->load(['company', 'items']))['payment_qr_data_uri']);

        $this->configurePaymentSource($user, 'upi://pay?pa=merchant@upi');
        $invoice->update(['status' => InvoiceStatus::Paid, 'amount_paid' => 1180, 'balance_due' => 0]);
        $this->assertNull($templates->renderData($invoice->fresh()->load(['company', 'items']))['payment_qr_data_uri']);
    }

    public function test_payment_qr_accepts_sanitized_https_checkouts_and_is_tenant_scoped(): void
    {
        [$user, $invoice] = $this->invoice();
        $this->configurePaymentSource($user, 'https://checkout.example.test/sessions/session-1');
        $first = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertStringContainsString('https://checkout.example.test/sessions/session-1?', $first['payment_qr_uri']);
        $this->assertStringContainsString('amount=1180.00', $first['payment_qr_uri']);

        [$otherUser, $otherInvoice] = $this->invoice();
        $this->configurePaymentSource($otherUser, 'othermerchant@upi');
        $second = app(InvoiceTemplateService::class)->renderData($otherInvoice->fresh()->load(['company', 'items']));
        $this->assertStringContainsString('pa=othermerchant%40upi', $second['payment_qr_uri']);
        $this->assertStringNotContainsString('othermerchant%40upi', $first['payment_qr_uri']);
    }

    private function configurePaymentSource(User $user, ?string $paymentSource): void
    {
        $templates = app(InvoiceTemplateService::class);
        $templates->update($user->company, $user, [
            'template_key' => 'structured_gst_grid',
            'brand_color' => '#0f766e',
            'copy_label' => 'original',
            'orientation' => 'portrait',
            'payment_qr_uri' => $paymentSource,
            'options' => $templates->defaultOptions(),
        ]);
    }

    /** @return array{User,CrmInvoice} */
    private function invoice(array $overrides = []): array
    {
        $company = Company::factory()->create(['currency' => 'INR']); $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $invoice = CrmInvoice::create(array_replace(['company_id' => $company->id, 'invoice_number' => 'RPOS-INV-SAMPLE', 'currency' => 'INR', 'status' => InvoiceStatus::Issued, 'taxable_total' => 1000, 'tax_total' => 180, 'cgst_total' => 90, 'sgst_total' => 90, 'igst_total' => 0, 'cess_total' => 0, 'grand_total' => 1180, 'amount_paid' => 0, 'balance_due' => 1180, 'created_by' => $user->id, 'updated_by' => $user->id], $overrides));
        $invoice->items()->create(['name' => 'Sample service', 'hsn_sac' => '998314', 'quantity' => 1, 'unit' => 'service', 'unit_price' => 1000, 'tax_rate' => 18, 'tax_amount' => 180, 'cgst_amount' => $overrides['cgst_amount'] ?? 90, 'sgst_amount' => $overrides['sgst_amount'] ?? 90, 'igst_amount' => $overrides['igst_amount'] ?? 0, 'cess_amount' => 0, 'line_subtotal' => 1000, 'line_total' => 1180]);
        return [$user, $invoice];
    }
}
