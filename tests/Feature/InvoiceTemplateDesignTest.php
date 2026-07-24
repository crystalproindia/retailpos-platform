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
        foreach (InvoiceTemplateService::KEYS as $key) {
            $service->update($user->company, $user, ['template_key' => $key, 'brand_color' => '#0f766e', 'copy_label' => 'original', 'orientation' => 'portrait', 'options' => $service->defaultOptions()]);
            $render = $service->renderData($invoice->fresh()->load(['company', 'items']));
            $this->assertSame($key, $render['setting']->template_key);
            $this->assertSame(9.0, $render['tax_rows'][0]['cgst_rate']);
            $this->assertSame(9.0, $render['tax_rows'][0]['sgst_rate']);
            $this->assertEquals(0.0, $render['tax_rows'][0]['igst_rate']);
            $this->assertNotEmpty(app(InvoicePdfService::class)->document($invoice->fresh())->output());
        }
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
