<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceItem;
use App\Models\Crm\CrmInvoiceReturn;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\Ai\BusinessIntelligenceContextService;
use App\Services\Crm\CrmInvoiceReturnService;
use App\Services\Reports\ExecutiveReportingService;
use App\Services\Reports\RetailReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class CrmReturnsCreditNotesTest extends TestCase
{
    use BuildsReportingData;
    use RefreshDatabase;

    public function test_partial_and_multiple_returns_reverse_original_snapshots_without_mutating_invoice_totals(): void
    {
        [$manager, $invoice, $item] = $this->fixture();

        $first = $this->finalize($manager, $invoice, $item, '1.000');
        $this->assertSame('100.00', $first->gross_total);
        $this->assertSame('10.00', $first->discount_total);
        $this->assertSame('90.00', $first->taxable_total);
        $this->assertSame('16.20', $first->tax_total);
        $this->assertSame('8.10', $first->cgst_total);
        $this->assertSame('8.10', $first->sgst_total);
        $this->assertSame('106.20', $first->credit_total);
        $this->assertSame('50.00', $first->known_cogs_reversal);
        $this->assertSame('40.00', $first->known_profit_reversal);
        $this->assertSame('318.60', $invoice->fresh()->balance_due);
        $this->assertSame('106.20', $invoice->fresh()->credited_total);
        $this->assertSame('424.80', $invoice->fresh()->grand_total);

        $second = $this->finalize($manager, $invoice, $item, '1.000');
        $this->assertNotSame($first->credit_note_number, $second->credit_note_number);
        $this->assertSame('212.40', $invoice->fresh()->credited_total);
        $this->assertSame('partial', $invoice->fresh()->return_status);
    }

    public function test_over_return_and_forged_line_are_rejected_atomically(): void
    {
        [$manager, $invoice, $item] = $this->fixture();
        $this->finalize($manager, $invoice, $item, '3.000');

        try {
            $this->finalize($manager, $invoice, $item, '2.000');
            $this->fail('Expected over-return rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
        [$otherManager, $otherInvoice, $otherItem] = $this->fixture();
        try {
            $this->finalize($manager, $invoice, $otherItem, '1.000');
            $this->fail('Expected forged line rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
        $this->assertSame(1, CrmInvoiceReturn::query()->where('invoice_id', $invoice->id)->count());
        $this->assertNotSame($manager->company_id, $otherManager->company_id);
        $this->assertNotSame($invoice->id, $otherInvoice->id);
    }

    public function test_return_all_remaining_marks_invoice_fully_credited(): void
    {
        [$manager, $invoice, $item] = $this->fixture();
        $this->finalize($manager, $invoice, $item, '1.000');
        $this->finalize($manager, $invoice, $item, '3.000');

        $invoice->refresh();
        $this->assertSame('full', $invoice->return_status);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::Credited, $invoice->status);
        $this->assertSame('424.80', $invoice->grand_total);
    }

    public function test_duplicate_submission_is_idempotent(): void
    {
        [$manager, $invoice, $item] = $this->fixture();
        $key = (string) Str::uuid();
        $first = $this->finalize($manager, $invoice, $item, '1.000', false, $key);
        $second = $this->finalize($manager, $invoice, $item, '1.000', false, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CrmInvoiceReturn::count());
        $this->assertSame('106.20', $invoice->fresh()->credited_total);
    }

    public function test_authenticated_manager_can_finalize_through_the_http_workflow(): void
    {
        [$manager, $invoice, $item] = $this->fixture();

        $response = $this->actingAs($manager)->post(route('sales.invoices.returns.store', $invoice), [
            'idempotency_key' => (string) Str::uuid(),
            'reason_code' => 'defective',
            'reason_note' => 'Packaging was intact; product was defective.',
            'items' => [['invoice_item_id' => $item->id, 'return_quantity' => '1.000', 'restock' => false]],
        ]);

        $response->assertSessionHasNoErrors();
        $return = CrmInvoiceReturn::query()->sole();
        $response->assertRedirect(route('sales.credit-notes.show', $return));
        $this->assertSame('defective', $return->reason_code);
        $this->assertSame($manager->id, $return->finalized_by);
    }

    public function test_rounding_sensitive_partial_returns_reconcile_to_the_original_snapshots(): void
    {
        [$manager, $invoice, $item] = $this->fixture();
        $invoice->update(['subtotal' => '100.00', 'discount_total' => '1.00', 'taxable_total' => '99.00', 'tax_total' => '17.82', 'cgst_total' => '8.91', 'sgst_total' => '8.91', 'grand_total' => '116.82', 'balance_due' => '116.82']);
        $item->update(['quantity' => '3.000', 'gross_sales_snapshot' => '100.00', 'discount_amount' => '1.00', 'discount_value' => '1.00', 'net_sales_snapshot' => '99.00', 'line_subtotal' => '99.00', 'tax_amount' => '17.82', 'cgst_amount' => '8.91', 'sgst_amount' => '8.91', 'line_total' => '116.82', 'total_cost_snapshot' => '60.00', 'gross_profit_snapshot' => '39.00']);

        $returns = collect([
            $this->finalize($manager, $invoice, $item, '1.000'),
            $this->finalize($manager, $invoice, $item, '1.000'),
            $this->finalize($manager, $invoice, $item, '1.000'),
        ]);

        $this->assertSame(['0.33', '0.34', '0.33'], $returns->pluck('discount_total')->all());
        $this->assertSame(10000, $returns->sum(fn (CrmInvoiceReturn $return): int => (int) round((float) $return->gross_total * 100)));
        $this->assertSame(100, $returns->sum(fn (CrmInvoiceReturn $return): int => (int) round((float) $return->discount_total * 100)));
        $this->assertSame(1782, $returns->sum(fn (CrmInvoiceReturn $return): int => (int) round((float) $return->tax_total * 100)));
        $this->assertSame(11682, $returns->sum(fn (CrmInvoiceReturn $return): int => (int) round((float) $return->credit_total * 100)));
        $this->assertSame('116.82', $invoice->fresh()->credited_total);
    }

    public function test_free_text_return_keeps_cost_unavailable_and_does_not_guess_cogs(): void
    {
        [$manager, $invoice, $item] = $this->fixture(false);
        $return = $this->finalize($manager, $invoice, $item, '1.000');

        $line = $return->items->first();
        $this->assertSame('unavailable', $line->cost_status);
        $this->assertNull($line->cogs_reversal);
        $this->assertNull($line->gross_profit_reversal);
        $this->assertSame(1, $return->unavailable_cost_item_count);
        $this->assertSame('0.00', $return->known_cogs_reversal);
    }

    public function test_restock_uses_original_crm_stock_location_and_non_restock_does_not_change_stock(): void
    {
        [$manager, $invoice, $item, $product] = $this->fixture();
        $warehouse = Warehouse::create(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'name' => 'CRM warehouse', 'code' => 'CRM-WH', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        $level = StockLevel::create(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity_on_hand' => '6.000', 'quantity_available' => '6.000']);
        StockMovement::create(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'movement_type' => 'sale', 'direction' => 'out', 'quantity' => '4.000', 'quantity_before' => '10.000', 'quantity_after' => '6.000', 'unit_cost' => '50.00', 'reference_type' => CrmInvoice::class, 'reference_id' => $invoice->id, 'created_by' => $manager->id, 'occurred_at' => now()]);

        $restocked = $this->finalize($manager, $invoice, $item, '1.000', true);
        $this->assertSame('7.000', $level->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', ['crm_invoice_return_item_id' => $restocked->items->first()->id, 'movement_type' => 'crm_sale_return', 'direction' => 'in']);
        $this->finalize($manager, $invoice, $item, '1.000', false);
        $this->assertSame('7.000', $level->fresh()->quantity_on_hand);
    }

    public function test_restock_is_rejected_without_authoritative_original_stock_movement(): void
    {
        [$manager, $invoice, $item] = $this->fixture();
        $this->expectException(ValidationException::class);
        $this->finalize($manager, $invoice, $item, '1.000', true);
    }

    public function test_credit_note_reduces_receivable_but_never_creates_a_refund_payment(): void
    {
        [$manager, $invoice, $item] = $this->fixture();
        $invoice->payments()->create(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'payment_reference' => 'PAY-FULL', 'receipt_number' => 'RCPT-FULL', 'amount' => '424.80', 'currency' => 'INR', 'payment_date' => today(), 'payment_method' => 'bank_transfer', 'status' => 'cleared', 'recorded_by' => $manager->id, 'cleared_by' => $manager->id, 'cleared_at' => now(), 'idempotency_key' => hash('sha256', 'paid-return-test')]);
        $invoice->update(['amount_paid' => '424.80', 'balance_due' => '0.00', 'status' => InvoiceStatus::Paid]);
        $return = $this->finalize($manager, $invoice, $item, '1.000');

        $this->assertSame('0.00', $return->receivable_credit_applied);
        $this->assertSame('106.20', $return->customer_credit_due);
        $this->assertDatabaseCount('crm_invoice_payments', 1);
        $this->assertSame('424.80', $invoice->fresh()->amount_paid);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_profitability_gst_sales_returns_owner_and_ai_sources_receive_authoritative_reversal(): void
    {
        [$manager, $invoice, $item] = $this->fixture();
        $this->finalize($manager, $invoice, $item, '1.000');
        $report = app(RetailReportingService::class)->summary($manager, ['outlet_id' => $manager->branch_id, 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString(), 'source' => 'crm']);

        $this->assertSame(27000, $report['reports']['profitability']['net_sales']);
        $this->assertSame(15000, $report['reports']['profitability']['cost_of_goods_sold']);
        $this->assertSame(12000, $report['reports']['profitability']['gross_profit']);
        $this->assertSame(9000, $report['reports']['profitability']['return_impact']);
        $this->assertSame(10620, $report['reports']['sales_returns']['crm_credit_total']);
        $this->assertSame(27000, $report['reports']['gst']['taxable_sales']);
        $this->assertSame(2430, $report['reports']['gst']['cgst']);
        $this->assertSame(2430, $report['reports']['gst']['sgst']);
        $this->assertSame(10620, $report['metrics']['sales_return_value']);

        $executive = app(ExecutiveReportingService::class)->dashboard($manager, ['outlet_id' => $manager->branch_id, 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString()], false);
        $this->assertSame(27000, collect($executive['kpis'])->firstWhere('key', 'net_sales')['value']);
        $this->assertSame(12000, collect($executive['kpis'])->firstWhere('key', 'gross_profit')['value']);

        $ai = app(BusinessIntelligenceContextService::class)->forIntent($manager, 'sales_summary', ['label' => 'today', 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString()], $manager->branch_id);
        $this->assertSame(10620, collect($ai['facts'])->firstWhere('label', 'Sales returns')['value']);
    }

    public function test_permissions_tenant_outlet_invalid_status_detail_and_pdf_are_enforced(): void
    {
        [$manager, $invoice, $item] = $this->fixture();
        $sales = User::factory()->create(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'role' => UserRole::Sales, 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'role' => UserRole::Staff, 'is_active' => true]);
        $this->actingAs($manager)->get(route('sales.invoices.returns.create', $invoice))->assertOk()->assertSee('Return All Remaining Items');
        $this->actingAs($sales)->get(route('sales.invoices.returns.create', $invoice))->assertForbidden();
        $this->actingAs($staff)->get(route('sales.invoices.returns.create', $invoice))->assertForbidden();

        $return = $this->finalize($manager, $invoice, $item, '1.000');
        $this->actingAs($manager)->get(route('sales.credit-notes.show', $return))->assertOk()->assertSee($return->credit_note_number);
        $this->actingAs($manager)->get(route('sales.credit-notes.pdf', $return))->assertOk()->assertHeader('content-type', 'application/pdf');
        [$other] = $this->fixture();
        $this->actingAs($other)->get(route('sales.credit-notes.show', $return))->assertNotFound();

        $otherBranch = Branch::factory()->create(['company_id' => $manager->company_id, 'is_active' => true]);
        $outletInvoice = $invoice->replicate(['invoice_number']);
        $outletInvoice->fill(['branch_id' => $otherBranch->id, 'invoice_number' => 'INV-OTHER-OUTLET'])->save();
        $this->actingAs($manager)->get(route('sales.invoices.returns.create', $outletInvoice))->assertNotFound();

        $invoice->update(['status' => InvoiceStatus::Cancelled]);
        $this->actingAs($manager)->get(route('sales.invoices.returns.create', $invoice))->assertSessionHasErrors('invoice');
    }

    public function test_credit_note_numbering_is_tenant_scoped(): void
    {
        [$firstUser, $firstInvoice, $firstItem] = $this->fixture();
        [$secondUser, $secondInvoice, $secondItem] = $this->fixture();

        $first = $this->finalize($firstUser, $firstInvoice, $firstItem, '1.000');
        $second = $this->finalize($secondUser, $secondInvoice, $secondItem, '1.000');

        $this->assertSame($first->credit_note_number, $second->credit_note_number);
        $this->assertNotSame($first->company_id, $second->company_id);
    }

    /** @return array{User, CrmInvoice, CrmInvoiceItem, Product|null} */
    private function fixture(bool $productLinked = true): array
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata', 'currency' => 'INR', 'tax_id' => '29ABCDE1234F1Z5']);
        $branch = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $manager = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Manager, 'is_active' => true]);
        $product = $productLinked ? $this->reportProduct($company, $branch, 'Linked Product', '50.00') : null;
        if ($product) {
            $product->update(['selling_price' => '100.00']);
        }
        $invoice = CrmInvoice::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'invoice_number' => 'INV-'.$company->id, 'billing_name' => 'Asha Buyer', 'billing_company' => 'Asha Stores', 'billing_address' => 'Bengaluru', 'customer_tax_number' => '29AAAAA0000A1Z5', 'currency' => 'INR', 'subtotal' => '400.00', 'discount_total' => '40.00', 'taxable_total' => '360.00', 'tax_total' => '64.80', 'cgst_total' => '32.40', 'sgst_total' => '32.40', 'igst_total' => '0.00', 'cess_total' => '0.00', 'grand_total' => '424.80', 'amount_paid' => '0.00', 'balance_due' => '424.80', 'status' => InvoiceStatus::Issued, 'issue_date' => today(), 'due_date' => today()->addDays(7), 'created_by' => $manager->id, 'updated_by' => $manager->id]);
        $item = $invoice->items()->create(['product_id' => $product?->id, 'name' => $product?->name ?? 'Custom Service', 'sku_snapshot' => $product?->sku, 'hsn_sac' => '1001', 'quantity' => '4.000', 'unit' => 'unit', 'unit_price' => '100.00', 'discount_type' => 'fixed', 'discount_value' => '40.00', 'discount_amount' => '40.00', 'tax_rate' => '18.000', 'tax_amount' => '64.80', 'cgst_amount' => '32.40', 'sgst_amount' => '32.40', 'igst_amount' => '0.00', 'cess_amount' => '0.00', 'line_subtotal' => '360.00', 'line_total' => '424.80', 'gross_sales_snapshot' => '400.00', 'net_sales_snapshot' => '360.00', 'unit_cost_snapshot' => $product ? '50.00' : null, 'total_cost_snapshot' => $product ? '200.00' : null, 'gross_profit_snapshot' => $product ? '160.00' : null, 'cost_snapshot_status' => $product ? 'captured' : 'unavailable', 'cost_snapshot_method' => $product ? 'standard_cost' : null]);

        return [$manager, $invoice, $item, $product];
    }

    private function finalize(User $user, CrmInvoice $invoice, $item, string $quantity, bool $restock = false, ?string $key = null): CrmInvoiceReturn
    {
        return app(CrmInvoiceReturnService::class)->finalize($user, $invoice->id, ['idempotency_key' => $key ?: (string) Str::uuid(), 'reason_code' => 'customer_return', 'items' => [['invoice_item_id' => $item->id, 'return_quantity' => $quantity, 'restock' => $restock]]]);
    }
}
