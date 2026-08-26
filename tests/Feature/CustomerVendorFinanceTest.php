<?php

namespace Tests\Feature;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\Crm\InvoiceStatus;
use App\Enums\Purchases\SupplierType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceReturn;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\Supplier;
use App\Models\Purchases\SupplierPayment;
use App\Models\User;
use App\Services\Finance\CreditLimitService;
use App\Services\Finance\CustomerCreditService;
use App\Services\Finance\CustomerPaymentAllocationService;
use App\Services\Finance\PayableService;
use App\Services\Finance\ReceivableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerVendorFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receivable_aging_and_outstanding_use_remaining_authorized_balance(): void
    {
        [$admin, $branch, $customer] = $this->customerFixture(UserRole::Administrator);
        $current = $this->invoice($admin, $branch, $customer, 'INV-CURRENT', '500.00', today()->addDay());
        $overdue = $this->invoice($admin, $branch, $customer, 'INV-OLD', '300.00', today()->subDays(35));
        $overdue->update(['amount_paid' => '100.00', 'balance_due' => '200.00', 'status' => InvoiceStatus::PartiallyPaid]);
        $snapshot = app(ReceivableService::class)->snapshot($admin);

        $this->assertSame(70000, $snapshot['metrics']['outstanding']);
        $this->assertSame(20000, $snapshot['metrics']['overdue']);
        $this->assertSame(50000, $snapshot['aging']['current']);
        $this->assertSame(20000, $snapshot['aging']['31_60']);
        $this->assertSame(2, $snapshot['customers'][0]['document_count']);
        $this->assertSame($current->company_id, $overdue->company_id);
    }

    public function test_customer_payment_supports_multiple_allocations_unapplied_credit_and_idempotency(): void
    {
        [$admin, $branch, $customer] = $this->customerFixture(UserRole::Administrator);
        $first = $this->invoice($admin, $branch, $customer, 'INV-ONE', '200.00', today()->subDay());
        $second = $this->invoice($admin, $branch, $customer, 'INV-TWO', '300.00', today()->addDay());
        $key = (string) Str::uuid();
        $payload = ['customer_id' => $customer->id, 'amount' => '350.00', 'currency' => 'INR', 'payment_date' => today()->toDateString(), 'payment_method' => 'bank_transfer', 'idempotency_key' => $key, 'allocations' => [['invoice_id' => $first->id, 'amount' => '200.00'], ['invoice_id' => $second->id, 'amount' => '100.00']]];
        $payment = app(CustomerPaymentAllocationService::class)->record($admin, $payload);
        $retry = app(CustomerPaymentAllocationService::class)->record($admin, $payload);

        $this->assertSame($payment->id, $retry->id);
        $this->assertSame('300.00', $payment->allocated_amount);
        $this->assertSame('50.00', $payment->unallocated_amount);
        $this->assertSame('0.00', $first->fresh()->balance_due);
        $this->assertSame('200.00', $second->fresh()->balance_due);
        $this->assertSame(5000, app(ReceivableService::class)->availableCreditMinor($admin, $customer->id));
        $this->assertDatabaseCount('crm_invoice_payment_allocations', 2);
        $this->assertDatabaseCount('crm_invoice_payments', 1);
    }

    public function test_unallocated_payment_can_be_reconciled_later_without_duplicate_allocation(): void
    {
        [$admin, $branch, $customer] = $this->customerFixture(UserRole::Administrator);
        $invoice = $this->invoice($admin, $branch, $customer, 'INV-LATER', '125.00', today());
        $payment = app(CustomerPaymentAllocationService::class)->record($admin, ['customer_id' => $customer->id, 'amount' => '125.00', 'currency' => 'INR', 'payment_date' => today()->toDateString(), 'payment_method' => 'bank_transfer', 'idempotency_key' => (string) Str::uuid(), 'allocations' => []]);
        $requestKey = (string) Str::uuid();

        $this->actingAs($admin)->get(route('finance.reconciliation.index'))->assertOk()->assertSee($payment->payment_reference);
        $response = $this->actingAs($admin)->post(route('finance.customer-payments.allocations.store', $payment), ['idempotency_key' => $requestKey, 'allocations' => [['invoice_id' => $invoice->id, 'amount' => '125.00']]]);
        $response->assertRedirect(route('finance.reconciliation.index'));
        $this->actingAs($admin)->post(route('finance.customer-payments.allocations.store', $payment), ['idempotency_key' => $requestKey, 'allocations' => [['invoice_id' => $invoice->id, 'amount' => '125.00']]])->assertRedirect(route('finance.reconciliation.index'));

        $this->assertSame('0.00', $invoice->fresh()->balance_due);
        $this->assertSame('0.00', $payment->fresh()->unallocated_amount);
        $this->assertDatabaseCount('crm_invoice_payment_allocations', 1);
    }

    public function test_payment_and_credit_cannot_be_overallocated(): void
    {
        [$admin, $branch, $customer] = $this->customerFixture(UserRole::Administrator);
        $invoice = $this->invoice($admin, $branch, $customer, 'INV-LIMIT', '100.00', today());

        try {
            app(CustomerPaymentAllocationService::class)->record($admin, ['customer_id' => $customer->id, 'amount' => '50.00', 'currency' => 'INR', 'payment_date' => today()->toDateString(), 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid(), 'allocations' => [['invoice_id' => $invoice->id, 'amount' => '60.00']]]);
            $this->fail('Expected payment allocation validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocations', $exception->errors());
        }
        $credit = $this->credit($admin, $branch, $customer, $invoice, '40.00');
        $this->expectException(ValidationException::class);
        app(CustomerCreditService::class)->apply($admin, $credit->id, $invoice->id, '50.00', (string) Str::uuid());
    }

    public function test_customer_credit_is_explicit_atomic_and_does_not_create_a_payment_or_refund(): void
    {
        [$admin, $branch, $customer] = $this->customerFixture(UserRole::Administrator);
        $invoice = $this->invoice($admin, $branch, $customer, 'INV-CREDIT', '180.00', today());
        $credit = $this->credit($admin, $branch, $customer, $invoice, '80.00');
        $allocation = app(CustomerCreditService::class)->apply($admin, $credit->id, $invoice->id, '50.00', (string) Str::uuid());

        $this->assertSame('50.00', $allocation->amount);
        $this->assertSame('130.00', $invoice->fresh()->balance_due);
        $this->assertSame(3000, app(ReceivableService::class)->availableCreditMinor($admin, $customer->id));
        $this->assertDatabaseCount('crm_invoice_payments', 0);
        $this->assertDatabaseCount('crm_customer_credit_allocations', 1);
    }

    public function test_customer_statement_has_deterministic_running_balance_pdf_and_safe_csv(): void
    {
        [$admin, $branch, $customer] = $this->customerFixture(UserRole::Administrator);
        $invoice = $this->invoice($admin, $branch, $customer, '=INJECT', '250.00', today()->subDays(2));
        $invoice->update(['issue_date' => today()->subDays(2)]);
        app(CustomerPaymentAllocationService::class)->record($admin, ['customer_id' => $customer->id, 'amount' => '100.00', 'currency' => 'INR', 'payment_date' => today()->subDay()->toDateString(), 'payment_method' => 'upi', 'idempotency_key' => (string) Str::uuid(), 'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.00']]]);
        $statement = app(ReceivableService::class)->statement($admin, $customer, today()->subMonth()->toImmutable(), today()->toImmutable());

        $this->assertSame(0, $statement['opening']);
        $this->assertSame(15000, $statement['closing']);
        $this->assertSame(['Invoice', 'Payment'], $statement['rows']->pluck('type')->all());
        $this->actingAs($admin)->get(route('finance.customer-statements.pdf', $customer))->assertOk()->assertHeader('content-type', 'application/pdf');
        $csv = $this->actingAs($admin)->get(route('finance.customer-statements.csv', $customer));
        $csv->assertOk();
        $this->assertStringContainsString("'=INJECT", $csv->streamedContent());
        $agingCsv = $this->actingAs($admin)->get(route('finance.receivables.csv'));
        $agingCsv->assertOk();
        $this->assertStringContainsString("'=INJECT", $agingCsv->streamedContent());
    }

    public function test_credit_limit_blocks_ordinary_user_and_authorized_override_is_audited(): void
    {
        [$manager, $branch, $customer] = $this->customerFixture(UserRole::Manager);
        $customer->update(['credit_limit' => '100.00']);
        $invoice = $this->invoice($manager, $branch, $customer, 'INV-LIMIT-ISSUE', '150.00', today(), InvoiceStatus::Draft);
        $sales = User::factory()->for($manager->company)->create(['branch_id' => $branch->id, 'role' => UserRole::Sales]);

        try {
            app(CreditLimitService::class)->assertCanIssue($invoice, $sales);
            $this->fail('Expected credit limit validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('credit_limit', $exception->errors());
        }
        app(CreditLimitService::class)->assertCanIssue($invoice, $manager, true, 'Approved after customer review');
        $this->assertDatabaseHas('audit_logs', ['event' => 'finance.customer_credit_limit.overridden', 'auditable_id' => $invoice->id]);
    }

    public function test_tenant_and_outlet_scope_rejects_forged_finance_access(): void
    {
        [$manager, $branch, $customer] = $this->customerFixture(UserRole::Manager);
        $otherBranch = Branch::factory()->for($manager->company)->create();
        $this->invoice($manager, $branch, $customer, 'INV-VISIBLE', '100.00', today());
        $hidden = $this->invoice($manager, $otherBranch, $customer, 'INV-HIDDEN', '900.00', today());
        [$otherAdmin, $foreignBranch, $foreignCustomer] = $this->customerFixture(UserRole::Administrator);

        $this->assertSame(10000, app(ReceivableService::class)->snapshot($manager)['metrics']['outstanding']);
        $this->actingAs($manager)->get(route('finance.receivables.index', ['outlet_id' => $otherBranch->id]))->assertOk()->assertDontSee($hidden->invoice_number);
        $this->actingAs($manager)->get(route('finance.customer-statements.show', $foreignCustomer))->assertNotFound();
        $this->assertNotSame($manager->company_id, $otherAdmin->company_id);
        $this->assertNotSame($branch->id, $foreignBranch->id);
    }

    public function test_supplier_payables_aging_statement_and_pdf_use_authorized_purchase_records(): void
    {
        [$admin, $branch] = $this->userFixture(UserRole::Administrator);
        $supplier = $this->supplier($admin);
        $invoice = $this->purchaseInvoice($admin, $branch, $supplier, 'PINV-001', '500.00', today()->subDays(40));
        $payment = SupplierPayment::create(['company_id' => $admin->company_id, 'supplier_id' => $supplier->id, 'branch_id' => $branch->id, 'payment_number' => 'PAY-001', 'payment_date' => today(), 'currency' => 'INR', 'payment_type' => 'invoice_payment', 'payment_method' => 'bank_transfer', 'amount' => '200.00', 'unallocated_amount' => '0.00', 'status' => 'recorded', 'recorded_by' => $admin->id]);
        $payment->allocations()->create(['purchase_invoice_id' => $invoice->id, 'amount' => '200.00']);
        $invoice->update(['paid_total' => '200.00', 'outstanding_total' => '300.00', 'status' => 'partially_paid']);
        $snapshot = app(PayableService::class)->snapshot($admin);
        $statement = app(PayableService::class)->statement($admin, $supplier, today()->subYear()->toImmutable(), today()->toImmutable());

        $this->assertSame(30000, $snapshot['metrics']['payable']);
        $this->assertSame(30000, $snapshot['aging']['31_60']);
        $this->assertSame(30000, $statement['closing']);
        $this->actingAs($admin)->get(route('finance.supplier-statements.pdf', $supplier))->assertOk()->assertHeader('content-type', 'application/pdf');
        $payableCsv = $this->actingAs($admin)->get(route('finance.payables.csv'));
        $payableCsv->assertOk();
        $this->assertStringContainsString('PINV-001', $payableCsv->streamedContent());
        $this->assertStringContainsString('300.00', $payableCsv->streamedContent());
    }

    public function test_permissions_hide_finance_from_sales_and_routes_remain_available_to_management(): void
    {
        [$manager] = $this->customerFixture(UserRole::Manager);
        $sales = User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Sales]);

        $this->actingAs($manager)->get(route('finance.receivables.index'))->assertOk()->assertSee('Money customers owe you');
        $this->actingAs($manager)->get(route('finance.payables.index'))->assertOk()->assertSee('Money you owe suppliers');
        $this->actingAs($sales)->get(route('finance.receivables.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('finance.reconciliation.index'))->assertForbidden();
    }

    /** @return array{User,Branch,CrmCustomer} */
    private function customerFixture(UserRole $role): array
    {
        [$user, $branch] = $this->userFixture($role);
        $customer = CrmCustomer::create(['company_id' => $user->company_id, 'customer_code' => 'CUS-'.$user->company_id, 'company_name' => 'ABC Traders', 'display_name' => 'Asha', 'email' => 'asha'.$user->company_id.'@example.test', 'phone' => '900000000'.$user->company_id, 'status' => CrmCustomerStatus::Active, 'source' => 'manual', 'created_by' => $user->id, 'updated_by' => $user->id]);

        return [$user, $branch, $customer];
    }

    /** @return array{User,Branch} */
    private function userFixture(UserRole $role): array
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create(['is_active' => true]);
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role, 'is_active' => true]);

        return [$user, $branch];
    }

    private function invoice(User $user, Branch $branch, CrmCustomer $customer, string $number, string $total, $due, InvoiceStatus $status = InvoiceStatus::Issued): CrmInvoice
    {
        return CrmInvoice::create(['company_id' => $user->company_id, 'branch_id' => $branch->id, 'customer_id' => $customer->id, 'invoice_number' => $number, 'billing_name' => $customer->display_name, 'billing_company' => $customer->company_name, 'currency' => 'INR', 'subtotal' => $total, 'taxable_total' => $total, 'grand_total' => $total, 'amount_paid' => '0.00', 'credited_total' => '0.00', 'balance_due' => $total, 'status' => $status, 'issue_date' => today(), 'due_date' => $due, 'created_by' => $user->id, 'updated_by' => $user->id]);
    }

    private function credit(User $user, Branch $branch, CrmCustomer $customer, CrmInvoice $invoice, string $amount): CrmInvoiceReturn
    {
        return CrmInvoiceReturn::create(['company_id' => $user->company_id, 'branch_id' => $branch->id, 'invoice_id' => $invoice->id, 'customer_id' => $customer->id, 'credit_note_number' => 'CN-'.Str::random(6), 'financial_year' => '2026-27', 'issue_date' => today(), 'status' => 'finalized', 'currency' => 'INR', 'credit_total' => $amount, 'receivable_credit_applied' => '0.00', 'customer_credit_due' => $amount, 'reason_code' => 'customer_return', 'idempotency_key' => hash('sha256', Str::uuid()->toString()), 'company_name_snapshot' => $user->company->name, 'customer_name_snapshot' => $customer->display_name, 'created_by' => $user->id, 'finalized_by' => $user->id, 'finalized_at' => now()]);
    }

    private function supplier(User $user): Supplier
    {
        return Supplier::create(['company_id' => $user->company_id, 'code' => 'SUP-001', 'name' => 'Supply House', 'supplier_type' => SupplierType::Distributor, 'default_currency' => 'INR', 'is_active' => true]);
    }

    private function purchaseInvoice(User $user, Branch $branch, Supplier $supplier, string $number, string $total, $due): PurchaseInvoice
    {
        return PurchaseInvoice::create(['company_id' => $user->company_id, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'invoice_number' => $number, 'supplier_invoice_number' => 'V-'.$number, 'supplier_invoice_date' => today(), 'financial_year' => '2026-27', 'status' => 'approved', 'currency' => 'INR', 'grand_total' => $total, 'paid_total' => '0.00', 'outstanding_total' => $total, 'due_date' => $due, 'created_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now()]);
    }
}
