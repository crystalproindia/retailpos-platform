<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\User;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoiceAmountInWordsService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Crm\PublicInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class InvoiceTemplateOutputPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_template_is_used_by_sales_print_and_public_pdf_paths_while_sales_download_uses_premium_customer_pdf(): void
    {
        [$user, $invoice] = $this->invoiceWithItems(4);
        $templates = app(InvoiceTemplateService::class);
        $templates->update($user->company, $user, [
            'template_key' => 'modern_split_panel',
            'brand_color' => '#0f766e',
            'copy_label' => 'original',
            'orientation' => 'portrait',
            'payment_qr_uri' => 'merchant@upi',
            'options' => $templates->defaultOptions(),
        ]);

        $this->actingAs($user)->get(route('sales.invoices.templates.index'))->assertOk()->assertSee('Invoice designs')->assertSee(route('sales.invoices.templates.preview', $invoice), false);
        $this->actingAs($user)->get(route('sales.invoices.templates.preview', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->get(route('sales.invoices.show', $invoice))->assertOk()->assertSee(route('sales.invoices.print', $invoice), false);
        $this->actingAs($user)->get(route('sales.invoices.print', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->get(route('sales.invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');

        $premiumRender = $templates->renderData($invoice->fresh()->load(['company', 'items']), ['paper_format' => 'a4', 'orientation' => 'portrait']);
        $premiumMarkup = view('invoice-templates.premium-customer-download', ['invoice' => $invoice->fresh()->load(['company', 'items']), 'render' => $premiumRender])->render();
        $this->assertStringContainsString('PAYMENT &amp; RECEIVABLES SUMMARY', $premiumMarkup);
        $this->assertStringContainsString('Amount in words', $premiumMarkup);
        $this->assertStringNotContainsString('Taxable Amount</td>', $premiumMarkup);
        $this->assertSame('Rupees Twelve Thousand Three Hundred Forty Five and Sixty Seven Paise only', app(InvoiceAmountInWordsService::class)->format('INR', '12345.67'));
        $this->assertStringStartsWith('%PDF-', app(InvoicePdfService::class)->premiumCustomerDocument($invoice->fresh()->load(['company', 'items']))->output());

        $link = app(PublicInvoiceService::class)->issue($invoice, $user);
        $token = basename((string) parse_url($link->url, PHP_URL_PATH));
        $this->get(route('invoices.public.pdf', $token))->assertOk()->assertHeader('content-type', 'application/pdf');

        [$otherUser] = $this->invoiceWithItems(1);
        $this->actingAs($otherUser)->get(route('sales.invoices.templates.preview', $invoice))->assertNotFound();
    }

    #[DataProvider('premiumReceivableTemplates')]
    public function test_premium_receivable_templates_render_with_authoritative_summary(string $templateKey): void
    {
        [$user, $invoice] = $this->invoiceWithItems(5);
        $templates = app(InvoiceTemplateService::class);
        $templates->update($user->company, $user, ['template_key' => $templateKey, 'brand_color' => '#123b70', 'copy_label' => 'original', 'orientation' => 'portrait', 'options' => $templates->defaultOptions()]);
        $render = $templates->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertSame((int) round((float) $invoice->grand_total * 100), $render['receivable']['balance_receivable']);
        $this->assertStringContainsString('PAYMENT &amp; RECEIVABLE SUMMARY', view($render['template']['view'], ['invoice' => $invoice->fresh()->load(['company', 'items']), 'render' => $render])->render());
        $this->assertStringStartsWith('%PDF-', app(InvoicePdfService::class)->document($invoice->fresh()->load(['company', 'items']))->output());
    }

    /** @return iterable<string,array{string}> */
    public static function premiumReceivableTemplates(): iterable
    {
        foreach (['retailpos_premium_blue', 'executive_navy_receivable', 'modern_minimal_receivable', 'professional_indigo_receivable', 'emerald_finance_receivable', 'slate_professional_receivable', 'royal_blue_services_receivable', 'warm_corporate_receivable', 'compact_ledger_pro_receivable'] as $key) yield $key => [$key];
    }

    #[DataProvider('longDocumentCases')]
    #[RunInSeparateProcess]
    public function test_representative_a4_templates_render_long_documents_with_repeated_headers_and_embedded_qr(string $templateKey, int $lineCount): void
    {
        [$user, $invoice] = $this->invoiceWithItems($lineCount);
        $templates = app(InvoiceTemplateService::class);
        $templates->update($user->company, $user, [
            'template_key' => $templateKey,
            'brand_color' => '#0f766e',
            'copy_label' => 'original',
            'orientation' => 'portrait',
            'payment_qr_uri' => 'merchant@upi',
            'options' => $templates->defaultOptions(),
        ]);

        $pdf = app(InvoicePdfService::class)->document($invoice->fresh()->load(['company', 'items']))->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(10_000, strlen($pdf));
        $this->assertLessThan(5_000_000, strlen($pdf));
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf));
        $this->assertStringContainsString('/Subtype /Image', $pdf);
    }

    /** @return iterable<string,array{string,int}> */
    public static function longDocumentCases(): iterable
    {
        foreach ([50, 100, 200] as $lineCount) {
            foreach (['structured_gst_grid', 'premium_elegant', 'compact_detailed_gst', 'modern_split_panel', 'executive_corporate_gst'] as $templateKey) {
                yield $templateKey.'-'.$lineCount => [$templateKey, $lineCount];
            }
        }
    }

    /** @return array{User,CrmInvoice} */
    private function invoiceWithItems(int $count): array
    {
        $company = Company::factory()->create([
            'currency' => 'INR',
            'address' => str_repeat('Corporate House, Sector 18, Business District, New Delhi 110001. ', 8),
        ]);
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $invoice = CrmInvoice::create([
            'company_id' => $company->id,
            'invoice_number' => 'RPOS-LONG-'.$company->id.'-'.$count,
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'billing_name' => 'Asha Enterprise Procurement Team',
            'billing_company' => 'Asha Enterprise Private Limited',
            'billing_address' => str_repeat('Billing tower, procurement floor, Bengaluru 560001. ', 12),
            'terms_conditions' => str_repeat('Payment is due against the stated invoice terms. ', 50),
            'taxable_total' => 0,
            'tax_total' => 0,
            'cgst_total' => 0,
            'sgst_total' => 0,
            'igst_total' => 0,
            'cess_total' => 0,
            'grand_total' => 0,
            'amount_paid' => 0,
            'balance_due' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $rates = [5, 12, 18, 28];
        $taxable = $cgst = $sgst = $cess = 0.0;
        for ($index = 1; $index <= $count; $index++) {
            $rate = $rates[($index - 1) % count($rates)];
            $amount = 100 + $index;
            $lineCgst = $amount * ($rate / 200);
            $lineSgst = $lineCgst;
            $lineCess = $rate === 28 && $index % 7 === 0 ? 1.5 : 0.0;
            $invoice->items()->create([
                'name' => $count === 50
                    ? 'Long catalogue item '.$index.' - '.str_repeat('descriptive retail product text ', 4)
                    : 'Catalogue item '.$index,
                'description' => $count === 50
                    ? str_repeat('Extended specification for reliable multipage PDF testing. ', 3)
                    : null,
                'hsn_sac' => 'HSN-'.(1000 + ($index % 9)),
                'quantity' => 1,
                'unit' => 'unit',
                'unit_price' => $amount,
                'tax_rate' => $rate,
                'tax_treatment_snapshot' => 'intra_state',
                'tax_amount' => $lineCgst + $lineSgst + $lineCess,
                'cgst_amount' => $lineCgst,
                'sgst_amount' => $lineSgst,
                'igst_amount' => 0,
                'cess_amount' => $lineCess,
                'line_subtotal' => $amount,
                'line_total' => $amount + $lineCgst + $lineSgst + $lineCess,
                'sort_order' => $index,
            ]);
            $taxable += $amount;
            $cgst += $lineCgst;
            $sgst += $lineSgst;
            $cess += $lineCess;
        }

        $invoice->update([
            'taxable_total' => $taxable,
            'tax_total' => $cgst + $sgst + $cess,
            'cgst_total' => $cgst,
            'sgst_total' => $sgst,
            'cess_total' => $cess,
            'grand_total' => $taxable + $cgst + $sgst + $cess,
            'balance_due' => $taxable + $cgst + $sgst + $cess,
        ]);

        return [$user, $invoice];
    }
}
