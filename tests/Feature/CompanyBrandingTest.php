<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Pos\PosSale;
use App\Models\User;
use App\Services\Branding\CompanyBrandingService;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Pos\PosReceiptPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_uploads_replaces_and_removes_private_company_branding(): void
    {
        Storage::fake('local');
        $manager = $this->manager();

        $this->actingAs($manager)->put(route('settings.company-profile.update'), $this->profilePayload($manager, [
            'company_logo' => UploadedFile::fake()->image('primary.png', 320, 120),
            'invoice_logo' => UploadedFile::fake()->image('invoice.jpg', 320, 120),
        ]))->assertRedirect();

        $company = $manager->company->refresh();
        $primaryPath = $company->company_logo_path;
        $invoicePath = $company->invoice_logo_path;
        $this->assertNotNull($primaryPath);
        $this->assertNotNull($invoicePath);
        $this->assertStringStartsWith('companies/'.$company->id.'/branding/', $primaryPath);
        Storage::disk('local')->assertExists($primaryPath);
        Storage::disk('local')->assertExists($invoicePath);
        $this->assertStringStartsWith('data:image/', app(CompanyBrandingService::class)->forCompany($company)['active_logo']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'company.branding.company.uploaded']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'company.branding.invoice.uploaded']);

        $this->actingAs($manager)->put(route('settings.company-profile.update'), $this->profilePayload($manager, [
            'company_logo' => UploadedFile::fake()->image('new-primary.webp', 300, 100),
        ]))->assertRedirect();

        $company->refresh();
        $this->assertNotSame($primaryPath, $company->company_logo_path);
        Storage::disk('local')->assertMissing($primaryPath);
        Storage::disk('local')->assertExists($company->company_logo_path);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'company.branding.company.replaced']);

        $this->actingAs($manager)->put(route('settings.company-profile.update'), $this->profilePayload($manager, [
            'remove_invoice_logo' => true,
        ]))->assertRedirect();

        $company->refresh();
        $this->assertNull($company->invoice_logo_path);
        Storage::disk('local')->assertMissing($invoicePath);
        $this->assertSame('company', app(CompanyBrandingService::class)->forCompany($company)['source']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'company.branding.invoice.removed']);
        $this->get('/storage/'.$company->company_logo_path)->assertForbidden();
    }

    public function test_branding_validation_permissions_and_tenant_isolation_are_enforced(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $other = $this->manager();
        $staff = User::factory()->for($manager->company)->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)->put(route('settings.company-profile.update'), $this->profilePayload($manager))->assertForbidden();
        $this->actingAs($manager)->put(route('settings.company-profile.update'), $this->profilePayload($manager, [
            'company_logo' => UploadedFile::fake()->create('malware.jpg', 8, 'application/x-php'),
        ]))->assertSessionHasErrors('company_logo');
        $this->actingAs($manager)->put(route('settings.company-profile.update'), $this->profilePayload($manager, [
            'company_logo' => UploadedFile::fake()->create('too-large.png', 2049, 'image/png'),
        ]))->assertSessionHasErrors('company_logo');
        $this->assertNull($manager->company->refresh()->company_logo_path);

        $this->actingAs($manager)->put(route('settings.company-profile.update'), $this->profilePayload($manager, [
            'company_logo' => UploadedFile::fake()->image('tenant-a.png', 240, 80),
        ]))->assertRedirect();

        $pathBeforeFailedReplacement = $manager->company->refresh()->company_logo_path;
        $this->actingAs($manager)->put(route('settings.company-profile.update'), $this->profilePayload($manager, [
            'company_logo' => UploadedFile::fake()->create('not-an-image.png', 8, 'text/plain'),
        ]))->assertSessionHasErrors('company_logo');
        $this->assertSame($pathBeforeFailedReplacement, $manager->company->refresh()->company_logo_path);
        Storage::disk('local')->assertExists($pathBeforeFailedReplacement);
        $this->assertNull($other->company->refresh()->company_logo_path);
        $this->assertSame(0, AuditLog::query()->where('company_id', $other->company_id)->where('event', 'like', 'company.branding.%')->count());
    }

    public function test_logo_controls_are_tenant_scoped_and_render_on_all_invoice_and_receipt_outputs(): void
    {
        Storage::fake('local');
        [$manager, $invoice, $sale] = $this->documents();
        app(CompanyBrandingService::class)->replace($manager->company, $manager, UploadedFile::fake()->image('brand.png', 360, 120), 'company');

        $templates = app(InvoiceTemplateService::class);
        foreach (InvoiceTemplateService::KEYS as $key) {
            $options = array_replace($templates->defaultOptions(), ['show_logo' => true, 'logo_position' => 'right', 'logo_size' => 'large', 'show_company_name' => false]);
            $templates->update($manager->company, $manager, ['template_key' => $key, 'brand_color' => '#0f766e', 'copy_label' => 'original', 'orientation' => 'portrait', 'options' => $options]);
            $render = $templates->renderData($invoice->fresh()->load(['company', 'items']));
            $markup = view(app(InvoicePdfService::class)->templateView($key), ['invoice' => $invoice->fresh()->load(['company', 'items']), 'render' => $render])->render();
            $this->assertStringContainsString('data:image/png;base64,', $markup);
            $this->assertNotEmpty(app(InvoicePdfService::class)->document($invoice->fresh())->output());
        }

        $this->actingAs($manager)->get(route('pos.receipts.show', $sale))->assertOk()->assertSee('data:image/png;base64,', false);
        $this->assertStringContainsString('/Subtype /Image', app(PosReceiptPdfService::class)->document($sale->fresh(), null)->output());

        $this->actingAs($manager)->put(route('sales.invoices.templates.update'), [
            'template_key' => 'modern_split_panel',
            'brand_color' => '#123456',
            'copy_label' => 'original',
            'orientation' => 'portrait',
            'options' => array_replace($templates->defaultOptions(), ['show_logo' => false, 'logo_position' => 'center', 'logo_size' => 'small', 'show_company_name' => false]),
        ])->assertRedirect();
        $withoutLogo = $templates->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertFalse($withoutLogo['branding']['show_logo']);
        $this->assertSame('center', $withoutLogo['branding']['logo_position']);
        $this->assertSame('small', $withoutLogo['branding']['logo_size']);
        foreach (InvoiceTemplateService::KEYS as $key) {
            $templates->update($manager->company, $manager, ['template_key' => $key, 'brand_color' => '#123456', 'copy_label' => 'original', 'orientation' => 'portrait', 'options' => array_replace($templates->defaultOptions(), ['show_logo' => false])]);
            $markup = view(app(InvoicePdfService::class)->templateView($key), ['invoice' => $invoice->fresh()->load(['company', 'items']), 'render' => $templates->renderData($invoice->fresh()->load(['company', 'items']))])->render();
            $this->assertStringNotContainsString('data:image/png;base64,', $markup);
            $this->assertStringContainsString($manager->company->legal_name, html_entity_decode($markup, ENT_QUOTES, 'UTF-8'));
        }

        $receiptMarkup = $this->actingAs($manager)->get(route('pos.receipts.show', $sale))->assertOk()->getContent();
        $this->assertStringContainsString($manager->company->name, html_entity_decode($receiptMarkup, ENT_QUOTES, 'UTF-8'));
        $this->assertNotEmpty(app(PosReceiptPdfService::class)->document($sale->fresh(), null)->output());

        $other = $this->manager();
        $this->assertNull($templates->brandingFor($other->company)['data_uri']);
    }

    /** @return array{User, CrmInvoice, PosSale} */
    private function documents(): array
    {
        $manager = $this->manager();
        $invoice = CrmInvoice::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'invoice_number' => 'RPOS-BRAND-001',
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'taxable_total' => 1000,
            'tax_total' => 180,
            'cgst_total' => 90,
            'sgst_total' => 90,
            'grand_total' => 1180,
            'amount_paid' => 0,
            'balance_due' => 1180,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);
        $invoice->items()->create(['name' => 'Branding test item', 'hsn_sac' => '998314', 'quantity' => 1, 'unit' => 'service', 'unit_price' => 1000, 'tax_rate' => 18, 'tax_amount' => 180, 'cgst_amount' => 90, 'sgst_amount' => 90, 'igst_amount' => 0, 'cess_amount' => 0, 'line_subtotal' => 1000, 'line_total' => 1180]);
        $sale = PosSale::create(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'sale_number' => 'POS-BRAND-001', 'receipt_number' => 'POS-BRAND-001', 'status' => 'completed', 'currency' => 'INR', 'subtotal' => 100, 'total_amount' => 100, 'paid_amount' => 100, 'completed_by' => $manager->id, 'completed_at' => now(), 'sold_at' => now()]);

        return [$manager, $invoice, $sale];
    }

    private function manager(): User
    {
        $company = Company::factory()->create(['industry' => 'general_retail']);
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function profilePayload(User $user, array $overrides = []): array
    {
        $company = $user->company->refresh();

        return array_replace([
            'name' => $company->name,
            'industry' => $company->industry ?: 'general_retail',
            'legal_name' => $company->legal_name,
            'address' => $company->address,
            'tax_id' => $company->tax_id,
            'phone' => $company->phone,
            'email' => $company->email,
        ], $overrides);
    }
}
