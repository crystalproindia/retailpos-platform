<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Branding\CompanyBrandingService;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceSignatureLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a4_a5_and_thermal_signature_layouts_keep_signature_payment_details_and_watermark_separate(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $company = app(CompanyBrandingService::class)->replace($manager->company, $manager, UploadedFile::fake()->image('signature.png', 320, 120), 'signature');
        $company->forceFill([
            'authorized_signatory_name' => 'Dinesh Kumar Suryavanshi Patel-Ramanathan',
            'authorized_signatory_designation' => 'Senior Vice President, Enterprise Retail Operations and Compliance',
        ])->save();
        $manager->setRelation('company', $company);

        $templates = app(InvoiceTemplateService::class);
        $templates->update(
            $company,
            $manager,
            $this->settings($templates, 'structured_gst_grid', 'a4'),
            UploadedFile::fake()->image('watermark.png', 600, 320),
        );
        $invoice = $this->invoice($manager)->load(['company', 'items']);

        foreach ($templates->definitions() as $key => $definition) {
            $templates->update($company, $manager, $this->settings($templates, $key, $definition['paper_format']));
            $render = $templates->renderData($invoice, [
                'template_key' => $key,
                'paper_format' => $definition['paper_format'],
                'orientation' => 'portrait',
            ]);
            $markup = view($definition['view'], compact('invoice', 'render'))->render();

            if ($definition['paper_format'] === 'thermal_58') {
                $this->assertStringNotContainsString('authorized-signature', $markup, $key);

                continue;
            }

            $this->assertSame(1, substr_count($markup, 'Dinesh Kumar Suryavanshi Patel-Ramanathan'), $key);
            $this->assertSame(1, substr_count($markup, 'Senior Vice President, Enterprise Retail Operations and Compliance'), $key);
            $this->assertStringContainsString('class="authorized-signature avoid-break"', $markup, $key);
            $this->assertStringContainsString('text-align:center', $markup, $key);
            $this->assertStringContainsString($definition['paper_format'] === 'thermal_80' ? 'height:38px' : 'height:58px', $markup, $key);
            $this->assertStringContainsString('Payment details', $markup, $key);

            if ($definition['paper_format'] !== 'thermal_80') {
                $this->assertStringContainsString('invoice-watermark', $markup, $key);
            }
        }

        $templates->update($company, $manager, $this->settings($templates, 'structured_gst_grid', 'a4'));
        $pdf = app(InvoicePdfService::class)->document($invoice)->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf));
        $this->assertStringContainsString('/Subtype /Image', $pdf);
    }

    /** @return array<string, mixed> */
    private function settings(InvoiceTemplateService $templates, string $templateKey, string $paperFormat): array
    {
        return [
            'template_key' => $templateKey,
            'paper_format' => $paperFormat,
            'brand_color' => '#0f766e',
            'copy_label' => 'customer_copy',
            'orientation' => 'portrait',
            'gst_presentation' => 'detailed',
            'account_holder_name' => 'Crystal Retail Private Limited',
            'account_number' => '123456789012345678',
            'upi_id' => 'billing@retailpos',
            'payment_note' => str_repeat('Payment reference must include this invoice number. ', 10),
            'watermark_enabled' => true,
            'options' => $templates->defaultOptions(),
        ];
    }

    private function invoice(User $manager)
    {
        $invoice = app(InvoiceService::class)->create($manager, [
            'billing_name' => 'Asha Sharma',
            'billing_company' => 'Asha Retail',
            'billing_email' => 'asha@example.test',
            'billing_address' => str_repeat('42 Market Street, Bengaluru, Karnataka 560001. ', 8),
            'currency' => 'INR',
            'items' => [[
                'name' => 'RetailPOS enterprise subscription',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_value' => 0,
                'tax_rate' => 18,
            ]],
        ]);

        for ($index = 1; $index <= 22; $index++) {
            $invoice->items()->create([
                'name' => 'Extended catalogue product '.$index.' '.str_repeat('for multi-page layout coverage ', 4),
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 18,
                'tax_amount' => 18,
                'cgst_amount' => 9,
                'sgst_amount' => 9,
                'line_subtotal' => 100,
                'line_total' => 118,
                'sort_order' => $index + 1,
            ]);
        }

        return $invoice;
    }

    private function manager(): User
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }
}
