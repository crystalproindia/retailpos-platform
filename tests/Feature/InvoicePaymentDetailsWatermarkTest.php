<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\InvoiceTemplateSetting;
use App\Models\User;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Crm\ProformaPdfService;
use App\Services\Crm\QuotationPdfService;
use App\Services\Crm\SalesDocumentPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InvoicePaymentDetailsWatermarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_save_update_and_reuse_account_details_without_cross_tenant_leakage(): void
    {
        $manager = $this->manager();
        $other = $this->manager();

        $this->saveSettings($manager, [
            'account_holder_name' => 'Crystal Retail Private Limited',
            'bank_name' => 'HDFC Bank',
            'account_number' => '50200012345678',
            'ifsc_code' => 'HDFC0001234',
            'bank_branch_name' => 'Bengaluru Main',
            'swift_bic' => 'HDFCINBBXXX',
            'upi_id' => 'accounts@hdfcbank',
            'payment_url' => 'https://pay.example.test/invoice',
            'payment_note' => 'Mention the invoice number with your transfer.',
        ])->assertRedirect();

        $setting = InvoiceTemplateSetting::query()->where('company_id', $manager->company_id)->firstOrFail();
        $this->assertSame('Crystal Retail Private Limited', $setting->account_holder_name);
        $this->assertSame('HDFC0001234', $setting->ifsc_code);
        $this->assertSame('HDFCINBBXXX', $setting->swift_bic);

        $this->saveSettings($manager, ['bank_name' => 'ICICI Bank', 'account_number' => '009988776655'])->assertRedirect();
        $this->assertDatabaseHas('invoice_template_settings', [
            'company_id' => $manager->company_id,
            'bank_name' => 'ICICI Bank',
        ]);
        $setting->refresh();
        $this->assertSame('009988776655', $setting->account_number);
        $this->assertNotSame('009988776655', $setting->getRawOriginal('account_number'));
        $this->assertNull(InvoiceTemplateSetting::query()->where('company_id', $other->company_id)->value('account_number'));

        $this->actingAs($other)->get(route('sales.invoices.templates.index'))
            ->assertOk()
            ->assertDontSee('009988776655');
    }

    public function test_new_documents_snapshot_payment_details_and_settings_changes_do_not_rewrite_history(): void
    {
        $manager = $this->manager();
        $this->saveSettings($manager, [
            'account_holder_name' => 'Original Account',
            'account_number' => '111122223333',
            'options' => $this->settingsOptions(['show_bank_details' => true]),
        ])->assertRedirect();

        $historical = $this->invoice($manager);
        $this->assertSame('Original Account', $historical->payment_details_snapshot['account_holder_name']);
        $this->assertNotNull($historical->presentation_snapshot_at);

        $this->saveSettings($manager, [
            'account_holder_name' => 'Replacement Account',
            'account_number' => '999900001111',
            'options' => $this->settingsOptions(['show_bank_details' => false]),
        ])->assertRedirect();

        $future = $this->invoice($manager);
        $this->assertNull($future->payment_details_snapshot);
        $historical->refresh();
        $this->assertSame('Original Account', $historical->payment_details_snapshot['account_holder_name']);

        $render = app(InvoiceTemplateService::class)->renderData($historical->load(['company', 'items']));
        $output = view('invoice-templates.structured-gst-grid', ['invoice' => $historical, 'render' => $render])->render();
        $this->assertStringContainsString('Original Account', $output);
        $this->assertStringNotContainsString('Replacement Account', $output);
    }

    public function test_visibility_controls_are_independent_for_invoice_quotation_and_proforma(): void
    {
        $manager = $this->manager();
        $this->saveSettings($manager, [
            'account_holder_name' => 'Document Account',
            'account_number' => '1234567890',
            'options' => $this->settingsOptions([
                'show_bank_details' => false,
                'show_payment_details_on_quotation' => true,
                'show_payment_details_on_proforma' => false,
            ]),
        ])->assertRedirect();

        $presentations = app(SalesDocumentPresentationService::class);
        $this->assertNull($presentations->snapshot($manager->company, SalesDocumentPresentationService::INVOICE)['payment_details_snapshot']);
        $this->assertSame('Document Account', $presentations->snapshot($manager->company, SalesDocumentPresentationService::QUOTATION)['payment_details_snapshot']['account_holder_name']);
        $this->assertNull($presentations->snapshot($manager->company, SalesDocumentPresentationService::PROFORMA)['payment_details_snapshot']);
    }

    public function test_png_jpeg_and_webp_watermarks_are_validated_and_stored_privately(): void
    {
        Storage::fake('local');
        $manager = $this->manager();

        foreach ([
            UploadedFile::fake()->image('watermark.png', 420, 240),
            UploadedFile::fake()->image('watermark.jpg', 420, 240),
            $this->webp('watermark.webp'),
        ] as $watermark) {
            $this->saveSettings($manager, ['watermark' => $watermark, 'watermark_enabled' => true])->assertRedirect();
            $setting = InvoiceTemplateSetting::query()->where('company_id', $manager->company_id)->firstOrFail();
            $this->assertStringStartsWith('companies/'.$manager->company_id.'/invoice-watermarks/', $setting->watermark_path);
            Storage::disk('local')->assertExists($setting->watermark_path);
            $this->assertTrue($setting->watermark_enabled);
        }
    }

    public function test_invalid_watermark_is_rejected_without_changing_saved_asset(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $this->saveSettings($manager, ['watermark' => UploadedFile::fake()->image('valid.png'), 'watermark_enabled' => true])->assertRedirect();
        $savedPath = InvoiceTemplateSetting::query()->where('company_id', $manager->company_id)->value('watermark_path');

        $this->saveSettings($manager, [
            'watermark' => UploadedFile::fake()->createWithContent('watermark.png', '<?php echo "unsafe";'),
            'watermark_enabled' => true,
        ])->assertSessionHasErrors('watermark');

        $this->assertSame($savedPath, InvoiceTemplateSetting::query()->where('company_id', $manager->company_id)->value('watermark_path'));
        Storage::disk('local')->assertExists($savedPath);
    }

    public function test_uploading_watermark_does_not_enable_it_until_tenant_turns_it_on(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $this->saveSettings($manager, [
            'watermark' => UploadedFile::fake()->image('available.png'),
            'watermark_enabled' => false,
        ])->assertRedirect();

        $setting = InvoiceTemplateSetting::query()->where('company_id', $manager->company_id)->firstOrFail();
        $this->assertNotNull($setting->watermark_path);
        $this->assertFalse($setting->watermark_enabled);
        $disabledInvoice = $this->invoice($manager);
        $this->assertNull($disabledInvoice->watermark_path_snapshot);

        $this->saveSettings($manager, ['watermark_enabled' => true])->assertRedirect();
        $enabledInvoice = $this->invoice($manager);
        $this->assertSame($setting->watermark_path, $enabledInvoice->watermark_path_snapshot);
    }

    public function test_watermark_replace_and_remove_preserve_historical_snapshot_files(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $this->saveSettings($manager, ['watermark' => UploadedFile::fake()->image('first.png'), 'watermark_enabled' => true])->assertRedirect();
        $firstPath = InvoiceTemplateSetting::query()->where('company_id', $manager->company_id)->value('watermark_path');
        $invoice = $this->invoice($manager);
        $this->assertSame($firstPath, $invoice->watermark_path_snapshot);

        $this->saveSettings($manager, ['watermark' => UploadedFile::fake()->image('second.jpg'), 'watermark_enabled' => true])->assertRedirect();
        $secondPath = InvoiceTemplateSetting::query()->where('company_id', $manager->company_id)->value('watermark_path');
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertExists($secondPath);

        $this->saveSettings($manager, ['remove_watermark' => true, 'watermark_enabled' => false])->assertRedirect();
        $this->assertNull(InvoiceTemplateSetting::query()->where('company_id', $manager->company_id)->value('watermark_path'));
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertMissing($secondPath);
    }

    public function test_watermark_is_tenant_isolated_and_never_exposes_private_storage_paths(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $other = $this->manager();
        $this->saveSettings($manager, ['watermark' => UploadedFile::fake()->image('private.png'), 'watermark_enabled' => true])->assertRedirect();
        $path = InvoiceTemplateSetting::query()->where('company_id', $manager->company_id)->value('watermark_path');

        $this->actingAs($other)->get(route('sales.invoices.templates.index'))
            ->assertOk()
            ->assertDontSee($path)
            ->assertDontSee(base64_encode(Storage::disk('local')->get($path)));
        $this->assertStringStartsWith('companies/'.$manager->company_id.'/', $path);
    }

    public function test_watermark_and_payment_details_have_browser_preview_and_pdf_parity(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $this->saveSettings($manager, [
            'account_holder_name' => 'PDF Account Holder',
            'upi_id' => 'billing@upi',
            'watermark' => UploadedFile::fake()->image('watermark.png', 600, 320),
            'watermark_enabled' => true,
            'options' => $this->settingsOptions(['show_bank_details' => true]),
        ])->assertRedirect();
        $invoice = $this->invoice($manager);
        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $output = view('invoice-templates.structured-gst-grid', ['invoice' => $invoice, 'render' => $render])->render();

        $this->assertStringContainsString('invoice-watermark', $output);
        $this->assertStringContainsString('opacity: .12', $output);
        $this->assertStringContainsString('left: 15%', $output);
        $this->assertStringContainsString('top: 31%', $output);
        $this->assertStringContainsString('PDF Account Holder', $output);
        $this->assertStringContainsString('billing@upi', $output);

        $pdf = app(InvoicePdfService::class)->document($invoice)->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);

        $publicMarkup = view('public.invoice', ['invoice' => $invoice->loadMissing('payments'), 'token' => 'safe-token', 'render' => $render])->render();
        $this->assertStringContainsString('opacity-[0.12]', $publicMarkup);
        $this->assertStringContainsString('PDF Account Holder', $publicMarkup);
    }

    public function test_watermark_repeats_on_long_a4_pdf_and_remains_bounded(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $this->saveSettings($manager, [
            'watermark' => UploadedFile::fake()->image('watermark.png', 600, 320),
            'watermark_enabled' => true,
        ])->assertRedirect();
        $invoice = $this->invoice($manager);
        for ($index = 0; $index < 70; $index++) {
            $invoice->items()->create([
                'name' => 'Long catalogue product '.$index.' '.str_repeat('descriptive ', 5),
                'quantity' => 1,
                'unit_price' => 10,
                'line_subtotal' => 10,
                'line_total' => 10,
                'sort_order' => $index + 2,
            ]);
        }

        $pdf = app(InvoicePdfService::class)->document($invoice->fresh()->load(['company', 'items']))->output();
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf));
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertLessThan(5_000_000, strlen($pdf));
    }

    public function test_thermal_58_omits_watermark_and_uses_compact_payment_details_while_thermal_80_keeps_watermark(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $this->saveSettings($manager, [
            'account_holder_name' => 'Thermal Account',
            'account_number' => '1234567890',
            'upi_id' => 'thermal@upi',
            'watermark' => UploadedFile::fake()->image('watermark.png'),
            'watermark_enabled' => true,
            'options' => $this->settingsOptions(['show_bank_details' => true]),
        ])->assertRedirect();
        $invoice = $this->invoice($manager)->load(['company', 'items']);
        $templates = app(InvoiceTemplateService::class);

        $render58 = $templates->renderData($invoice, ['template_key' => 'thermal_58_mini', 'paper_format' => 'thermal_58']);
        $output58 = view('invoice-templates.layouts.thermal', ['invoice' => $invoice, 'render' => $render58])->render();
        $this->assertFalse($render58['watermark']['enabled']);
        $this->assertStringNotContainsString('<div class="invoice-watermark"', $output58);
        $this->assertStringContainsString('UPI: thermal@upi', $output58);
        $this->assertStringNotContainsString('Thermal Account', $output58);

        $render80 = $templates->renderData($invoice, ['template_key' => 'thermal_80_classic', 'paper_format' => 'thermal_80']);
        $this->assertTrue($render80['watermark']['enabled']);
        $this->assertStringContainsString('invoice-watermark', view('invoice-templates.layouts.thermal', ['invoice' => $invoice, 'render' => $render80])->render());
    }

    public function test_quotation_and_proforma_pdf_outputs_use_their_immutable_snapshots(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $this->saveSettings($manager, [
            'account_holder_name' => 'Sales Document Account',
            'watermark' => UploadedFile::fake()->image('watermark.png'),
            'watermark_enabled' => true,
            'options' => $this->settingsOptions([
                'show_payment_details_on_quotation' => true,
                'show_payment_details_on_proforma' => true,
            ]),
        ])->assertRedirect();
        $presentations = app(SalesDocumentPresentationService::class);

        $status = CrmLeadStatus::create([
            'company_id' => $manager->company_id,
            'name' => 'New',
            'slug' => 'new',
            'stage_type' => LeadStageType::New,
            'is_active' => true,
        ]);
        $lead = CrmLead::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'status_id' => $status->id,
            'title' => 'Sales document lead',
            'priority' => LeadPriority::Medium,
            'created_by' => $manager->id,
        ]);
        $quotation = CrmQuotation::create($presentations->snapshot($manager->company, SalesDocumentPresentationService::QUOTATION) + [
            'company_id' => $manager->company_id,
            'lead_id' => $lead->id,
            'quotation_number' => 'Q-WATERMARK-001',
            'title' => 'Watermarked proposal',
            'currency' => 'INR',
            'status' => 'draft',
            'grand_total' => 100,
            'created_by' => $manager->id,
        ]);
        $quotation->items()->create(['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $proforma = CrmProformaInvoice::create($presentations->snapshot($manager->company, SalesDocumentPresentationService::PROFORMA) + [
            'company_id' => $manager->company_id,
            'proforma_number' => 'PI-WATERMARK-001',
            'title' => 'Watermarked proforma',
            'currency' => 'INR',
            'invoice_date' => today(),
            'grand_total' => 100,
            'balance_amount' => 100,
            'created_by' => $manager->id,
        ]);
        $proforma->items()->create(['name' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);

        $quotationPdf = app(QuotationPdfService::class)->document($quotation->load(['company', 'items']))->output();
        $proformaPdf = app(ProformaPdfService::class)->document($proforma->load(['company', 'items']))->output();
        $this->assertStringStartsWith('%PDF-', $quotationPdf);
        $this->assertStringStartsWith('%PDF-', $proformaPdf);
        $this->assertStringContainsString('/Subtype /Image', $quotationPdf);
        $this->assertStringContainsString('/Subtype /Image', $proformaPdf);
    }

    public function test_historical_documents_without_new_snapshots_remain_unchanged(): void
    {
        $manager = $this->manager();
        $this->saveSettings($manager, ['account_holder_name' => 'Live Account'])->assertRedirect();
        $invoice = CrmInvoice::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'invoice_number' => 'HISTORICAL-001',
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'grand_total' => 10,
            'balance_due' => 10,
        ]);
        $invoice->items()->create(['name' => 'Historical item', 'quantity' => 1, 'unit_price' => 10, 'line_subtotal' => 10, 'line_total' => 10]);

        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $this->assertNull($render['payment_details']);
        $this->assertFalse($render['watermark']['enabled']);
    }

    public function test_settings_ui_is_responsive_dark_mode_ready_and_requires_existing_permission(): void
    {
        $manager = $this->manager();
        $staff = User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Staff]);

        $this->actingAs($manager)->get(route('sales.invoices.templates.index'))
            ->assertOk()
            ->assertSee('Account and payment details')
            ->assertSee('Invoice watermark')
            ->assertSee('dark:bg-slate-900', false)
            ->assertSee('md:grid-cols-2', false)
            ->assertSee('sm:grid-cols-3', false);
        $this->actingAs($staff)->get(route('sales.invoices.templates.index'))->assertForbidden();
    }

    private function saveSettings(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)->put(route('sales.invoices.templates.update'), array_replace_recursive([
            'template_key' => 'structured_gst_grid',
            'paper_format' => 'a4',
            'brand_color' => '#0f766e',
            'copy_label' => 'original',
            'orientation' => 'portrait',
            'gst_presentation' => 'detailed',
            'watermark_enabled' => false,
            'remove_watermark' => false,
            'options' => $this->settingsOptions(),
        ], $overrides));
    }

    /** @param array<string, bool> $overrides */
    private function settingsOptions(array $overrides = []): array
    {
        return array_replace(app(InvoiceTemplateService::class)->defaultOptions(), $overrides);
    }

    private function invoice(User $user): CrmInvoice
    {
        return app(InvoiceService::class)->create($user, [
            'billing_name' => 'Asha Sharma',
            'billing_company' => 'Asha Retail',
            'billing_email' => 'asha@example.test',
            'currency' => 'INR',
            'items' => [[
                'name' => 'RetailPOS service',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_value' => 0,
                'tax_rate' => 0,
            ]],
        ]);
    }

    private function manager(): User
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }

    private function webp(string $name): UploadedFile
    {
        $contents = base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADMDOJaQAA3AA/v89WAAAAA==', true);

        return UploadedFile::fake()->createWithContent($name, $contents ?: '');
    }
}
