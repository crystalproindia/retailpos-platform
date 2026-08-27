<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceAmendment;
use App\Models\User;
use App\Services\Ai\BusinessIntelligenceContextService;
use App\Services\Crm\CrmInvoiceReturnService;
use App\Services\Crm\InvoiceAmendmentService;
use App\Services\Crm\InvoiceShareService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Finance\ReceivableService;
use App\Services\Reports\RetailReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class SalesInvoiceAmendmentTest extends TestCase
{
    use BuildsReportingData;
    use RefreshDatabase;

    public function test_draft_edit_remains_available_and_issued_invoice_exposes_controlled_amendment(): void
    {
        $fixture = $this->fixture(InvoiceStatus::Draft);
        $this->actingAs($fixture['manager'])->get(route('sales.invoices.edit', $fixture['invoice']))->assertOk();

        $fixture['invoice']->update(['status' => InvoiceStatus::Issued]);
        $this->actingAs($fixture['manager'])->get(route('sales.invoices.show', $fixture['invoice']))
            ->assertOk()->assertSee('Amend invoice')->assertDontSee('Edit draft');
        $this->actingAs($fixture['manager'])->get(route('sales.invoices.amendments.create', $fixture['invoice']))
            ->assertOk()->assertSee('Original issued lines stay unchanged')->assertSee('Add Service')->assertSee('Add Product');
    }

    public function test_service_amendment_is_immutable_idempotent_and_preserves_original_line(): void
    {
        $fixture = $this->fixture();
        $before = $fixture['original']->fresh()->only(['name', 'quantity', 'unit_price', 'line_total', 'gross_sales_snapshot', 'net_sales_snapshot', 'cost_snapshot_status']);
        $key = (string) Str::uuid();
        $amendment = $this->amend($fixture, $this->serviceLine(), $key);
        $duplicate = $this->amend($fixture, $this->serviceLine(), $key, 1);

        $this->assertSame($amendment->id, $duplicate->id);
        $this->assertDatabaseCount('crm_invoice_amendments', 1);
        $this->assertDatabaseCount('crm_invoice_amendment_items', 1);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame($before, $fixture['original']->fresh()->only(array_keys($before)));
        $this->assertSame('unavailable', $amendment->items->first()->cost_status_snapshot);
        $this->assertNull($amendment->items->first()->unit_cost_snapshot);
        $this->assertSame('159.00', $fixture['invoice']->fresh()->grand_total);
        $this->assertSame('159.00', $fixture['invoice']->fresh()->balance_due);
        $this->assertSame(2, $fixture['invoice']->fresh()->amendment_version);
    }

    public function test_product_amendment_snapshots_cost_posts_stock_and_is_returnable(): void
    {
        $fixture = $this->fixture();
        $amendment = $this->amend($fixture, $this->productLine($fixture), warehouseId: $fixture['warehouse']->id);
        $line = $amendment->items->first()->invoiceItem;

        $this->assertSame('30.00', $line->unit_cost_snapshot);
        $this->assertSame('60.00', $line->total_cost_snapshot);
        $this->assertSame('40.00', $line->gross_profit_snapshot);
        $this->assertSame('captured', $line->cost_snapshot_status);
        $this->assertSame('8.000', $fixture['stock']->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', ['crm_invoice_item_id' => $line->id, 'movement_type' => 'sale', 'direction' => 'out', 'quantity' => 2]);

        $return = app(CrmInvoiceReturnService::class)->finalize($fixture['manager'], $fixture['invoice']->id, [
            'idempotency_key' => (string) Str::uuid(), 'reason_code' => 'customer_return',
            'items' => [['invoice_item_id' => $line->id, 'return_quantity' => '1.000', 'restock' => true]],
        ]);
        $this->assertSame('1.000', $return->items->first()->return_quantity);
        $this->assertSame('9.000', $fixture['stock']->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', ['crm_invoice_return_item_id' => $return->items->first()->id, 'movement_type' => 'crm_sale_return']);
    }

    public function test_paid_and_partially_credited_financial_history_is_preserved(): void
    {
        $fixture = $this->fixture();
        $payment = $fixture['invoice']->payments()->create([
            'company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch']->id, 'payment_reference' => 'PAY-AMEND',
            'receipt_number' => 'RCPT-AMEND', 'amount' => '100.00', 'currency' => 'INR', 'payment_date' => today(),
            'payment_method' => 'bank_transfer', 'status' => 'cleared', 'recorded_by' => $fixture['manager']->id,
            'cleared_by' => $fixture['manager']->id, 'cleared_at' => now(), 'idempotency_key' => hash('sha256', 'amend-payment'),
        ]);
        $fixture['invoice']->update(['amount_paid' => '100.00', 'balance_due' => '0.00', 'status' => InvoiceStatus::Paid, 'paid_at' => now()]);
        $this->amend($fixture, $this->serviceLine());

        $invoice = $fixture['invoice']->fresh();
        $this->assertSame($payment->id, $invoice->payments()->sole()->id);
        $this->assertSame('100.00', $invoice->amount_paid);
        $this->assertSame('59.00', $invoice->balance_due);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertNull($invoice->paid_at);
    }

    public function test_gst_components_reports_profitability_pdf_and_statement_use_authoritative_amended_values(): void
    {
        $fixture = $this->fixture();
        $fixture['invoice']->update(['tax_mode' => 'gst', 'supplier_state_code_snapshot' => '29', 'place_of_supply_state_code' => '29']);
        $line = array_replace($this->productLine($fixture), ['tax_rate' => '18']);
        $amendment = $this->amend($fixture, $line, warehouseId: $fixture['warehouse']->id);
        $invoice = $fixture['invoice']->fresh();

        $this->assertSame('9.00', $amendment->cgst_added);
        $this->assertSame('9.00', $amendment->sgst_added);
        $this->assertSame('0.00', $amendment->igst_added);
        $this->assertSame('118.00', $amendment->amount_added);
        $this->assertSame('218.00', $invoice->grand_total);
        $report = app(RetailReportingService::class)->summary($fixture['manager'], ['outlet_id' => $fixture['branch']->id, 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString(), 'source' => 'crm']);
        $this->assertSame(20000, $report['reports']['profitability']['net_sales']);
        $this->assertSame(6000, $report['reports']['profitability']['cost_of_goods_sold']);
        $this->assertSame(900, $report['reports']['gst']['cgst']);
        $this->assertSame(900, $report['reports']['gst']['sgst']);
        $this->actingAs($fixture['manager'])->get(route('sales.invoices.pdf', $invoice))->assertOk();
        $this->assertStringContainsString('AMENDED', app(InvoiceTemplateService::class)->renderData($invoice)['document_title']);
        $statement = app(ReceivableService::class)->statement($fixture['manager'], $fixture['customer'], now()->subDay()->toImmutable(), now()->addDay()->toImmutable());
        $this->assertSame(21800, collect($statement['rows'])->firstWhere('reference', $invoice->invoice_number)['debit']);
    }

    public function test_permissions_tenant_outlet_and_terminal_state_are_enforced(): void
    {
        $fixture = $this->fixture();
        $this->actingAs($fixture['manager'])
            ->getJson(route('sales.invoices.amendments.products.search', ['q' => 'Amended']))
            ->assertOk()
            ->assertJsonPath('products.0.id', $fixture['product']->id);
        $sales = User::factory()->create(['company_id' => $fixture['company']->id, 'branch_id' => $fixture['branch']->id, 'role' => UserRole::Sales, 'is_active' => true]);
        $this->actingAs($sales)->get(route('sales.invoices.amendments.create', $fixture['invoice']))->assertForbidden();
        $this->actingAs($sales)->getJson(route('sales.invoices.amendments.products.search', ['q' => 'Amended']))->assertForbidden();

        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->create(['company_id' => $otherCompany->id, 'role' => UserRole::Administrator, 'is_active' => true]);
        $this->actingAs($outsider)->get(route('sales.invoices.amendments.create', $fixture['invoice']))->assertNotFound();

        $otherBranch = Branch::factory()->create(['company_id' => $fixture['company']->id, 'is_active' => true]);
        $otherInvoice = $fixture['invoice']->replicate(['invoice_number']);
        $otherInvoice->fill(['branch_id' => $otherBranch->id, 'invoice_number' => 'INV-OTHER-OUTLET'])->save();
        $this->actingAs($fixture['manager'])->get(route('sales.invoices.amendments.create', $otherInvoice))->assertNotFound();

        $fixture['invoice']->update(['status' => InvoiceStatus::Cancelled]);
        $this->actingAs($fixture['manager'])->get(route('sales.invoices.show', $fixture['invoice']))->assertOk()->assertDontSee('Amend invoice');
    }

    public function test_traceability_products_are_rejected_without_batch_or_serial_allocation(): void
    {
        $fixture = $this->fixture();
        $fixture['product']->update(['track_batches' => true]);

        try {
            $this->amend($fixture, $this->productLine($fixture), warehouseId: $fixture['warehouse']->id);
            $this->fail('Expected traceability validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertDatabaseCount('crm_invoice_amendments', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('10.000', $fixture['stock']->fresh()->quantity_on_hand);
    }

    public function test_stale_version_rejects_concurrent_amendment_without_partial_writes(): void
    {
        $fixture = $this->fixture();
        $this->amend($fixture, $this->serviceLine());

        try {
            $this->amend($fixture, $this->serviceLine('Second service'), expectedVersion: 1);
            $this->fail('Expected stale amendment rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice', $exception->errors());
        }
        $this->assertDatabaseCount('crm_invoice_amendments', 1);
        $this->assertDatabaseCount('crm_invoice_amendment_items', 1);
        $this->assertSame('159.00', $fixture['invoice']->fresh()->grand_total);
    }

    public function test_credit_limit_blocks_and_authorized_override_reuses_finance_rules(): void
    {
        $fixture = $this->fixture();
        $fixture['customer']->update(['credit_limit' => '120.00']);

        try {
            $this->amend($fixture, $this->serviceLine());
            $this->fail('Expected credit-limit rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('credit_limit', $exception->errors());
        }
        $this->assertDatabaseCount('crm_invoice_amendments', 0);

        $amendment = $this->amend($fixture, $this->serviceLine(), override: true);
        $this->assertSame('59.00', $amendment->amount_added);
        $this->assertDatabaseHas('audit_logs', ['event' => 'finance.customer_credit_limit.overridden']);
    }

    public function test_partial_credit_note_remains_intact_and_new_balance_is_reconciled_once(): void
    {
        $fixture = $this->fixture();
        $credit = app(CrmInvoiceReturnService::class)->finalize($fixture['manager'], $fixture['invoice']->id, [
            'idempotency_key' => (string) Str::uuid(), 'reason_code' => 'service_adjustment',
            'items' => [['invoice_item_id' => $fixture['original']->id, 'return_quantity' => '0.250', 'restock' => false]],
        ]);
        $this->assertSame('25.00', $credit->credit_total);
        $this->assertSame('75.00', $fixture['invoice']->fresh()->balance_due);

        $this->amend($fixture, $this->serviceLine());
        $invoice = $fixture['invoice']->fresh();
        $this->assertSame($credit->id, $invoice->returns()->sole()->id);
        $this->assertSame('25.00', $invoice->credited_total);
        $this->assertSame('134.00', $invoice->balance_due);
        $this->assertDatabaseCount('crm_invoice_returns', 1);
        $this->assertDatabaseCount('crm_invoice_amendments', 1);
    }

    public function test_interstate_tax_rounding_http_validation_and_cross_tenant_product_injection_are_safe(): void
    {
        $fixture = $this->fixture();
        $fixture['invoice']->update(['place_of_supply_state_code' => '27']);
        $amendment = $this->amend($fixture, array_replace($this->productLine($fixture), ['quantity' => '3.000', 'unit_price' => '33.33', 'tax_rate' => '18']), warehouseId: $fixture['warehouse']->id);
        $this->assertSame('0.00', $amendment->cgst_added);
        $this->assertSame('0.00', $amendment->sgst_added);
        $this->assertSame('18.00', $amendment->igst_added);
        $this->assertSame('117.99', $amendment->amount_added);

        $other = $this->fixture();
        $response = $this->actingAs($fixture['manager'])->post(route('sales.invoices.amendments.store', $fixture['invoice']), [
            'expected_version' => 2, 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Attempted external product',
            'warehouse_id' => $fixture['warehouse']->id,
            'items' => [array_replace($this->productLine($fixture), ['product_id' => $other['product']->id])],
        ]);
        $response->assertSessionHasErrors('items.0.product_id');
        $this->assertDatabaseCount('crm_invoice_amendments', 1);
    }

    public function test_customer_communications_and_ai_read_refreshed_authoritative_balance(): void
    {
        $fixture = $this->fixture();
        $this->amend($fixture, $this->serviceLine());
        $invoice = $fixture['invoice']->fresh();
        $message = app(InvoiceShareService::class)->whatsapp($invoice, $fixture['manager'])['message'];
        $this->assertStringContainsString('Amount: INR 159.00', $message);
        $this->assertStringContainsString('Balance due: INR 159.00', $message);

        $context = app(BusinessIntelligenceContextService::class)->forIntent($fixture['manager'], 'finance', ['label' => 'today', 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString()], $fixture['branch']->id);
        $this->assertSame(15900, collect($context['facts'])->firstWhere('label', 'Customers owe')['value']);
    }

    /** @return array<string, mixed> */
    private function fixture(InvoiceStatus $status = InvoiceStatus::Issued): array
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $branch = $this->reportBranch($company, 'Amendment outlet');
        $manager = $this->reportUser($company, $branch, UserRole::Manager);
        $customer = CrmCustomer::create(['company_id' => $company->id, 'customer_code' => 'CRM-AMEND-'.$company->id, 'company_name' => 'Amendment Buyer', 'display_name' => 'Amendment Buyer', 'status' => 'active', 'created_by' => $manager->id, 'updated_by' => $manager->id]);
        $invoice = CrmInvoice::create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'invoice_number' => 'INV-AMEND-'.$company->id, 'billing_name' => $customer->display_name, 'currency' => 'INR',
            'tax_mode' => 'gst', 'supplier_state_code_snapshot' => '29', 'place_of_supply_state_code' => '29', 'subtotal' => '100.00', 'discount_total' => '0.00', 'taxable_total' => '100.00',
            'tax_total' => '0.00', 'grand_total' => '100.00', 'amount_paid' => '0.00', 'balance_due' => '100.00',
            'status' => $status, 'issue_date' => today(), 'due_date' => today()->addDays(7), 'created_by' => $manager->id, 'updated_by' => $manager->id,
        ]);
        $original = $invoice->items()->create(['name' => 'Original service', 'quantity' => '1.000', 'unit' => 'unit', 'unit_price' => '100.00', 'discount_type' => 'fixed', 'discount_value' => '0.00', 'discount_amount' => '0.00', 'tax_rate' => '0.000', 'tax_amount' => '0.00', 'line_subtotal' => '100.00', 'line_total' => '100.00', 'gross_sales_snapshot' => '100.00', 'net_sales_snapshot' => '100.00', 'cost_snapshot_status' => 'unavailable', 'sort_order' => 1]);
        $product = $this->reportProduct($company, $branch, 'Amended product', '30.00');
        $product->update(['selling_price' => '50.00']);
        $warehouse = $this->reportWarehouse($company, $branch, 'Amendment warehouse');
        $stock = $this->reportStockLevel($company, $branch, $warehouse, $product, '10.000');

        return compact('company', 'branch', 'manager', 'customer', 'invoice', 'original', 'product', 'warehouse', 'stock');
    }

    /** @return array<string, mixed> */
    private function serviceLine(string $name = 'Installation Charges'): array
    {
        return ['name' => $name, 'quantity' => '1.000', 'unit' => 'service', 'unit_price' => '50.00', 'discount_type' => 'fixed', 'discount_value' => '0.00', 'tax_rate' => '18.000'];
    }

    /** @param array<string, mixed> $fixture @return array<string, mixed> */
    private function productLine(array $fixture): array
    {
        return ['product_id' => $fixture['product']->id, 'name' => $fixture['product']->name, 'quantity' => '2.000', 'unit' => 'unit', 'unit_price' => '50.00', 'discount_type' => 'fixed', 'discount_value' => '0.00', 'tax_rate' => '0.000'];
    }

    /** @param array<string, mixed> $fixture @param array<string, mixed> $line */
    private function amend(array $fixture, array $line, ?string $key = null, int $expectedVersion = 1, ?int $warehouseId = null, bool $override = false): CrmInvoiceAmendment
    {
        return app(InvoiceAmendmentService::class)->finalize($fixture['manager'], $fixture['invoice']->id, [
            'expected_version' => $expectedVersion,
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'reason' => 'Additional services approved',
            'warehouse_id' => $warehouseId,
            'credit_limit_override' => $override,
            'credit_limit_override_reason' => $override ? 'Manager approved after credit review' : null,
            'items' => [$line],
        ]);
    }
}
