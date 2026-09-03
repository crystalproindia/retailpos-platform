<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\ExpenseTransaction;
use App\Models\Inventory\InventoryCategory;
use App\Models\Pos\PosSaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\ExpenseCategoryProvisioner;
use App\Services\Finance\ExpenseLedgerService;
use App\Services\Finance\ExpenseReceiptService;
use App\Services\Finance\FinancialPeriodResolver;
use App\Services\Reports\ProfitAndLossService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Concerns\BuildsReportingData;

class ExpenseLedgerCoreTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_profit_and_loss_composes_authoritative_sale_cogs_with_the_ledger_without_counting_inventory_purchases_as_expenses(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $branch = $this->reportBranch($company, 'Accounting outlet');
        $user = $this->reportUser($company, $branch);
        $warehouse = $this->reportWarehouse($company, $branch, 'Accounting warehouse');
        $product = $this->reportProduct($company, $branch, 'Accounting product', '60.00');
        $supplier = $this->reportSupplier($company, 'Inventory supplier');
        $this->reportPurchaseInvoice($company, $branch, $warehouse, $supplier, $user, 'PURCHASE-ONLY', '600.00', 'approved', '2026-08-01');

        $sale = $this->reportSale($company, $branch, $user, 'ACCOUNTING-SALE', '200.00', 'completed', '2026-08-15');
        $item = $this->reportSaleItem($sale, $product, null, '2.000', '200.00');
        $item->update([
            'gross_amount' => '200.00', 'taxable_amount' => '200.00', 'gross_sales_snapshot' => '200.00',
            'net_sales_snapshot' => '200.00', 'unit_cost_snapshot' => '60.00', 'total_cost_snapshot' => '120.00',
            'gross_profit_snapshot' => '80.00', 'cost_snapshot_status' => 'captured', 'cost_snapshot_method' => 'standard_cost',
        ]);

        $operating = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent', 'classification' => ExpenseCategory::OPERATING_EXPENSE, 'is_active' => true]);
        $income = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Interest', 'classification' => ExpenseCategory::OTHER_INCOME, 'is_active' => true]);
        $other = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Bank charge', 'classification' => ExpenseCategory::OTHER_EXPENSE, 'is_active' => true]);
        $ledger = app(ExpenseLedgerService::class);
        $draft = $ledger->createDraft($user, $this->draft($branch, $operating, '2026-08-15', '750.00'));
        $posted = $ledger->createDraft($user, $this->draft($branch, $operating, '2026-08-15', '40.00'));
        $otherIncome = $ledger->createDraft($user, $this->draft($branch, $income, '2026-08-15', '10.00'));
        $otherExpense = $ledger->createDraft($user, $this->draft($branch, $other, '2026-08-15', '5.00'));
        foreach ([$posted, $otherIncome, $otherExpense] as $entry) $ledger->post($entry, $user);
        $ledger->reverse($otherExpense, $user, 'Incorrect bank charge', '2026-08-15');

        $report = app(ProfitAndLossService::class)->report($user, ['ids' => null, 'warehouse_id' => null, 'label' => 'All Outlets'], $this->range('2026-08-01', '2026-08-31'), []);

        $this->assertSame(20000, $report['gross_sales']);
        $this->assertSame(20000, $report['net_sales']);
        $this->assertSame(12000, $report['cogs']);
        $this->assertSame(8000, $report['gross_profit']);
        $this->assertSame(4000, $report['operating_expenses']);
        $this->assertSame(4000, $report['operating_profit']);
        $this->assertSame(1000, $report['other_income']);
        $this->assertSame(0, $report['other_expenses']);
        $this->assertSame(5000, $report['net_profit']);
        $this->assertSame(ExpenseTransaction::DRAFT, $draft->fresh()->status);
        $this->assertSame(40.0, $report['gross_margin_percent']);
        $this->assertSame(20.0, $report['operating_margin_percent']);
        $this->assertSame(25.0, $report['net_margin_percent']);
        $this->assertSame(1, \App\Models\Purchases\PurchaseInvoice::query()->count());
    }

    public function test_reversed_original_and_its_cross_period_reversal_are_reported_in_their_own_accounting_periods(): void
    {
        [$user, $branch, $category] = $this->fixture();
        $ledger = app(ExpenseLedgerService::class);
        $original = $ledger->createDraft($user, $this->draft($branch, $category, '2026-08-31', '100.00'));
        $ledger->post($original, $user);
        $ledger->reverse($original, $user, 'Duplicate entry', '2026-09-02');

        $service = app(ProfitAndLossService::class);
        $scope = ['ids' => null, 'warehouse_id' => null, 'label' => 'All Outlets'];
        $august = $service->report($user, $scope, $this->range('2026-08-01', '2026-08-31'), []);
        $september = $service->report($user, $scope, $this->range('2026-09-01', '2026-09-30'), []);

        $this->assertSame(10000, $august['operating_expenses']);
        $this->assertSame(-10000, $september['operating_expenses']);
        $this->assertSame(ExpenseTransaction::REVERSED, $original->fresh()->status);
        $reversal = ExpenseTransaction::query()->where('reverses_expense_transaction_id', $original->id)->firstOrFail();
        $this->assertSame('2026-09-02', $reversal->transaction_date->toDateString());
        $this->assertSame('-100.00', $reversal->amount);
    }

    public function test_same_period_reversal_nets_to_zero_without_hiding_the_original_entry(): void
    {
        [$user, $branch, $category] = $this->fixture();
        $ledger = app(ExpenseLedgerService::class);
        $original = $ledger->createDraft($user, $this->draft($branch, $category, '2026-08-31', '100.00'));
        $ledger->post($original, $user);
        $ledger->reverse($original, $user, 'Correction', '2026-08-31');

        $report = app(ProfitAndLossService::class)->report($user, ['ids' => null, 'warehouse_id' => null], $this->range('2026-08-01', '2026-08-31'), []);

        $this->assertSame(0, $report['operating_expenses']);
        $this->assertSame(2, ExpenseTransaction::query()->count());
        $this->assertSame(ExpenseTransaction::REVERSED, $original->fresh()->status);
    }

    public function test_reversal_rejects_an_invalid_accounting_date(): void
    {
        [$user, $branch, $category] = $this->fixture();
        $ledger = app(ExpenseLedgerService::class);
        $entry = $ledger->createDraft($user, $this->draft($branch, $category, '2026-08-31', '100.00'));
        $ledger->post($entry, $user);

        try {
            $ledger->reverse($entry, $user, 'Correction', 'not-a-date');
            $this->fail('Expected invalid reversal date to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transaction_date', $exception->errors());
        }
    }

    public function test_financial_period_resolver_uses_company_timezone_and_existing_fiscal_year_setting(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        Setting::create(['company_id' => $company->id, 'group' => 'business', 'key' => 'fiscal_year_start', 'value' => ['value' => 'July']]);
        $resolver = app(FinancialPeriodResolver::class);

        $period = $resolver->resolve($company, ['period' => 'financial_year'], CarbonImmutable::parse('2026-06-30 17:00:00', 'UTC'));
        $this->assertSame('2025-07-01', $period['from']->toDateString());
        $this->assertSame('2026-06-30', $period['to']->toDateString());
        $this->assertSame('Asia/Kolkata', $period['timezone']);

        $next = $resolver->resolve($company, ['period' => 'financial_year'], CarbonImmutable::parse('2026-07-01 00:30:00', 'Asia/Kolkata'));
        $this->assertSame('2026-07-01', $next['from']->toDateString());
        $this->assertSame('2027-06-30', $next['to']->toDateString());
    }

    public function test_custom_period_requires_valid_ordered_dates(): void
    {
        $company = Company::factory()->create();
        $resolver = app(FinancialPeriodResolver::class);

        try {
            $resolver->resolve($company, ['period' => 'custom', 'date_from' => '2026-08-02', 'date_to' => '2026-08-01']);
            $this->fail('Expected invalid custom range to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('date_to', $exception->errors());
        }
    }

    public function test_receipts_are_private_tenant_scoped_and_immutable_after_posting(): void
    {
        Storage::fake('local');
        [$user, $branch, $category] = $this->fixture();
        $entry = app(ExpenseLedgerService::class)->createDraft($user, $this->draft($branch, $category, '2026-08-01', '100.00'));
        $receipts = app(ExpenseReceiptService::class);
        $first = UploadedFile::fake()->createWithContent('first.pdf', '%PDF-1.4 receipt');
        $entry = $receipts->replaceDraft($entry, $user, $first);
        $firstPath = $entry->receipt_path;
        Storage::disk('local')->assertExists($firstPath);

        $entry = $receipts->replaceDraft($entry, $user, UploadedFile::fake()->createWithContent('replacement.pdf', '%PDF-1.4 replacement'));
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($entry->receipt_path);
        app(ExpenseLedgerService::class)->post($entry, $user);

        $this->expectException(ValidationException::class);
        $receipts->replaceDraft($entry->fresh(), $user, UploadedFile::fake()->createWithContent('late.pdf', '%PDF-1.4 late'));
    }

    public function test_receipt_route_rejects_cross_tenant_and_outlet_access(): void
    {
        Storage::fake('local');
        [$user, $branch, $category] = $this->fixture();
        $entry = app(ExpenseLedgerService::class)->createDraft($user, $this->draft($branch, $category, '2026-08-01', '100.00'));
        $entry = app(ExpenseReceiptService::class)->replaceDraft($entry, $user, UploadedFile::fake()->createWithContent('receipt.pdf', '%PDF-1.4'));
        $otherCompany = Company::factory()->create();
        $other = User::factory()->create(['company_id' => $otherCompany->id, 'role' => UserRole::Manager]);
        $otherBranch = Branch::factory()->create(['company_id' => $user->company_id, 'is_active' => true]);
        $outletManager = User::factory()->create(['company_id' => $user->company_id, 'branch_id' => $otherBranch->id, 'role' => UserRole::Manager]);
        $staff = User::factory()->create(['company_id' => $user->company_id, 'branch_id' => $branch->id, 'role' => UserRole::Staff]);

        $this->actingAs($other)->get(route('finance.expenses.receipt', $entry))->assertNotFound();
        $this->actingAs($outletManager)->get(route('finance.expenses.receipt', $entry))->assertForbidden();
        $this->actingAs($staff)->get(route('finance.expenses.receipt', $entry))->assertForbidden();
        $this->actingAs($user)->get(route('finance.expenses.receipt', $entry))->assertOk();
    }

    public function test_receipt_upload_rejects_invalid_mime_without_losing_the_existing_draft_receipt(): void
    {
        Storage::fake('local');
        [$user, $branch, $category] = $this->fixture();
        $entry = app(ExpenseLedgerService::class)->createDraft($user, $this->draft($branch, $category, '2026-08-01', '100.00'));
        $receipts = app(ExpenseReceiptService::class);
        $entry = $receipts->replaceDraft($entry, $user, UploadedFile::fake()->createWithContent('receipt.pdf', '%PDF-1.4'));
        $path = $entry->receipt_path;

        try {
            $receipts->replaceDraft($entry, $user, UploadedFile::fake()->create('unsafe.txt', 4, 'text/plain'));
            $this->fail('Expected invalid receipt MIME type to be rejected.');
        } catch (ValidationException) {
            $this->assertSame($path, $entry->fresh()->receipt_path);
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_default_categories_are_idempotent_without_overwriting_existing_rows(): void
    {
        $company = Company::factory()->create();
        $provisioner = app(ExpenseCategoryProvisioner::class);
        $provisioner->provision($company);
        $this->assertSame(18, ExpenseCategory::query()->where('company_id', $company->id)->count());
        $category = ExpenseCategory::query()->where('company_id', $company->id)->firstOrFail();
        $category->update(['is_active' => false, 'classification' => ExpenseCategory::OTHER_INCOME]);
        $provisioner->provision($company);

        $this->assertSame(18, ExpenseCategory::query()->where('company_id', $company->id)->count());
        $this->assertFalse($category->fresh()->is_active);
        $this->assertSame(ExpenseCategory::OTHER_INCOME, $category->fresh()->classification);
    }

    /** @return array{User, Branch, ExpenseCategory} */
    private function fixture(): array
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $branch = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Administrator]);
        $category = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Store utilities', 'classification' => ExpenseCategory::OPERATING_EXPENSE, 'is_active' => true]);

        return [$user, $branch, $category];
    }

    /** @return array<string, mixed> */
    private function draft(Branch $branch, ExpenseCategory $category, string $date, string $amount): array
    {
        return ['branch_id' => $branch->id, 'expense_category_id' => $category->id, 'transaction_date' => $date, 'amount' => $amount, 'description' => 'Utility payment'];
    }

    /** @return array{from: CarbonImmutable, to: CarbonImmutable, timezone: string} */
    private function range(string $from, string $to): array
    {
        return ['from' => CarbonImmutable::parse($from, 'Asia/Kolkata')->startOfDay(), 'to' => CarbonImmutable::parse($to, 'Asia/Kolkata')->endOfDay(), 'timezone' => 'Asia/Kolkata'];
    }
}
