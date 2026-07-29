<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\InvoiceTemplateSetting;
use App\Models\User;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_upi_uri_and_tenant_upi_id_use_trusted_outstanding_amount(): void
    {
        [$user, $invoice] = $this->invoice(['amount_paid' => 180, 'balance_due' => 1000, 'status' => InvoiceStatus::PartiallyPaid]);
        $setting = app(InvoiceTemplateService::class)->setting($user->company);

        $setting->update(['payment_qr_uri' => 'upi://pay?pa=merchant@bank&pn=Merchant&am=999999&cu=USD']);
        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $this->assertStringStartsWith('upi://pay?', $render['payment_qr_uri']);
        $this->assertStringContainsString('pa=merchant%40bank', $render['payment_qr_uri']);
        $this->assertStringContainsString('am=1000.00', $render['payment_qr_uri']);
        $this->assertStringContainsString('cu=INR', $render['payment_qr_uri']);
        $this->assertStringNotContainsString('999999', $render['payment_qr_uri']);
        $this->assertStringStartsWith('data:image/png;base64,', $render['payment_qr_data_uri']);

        $setting->update(['payment_qr_uri' => 'tenant-store@upi']);
        $render = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertStringContainsString('pa=tenant-store%40upi', $render['payment_qr_uri']);
        $this->assertStringContainsString('am=1000.00', $render['payment_qr_uri']);
    }

    public function test_valid_https_payment_and_authorized_checkout_urls_are_sanitized(): void
    {
        [$user, $invoice] = $this->invoice(['balance_due' => 1180]);
        $setting = app(InvoiceTemplateService::class)->setting($user->company);
        $setting->update(['payment_qr_uri' => 'https://pay.example.test/checkout/approved-session?reference=INV-1&amount=1']);

        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $this->assertSame('https://pay.example.test/checkout/approved-session?reference=INV-1&amount=1180.00&currency=INR', $render['payment_qr_uri']);
        $this->assertStringStartsWith('data:image/png;base64,', $render['payment_qr_data_uri']);

        $setting->update(['payment_qr_uri' => 'https://pay.example.test/checkout?token=private-token']);
        $render = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertNull($render['payment_qr_uri']);
        $this->assertNull($render['payment_qr_data_uri']);
    }

    public function test_invalid_missing_and_non_https_sources_do_not_generate_qr(): void
    {
        [$user, $invoice] = $this->invoice();
        $setting = app(InvoiceTemplateService::class)->setting($user->company);

        foreach ([null, '', 'not-a-payment-uri', 'http://pay.example.test/invoice', 'upi://pay?pa=bad', 'https://user:password@pay.example.test/checkout', 'https://127.0.0.1/pay'] as $source) {
            $setting->update(['payment_qr_uri' => $source]);
            $render = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));
            $this->assertNull($render['payment_qr_uri']);
            $this->assertNull($render['payment_qr_data_uri']);
        }
    }

    public function test_qr_visibility_tracks_unpaid_partially_paid_and_fully_paid_status(): void
    {
        [$user, $invoice] = $this->invoice();
        app(InvoiceTemplateService::class)->setting($user->company)->update(['payment_qr_uri' => 'merchant@bank']);

        $unpaid = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $this->assertNotNull($unpaid['payment_qr_data_uri']);
        $this->assertStringContainsString('am=1180.00', $unpaid['payment_qr_uri']);

        $invoice->update(['status' => InvoiceStatus::PartiallyPaid, 'amount_paid' => 400, 'balance_due' => 780]);
        $partial = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertNotNull($partial['payment_qr_data_uri']);
        $this->assertStringContainsString('am=780.00', $partial['payment_qr_uri']);

        $invoice->update(['status' => InvoiceStatus::Paid, 'amount_paid' => 1180, 'balance_due' => 0]);
        $paid = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertNull($paid['payment_qr_uri']);
        $this->assertNull($paid['payment_qr_data_uri']);
    }

    public function test_generated_pdf_contains_the_locally_embedded_qr_image(): void
    {
        [$user, $invoice] = $this->invoice();
        app(InvoiceTemplateService::class)->setting($user->company)->update(['payment_qr_uri' => 'merchant@bank']);

        $pdf = app(InvoicePdfService::class)->document($invoice)->output();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertGreaterThan(5_000, strlen($pdf));
    }

    public function test_payment_qr_configuration_is_tenant_isolated(): void
    {
        [$firstUser, $firstInvoice] = $this->invoice();
        [$secondUser, $secondInvoice] = $this->invoice();
        InvoiceTemplateSetting::query()->where('company_id', $firstUser->company_id)->update(['payment_qr_uri' => 'first-tenant@upi']);
        InvoiceTemplateSetting::query()->where('company_id', $secondUser->company_id)->update(['payment_qr_uri' => 'second-tenant@upi']);

        $first = app(InvoiceTemplateService::class)->renderData($firstInvoice->load(['company', 'items']));
        $second = app(InvoiceTemplateService::class)->renderData($secondInvoice->load(['company', 'items']));

        $this->assertStringContainsString('first-tenant%40upi', $first['payment_qr_uri']);
        $this->assertStringNotContainsString('second-tenant%40upi', $first['payment_qr_uri']);
        $this->assertStringContainsString('second-tenant%40upi', $second['payment_qr_uri']);
        $this->assertStringNotContainsString('first-tenant%40upi', $second['payment_qr_uri']);
    }

    /** @return array{User,CrmInvoice} */
    private function invoice(array $overrides = []): array
    {
        $company = Company::factory()->create(['name' => 'QR Tenant', 'legal_name' => 'QR Tenant Private Limited', 'currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $invoice = CrmInvoice::create(array_replace([
            'company_id' => $company->id,
            'invoice_number' => 'RPOS-QR-'.$company->id,
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'taxable_total' => 1000,
            'tax_total' => 180,
            'cgst_total' => 90,
            'sgst_total' => 90,
            'igst_total' => 0,
            'cess_total' => 0,
            'grand_total' => 1180,
            'amount_paid' => 0,
            'balance_due' => 1180,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
        $invoice->items()->create([
            'name' => 'QR test item',
            'hsn_sac' => '998314',
            'quantity' => 1,
            'unit' => 'service',
            'unit_price' => 1000,
            'tax_rate' => 18,
            'tax_amount' => 180,
            'cgst_amount' => 90,
            'sgst_amount' => 90,
            'igst_amount' => 0,
            'cess_amount' => 0,
            'line_subtotal' => 1000,
            'line_total' => 1180,
        ]);
        app(InvoiceTemplateService::class)->setting($company);

        return [$user, $invoice];
    }
}
