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

class InvoiceTemplateFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_save_and_reload_each_paper_format(): void
    {
        [$user] = $this->invoice();
        $templates = app(InvoiceTemplateService::class);
        $choices = [
            'a4' => 'modern_blue_corporate',
            'a5' => 'a5_compact_gst',
            'thermal_80' => 'thermal_80_gst_detailed',
            'thermal_58' => 'thermal_58_gst_compact',
        ];

        foreach ($choices as $format => $key) {
            $templates->update($user->company, $user, $this->settings($templates, $key, $format));
            $setting = $templates->setting($user->company);
            $this->assertSame($key, $setting->template_key);
            $this->assertSame($format, $setting->paper_format);
        }
    }

    public function test_incompatible_format_and_orientation_are_normalized_server_side(): void
    {
        [$user] = $this->invoice();
        $templates = app(InvoiceTemplateService::class);
        $templates->update($user->company, $user, $this->settings($templates, 'thermal_58_mini', 'a4', 'landscape'));
        $setting = $templates->setting($user->company);

        $this->assertSame('thermal_58', $setting->paper_format);
        $this->assertSame('portrait', $setting->orientation);
    }

    public function test_legacy_setting_defaults_to_a4_without_mutating_invoice_financials(): void
    {
        [$user, $invoice] = $this->invoice();
        $setting = app(InvoiceTemplateService::class)->setting($user->company);

        $this->assertSame('structured_gst_grid', $setting->template_key);
        $this->assertSame('a4', $setting->paper_format);
        $this->assertSame(1180.0, (float) $invoice->fresh()->grand_total);
        $this->assertSame(180.0, (float) $invoice->fresh()->tax_total);
    }

    public function test_unknown_legacy_template_key_uses_a_safe_a4_rendering_fallback(): void
    {
        [$user, $invoice] = $this->invoice();
        $setting = app(InvoiceTemplateService::class)->setting($user->company);
        $setting->update(['template_key' => 'retired-template', 'paper_format' => 'thermal_58', 'orientation' => 'portrait']);

        $render = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));

        $this->assertSame('structured_gst_grid', $render['setting']->template_key);
        $this->assertSame('a4', $render['setting']->paper_format);
        $this->assertSame(1180.0, (float) $invoice->fresh()->grand_total);
    }

    public function test_format_specific_views_and_print_markup_are_used_for_a5_and_thermal(): void
    {
        [$user, $invoice] = $this->invoice();
        $templates = app(InvoiceTemplateService::class);
        $pdf = app(InvoicePdfService::class);

        foreach ([['a5_modern_retail', 'a5', '148mm 210mm'], ['thermal_80_gst_detailed', 'thermal_80', '80mm auto'], ['thermal_58_mini', 'thermal_58', '58mm auto']] as [$key, $format, $pageSize]) {
            $templates->update($user->company, $user, $this->settings($templates, $key, $format));
            $render = $templates->renderData($invoice->fresh()->load(['company', 'items']));
            $markup = view($pdf->templateView($key), ['invoice' => $invoice->fresh()->load(['company', 'items']), 'render' => $render])->render();
            $this->assertStringContainsString('@page { size: '.$pageSize, $markup);
            $this->assertStringStartsWith('%PDF-', $pdf->document($invoice->fresh())->output());
            $this->assertSame(1180.0, (float) $invoice->fresh()->grand_total);
        }
    }

    public function test_every_registered_design_has_a_compatible_print_view(): void
    {
        [$user, $invoice] = $this->invoice();
        $templates = app(InvoiceTemplateService::class);
        $pdf = app(InvoicePdfService::class);

        foreach ($templates->definitions() as $key => $definition) {
            $templates->update($user->company, $user, $this->settings($templates, $key, $definition['paper_format']));
            $render = $templates->renderData($invoice->fresh()->load(['company', 'items']));
            $this->assertSame($definition['paper_format'], $render['setting']->paper_format);
            $this->assertTrue(view()->exists($pdf->templateView($key)));
            $this->assertNotEmpty(view($pdf->templateView($key), ['invoice' => $invoice->fresh()->load(['company', 'items']), 'render' => $render])->render());
        }
    }

    public function test_template_management_is_permission_and_company_scoped(): void
    {
        [$manager, $invoice] = $this->invoice();
        $staff = User::factory()->for($manager->company)->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->get(route('sales.invoices.templates.index'))->assertForbidden();
        $this->actingAs($staff)->put(route('sales.invoices.templates.update'), $this->settings(app(InvoiceTemplateService::class), 'a5_minimal', 'a5'))->assertForbidden();

        [$otherUser] = $this->invoice();
        $this->actingAs($otherUser)->get(route('sales.invoices.templates.preview', $invoice))->assertNotFound();
    }

    public function test_authorized_user_can_preview_an_in_memory_sample_invoice_without_creating_financial_records(): void
    {
        [$user] = $this->invoice();
        CrmInvoice::query()->delete();

        $this->actingAs($user)->get(route('sales.invoices.templates.index'))
            ->assertOk()
            ->assertSee('Uses a realistic sample invoice')
            ->assertSee(route('sales.invoices.templates.preview', 0), false);
        $this->actingAs($user)->get(route('sales.invoices.templates.preview', 0))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertSame(0, CrmInvoice::query()->count());
    }

    /** @return array<string,mixed> */
    private function settings(InvoiceTemplateService $templates, string $key, string $format, string $orientation = 'portrait'): array
    {
        return ['template_key' => $key, 'paper_format' => $format, 'brand_color' => '#0f766e', 'copy_label' => 'customer_copy', 'orientation' => $orientation, 'gst_presentation' => 'detailed', 'options' => $templates->defaultOptions()];
    }

    /** @return array{User,CrmInvoice} */
    private function invoice(): array
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $invoice = CrmInvoice::create(['company_id' => $company->id, 'invoice_number' => 'FORMAT-'.$company->id, 'currency' => 'INR', 'status' => InvoiceStatus::Issued, 'billing_name' => 'Asha Retail', 'billing_address' => 'MG Road, Bengaluru', 'taxable_total' => 1000, 'tax_total' => 180, 'cgst_total' => 90, 'sgst_total' => 90, 'igst_total' => 0, 'cess_total' => 0, 'grand_total' => 1180, 'amount_paid' => 0, 'balance_due' => 1180, 'created_by' => $user->id, 'updated_by' => $user->id]);
        $invoice->items()->create(['name' => 'Premium product with a long retail name', 'hsn_sac' => '998314', 'quantity' => 1, 'unit' => 'PCS', 'unit_price' => 1000, 'tax_rate' => 18, 'tax_amount' => 180, 'cgst_amount' => 90, 'sgst_amount' => 90, 'igst_amount' => 0, 'cess_amount' => 0, 'line_subtotal' => 1000, 'line_total' => 1180]);

        return [$user, $invoice];
    }
}
