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

class InvoiceTemplateGstPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_intra_state_stored_snapshots_present_the_required_gst_rates(): void
    {
        [$user, $invoice] = $this->invoiceWithItems([
            $this->line('INTRA-5', 5, 100, 2.5, 2.5, 0, 0, 'intra_state'),
            $this->line('INTRA-12', 12, 100, 6, 6, 0, 0, 'intra_state'),
            $this->line('INTRA-18', 18, 100, 9, 9, 0, 0, 'intra_state'),
            $this->line('INTRA-28', 28, 100, 14, 14, 0, 0, 'intra_state'),
        ]);

        $rows = $this->rows($invoice);

        foreach ([5 => [2.5, 2.5], 12 => [6, 6], 18 => [9, 9], 28 => [14, 14]] as $rate => [$cgst, $sgst]) {
            $row = $rows['INTRA-'.$rate];
            $this->assertSame((float) $rate, $row['tax_rate']);
            $this->assertEquals($cgst, $row['cgst']);
            $this->assertEquals($sgst, $row['sgst']);
            $this->assertEquals(0.0, $row['igst']);
            $this->assertEquals(100.0, $row['taxable']);
        }
    }

    public function test_interstate_stored_snapshots_never_present_cgst_or_sgst(): void
    {
        [, $invoice] = $this->invoiceWithItems([
            $this->line('INTER-5', 5, 100, 0, 0, 5, 0, 'inter_state'),
            $this->line('INTER-12', 12, 100, 0, 0, 12, 0, 'inter_state'),
            $this->line('INTER-18', 18, 100, 0, 0, 18, 0, 'inter_state'),
            $this->line('INTER-28', 28, 100, 0, 0, 28, 0, 'inter_state'),
        ]);

        $rows = $this->rows($invoice);

        foreach ([5, 12, 18, 28] as $rate) {
            $row = $rows['INTER-'.$rate];
            $this->assertSame((float) $rate, $row['igst_rate']);
            $this->assertEquals((float) $rate, $row['igst']);
            $this->assertEquals(0.0, $row['cgst']);
            $this->assertEquals(0.0, $row['sgst']);
        }

        $markup = view(app(InvoicePdfService::class)->templateView('structured_gst_grid'), ['invoice' => $invoice->load(['company', 'items']), 'render' => app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']))])->render();
        $this->assertStringNotContainsString('>CGST</td>', $markup);
        $this->assertStringNotContainsString('>SGST</td>', $markup);
    }

    public function test_rate_wise_summary_keeps_cess_and_special_treatments_separate_and_reconciled(): void
    {
        [$user, $invoice] = $this->invoiceWithItems([
            $this->line('CESS-28', 28, 100, 14, 14, 0, 1, 'intra_state'),
            $this->line('CESS-28', 28, 100, 14, 14, 0, 1, 'intra_state'),
            $this->line('ZERO-0', 0, 100, 0, 0, 0, 0, 'zero_rated'),
            $this->line('EXEMPT-0', 0, 100, 0, 0, 0, 0, 'exempt'),
            $this->line('NONGST-0', 0, 100, 0, 0, 0, 0, 'non_gst'),
            $this->line('RCM-18', 18, 100, 0, 0, 0, 0, 'reverse_charge'),
        ]);

        $rows = $this->rows($invoice);
        $this->assertEquals(200.0, $rows['CESS-28']['taxable']);
        $this->assertEquals(2.0, $rows['CESS-28']['cess']);
        $this->assertEquals(58.0, $rows['CESS-28']['total_tax']);
        $this->assertSame('zero_rated', $rows['ZERO-0']['tax_treatment']);
        $this->assertSame('exempt', $rows['EXEMPT-0']['tax_treatment']);
        $this->assertSame('non_gst', $rows['NONGST-0']['tax_treatment']);
        $this->assertSame('reverse_charge', $rows['RCM-18']['tax_treatment']);
        $this->assertEquals(0.0, $rows['RCM-18']['total_tax']);

        $this->assertEquals((float) $invoice->tax_total, array_sum(array_column($rows, 'total_tax')));
        $invoice->update(['adjustment_total' => -0.01]);
        $markup = view(app(InvoicePdfService::class)->templateView('structured_gst_grid'), ['invoice' => $invoice->load(['company', 'items']), 'render' => app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']))])->render();
        $this->assertStringContainsString('Reverse Charge', $markup);
        $this->assertStringContainsString('Non Gst', $markup);
        $this->assertStringContainsString('Round-off / adjustment', $markup);
    }

    /** @return array<string,array<string,mixed>> */
    private function rows(CrmInvoice $invoice): array
    {
        return collect(app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']))['tax_rows'])->keyBy('hsn_sac')->all();
    }

    /** @param array<int,array<string,mixed>> $items @return array{User,CrmInvoice} */
    private function invoiceWithItems(array $items): array
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $taxable = array_sum(array_column($items, 'line_subtotal'));
        $cgst = array_sum(array_column($items, 'cgst_amount'));
        $sgst = array_sum(array_column($items, 'sgst_amount'));
        $igst = array_sum(array_column($items, 'igst_amount'));
        $cess = array_sum(array_column($items, 'cess_amount'));
        $tax = $cgst + $sgst + $igst + $cess;
        $invoice = CrmInvoice::create(['company_id' => $company->id, 'invoice_number' => 'GST-MATRIX-'.$company->id, 'currency' => 'INR', 'status' => InvoiceStatus::Issued, 'taxable_total' => $taxable, 'tax_total' => $tax, 'cgst_total' => $cgst, 'sgst_total' => $sgst, 'igst_total' => $igst, 'cess_total' => $cess, 'grand_total' => $taxable + $tax, 'amount_paid' => 0, 'balance_due' => $taxable + $tax, 'created_by' => $user->id, 'updated_by' => $user->id]);

        foreach ($items as $sortOrder => $item) {
            $invoice->items()->create($item + ['name' => 'GST matrix '.$item['hsn_sac'], 'quantity' => 1, 'unit' => 'unit', 'unit_price' => $item['line_subtotal'], 'tax_amount' => $item['cgst_amount'] + $item['sgst_amount'] + $item['igst_amount'] + $item['cess_amount'], 'line_total' => $item['line_subtotal'] + $item['cgst_amount'] + $item['sgst_amount'] + $item['igst_amount'] + $item['cess_amount'], 'sort_order' => $sortOrder]);
        }

        return [$user, $invoice];
    }

    /** @return array<string,mixed> */
    private function line(string $hsnSac, int $rate, float $taxable, float $cgst, float $sgst, float $igst, float $cess, string $treatment): array
    {
        return ['hsn_sac' => $hsnSac, 'tax_rate' => $rate, 'tax_treatment_snapshot' => $treatment, 'line_subtotal' => $taxable, 'cgst_amount' => $cgst, 'sgst_amount' => $sgst, 'igst_amount' => $igst, 'cess_amount' => $cess];
    }
}
