<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Finance\ExpenseCategory;
use App\Models\User;
use App\Services\Finance\ExpenseLedgerService;
use App\Services\Finance\ProfitAndLossPdfService;
use App\Services\Reports\ProfitAndLossService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class ProfitAndLossReportingQualificationTest extends TestCase
{
    use BuildsReportingData;
    use RefreshDatabase;

    public function test_html_csv_and_pdf_share_the_normalized_profitable_period_with_correct_percentages(): void
    {
        [$user, $report] = $this->profitableFixture();
        $query = ['period' => 'custom', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'outlet_id' => 'all'];

        $this->assertSame(20000, $report['gross_sales']);
        $this->assertSame(20000, $report['net_sales']);
        $this->assertSame(12000, $report['cogs']);
        $this->assertSame(8000, $report['gross_profit']);
        $this->assertSame(4000, $report['operating_expenses']);
        $this->assertSame(4000, $report['operating_profit']);
        $this->assertSame(1000, $report['other_income']);
        $this->assertSame(0, $report['other_expenses']);
        $this->assertSame(5000, $report['net_profit']);
        $this->assertSame(40.0, $report['gross_margin_percent']);
        $this->assertSame(20.0, $report['operating_margin_percent']);
        $this->assertSame(25.0, $report['net_margin_percent']);

        $html = $this->actingAs($user)->get(route('finance.profit-and-loss.index', $query));
        $html->assertOk()
            ->assertViewHas('report', fn (array $actual) => $actual === $report)
            ->assertSee('₹200.00')
            ->assertSee('40%')
            ->assertDontSee('4000%');

        $csv = $this->actingAs($user)->get(route('finance.profit-and-loss.csv', $query));
        $csv->assertOk();
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('"Gross Sales",200', $csvContent);
        $this->assertStringContainsString('"Gross Profit",80', $csvContent);
        $this->assertStringContainsString('"Gross Margin %",40', $csvContent);
        $this->assertStringContainsString('"Operating Margin %",20', $csvContent);
        $this->assertStringContainsString('"Net Margin %",25', $csvContent);
        $this->assertStringNotContainsString('4000', $csvContent);

        $pdfSource = view('pdf.finance-profit-and-loss', [
            'company' => $user->company,
            'report' => $report,
            'scope' => 'Company / Consolidated',
        ])->render();
        $this->assertStringContainsString('₹200.00', $pdfSource);
        $this->assertStringContainsString('Gross Profit (40%)', $pdfSource);
        $this->assertStringContainsString('Operating Profit (20%)', $pdfSource);
        $this->assertStringContainsString('NET PROFIT (25%)', $pdfSource);

        $pdf = $this->actingAs($user)->get(route('finance.profit-and-loss.pdf', $query));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_negative_and_zero_periods_keep_signed_amounts_and_safe_null_margins_across_presentation_sources(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $branch = $this->reportBranch($company, 'Loss outlet');
        $user = $this->reportUser($company, $branch);
        $operating = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Loss rent', 'classification' => ExpenseCategory::OPERATING_EXPENSE, 'is_active' => true]);
        $other = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Loss charge', 'classification' => ExpenseCategory::OTHER_EXPENSE, 'is_active' => true]);
        $ledger = app(ExpenseLedgerService::class);
        foreach ([[$operating, '75.00'], [$other, '25.00']] as [$category, $amount]) {
            $entry = $ledger->createDraft($user, $this->draft($branch, $category, '2026-08-15', $amount));
            $ledger->post($entry, $user);
        }

        $service = app(ProfitAndLossService::class);
        $scope = ['ids' => null, 'warehouse_id' => null, 'label' => 'Company / Consolidated'];
        $loss = $service->report($user, $scope, $this->range('2026-08-01', '2026-08-31'));
        $empty = $service->report($user, $scope, $this->range('2026-09-01', '2026-09-30'));

        $this->assertSame(-7500, $loss['operating_profit']);
        $this->assertSame(-10000, $loss['net_profit']);
        $this->assertSame(0, $empty['net_profit']);
        $this->assertNull($empty['gross_margin_percent']);
        $this->assertNull($empty['operating_margin_percent']);
        $this->assertNull($empty['net_margin_percent']);

        $html = $this->actingAs($user)
            ->get(route('finance.profit-and-loss.index', ['period' => 'custom', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'outlet_id' => 'all']))
            ->assertOk()
            ->getContent();
        $pdf = view('pdf.finance-profit-and-loss', ['company' => $company, 'report' => $loss, 'scope' => $scope['label']])->render();
        $this->assertStringContainsString('₹-75.00', $html);
        $this->assertStringContainsString('₹-100.00', $html);
        $this->assertStringContainsString('₹-75.00', $pdf);
        $this->assertStringContainsString('₹-100.00', $pdf);

        $emptyPdf = app(ProfitAndLossPdfService::class)->render($company, $empty, $scope['label'])->output();
        $this->assertNotSame('', $emptyPdf);
    }

    public function test_direct_pnl_exports_and_expense_csv_remain_authorized_and_sanitize_formula_cells(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $branch = $this->reportBranch($company, 'Finance outlet');
        $manager = $this->reportUser($company, $branch);
        $staff = $this->reportUser($company, $branch, UserRole::Staff);
        $category = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Safe export', 'classification' => ExpenseCategory::OPERATING_EXPENSE, 'is_active' => true]);
        $entry = app(ExpenseLedgerService::class)->createDraft($manager, [
            ...$this->draft($branch, $category, '2026-08-15', '12.50'),
            'payee' => '=SUM(1,1)',
            'reference' => '+unsafe',
            'description' => '@unsafe',
        ]);

        $this->actingAs($staff)->get(route('finance.profit-and-loss.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('finance.profit-and-loss.csv'))->assertForbidden();
        $this->actingAs($staff)->get(route('finance.profit-and-loss.pdf'))->assertForbidden();

        $csv = $this->actingAs($manager)->get(route('finance.expenses.csv'));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString(sprintf('%c=SUM(1,1)', 39), $content);
        $this->assertStringContainsString("'+unsafe", $content);
        $this->assertStringContainsString("'@unsafe", $content);
        $this->assertStringNotContainsString('Company ID', $content);
        $this->assertStringNotContainsString('Receipt Path', $content);
    }

    /** @return array{User, array<string, mixed>} */
    private function profitableFixture(): array
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
        $branch = $this->reportBranch($company, 'Accounting outlet');
        $user = $this->reportUser($company, $branch);
        $product = $this->reportProduct($company, $branch, 'Accounting product', '60.00');
        $sale = $this->reportSale($company, $branch, $user, 'ACCOUNTING-SALE', '200.00', 'completed', '2026-08-15');
        $item = $this->reportSaleItem($sale, $product, null, '2.000', '200.00');
        $item->update([
            'gross_amount' => '200.00', 'taxable_amount' => '200.00', 'gross_sales_snapshot' => '200.00',
            'net_sales_snapshot' => '200.00', 'unit_cost_snapshot' => '60.00', 'total_cost_snapshot' => '120.00',
            'gross_profit_snapshot' => '80.00', 'cost_snapshot_status' => 'captured', 'cost_snapshot_method' => 'standard_cost',
        ]);

        $operating = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent', 'classification' => ExpenseCategory::OPERATING_EXPENSE, 'is_active' => true]);
        $income = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Interest', 'classification' => ExpenseCategory::OTHER_INCOME, 'is_active' => true]);
        $ledger = app(ExpenseLedgerService::class);
        foreach ([[$operating, '40.00'], [$income, '10.00']] as [$category, $amount]) {
            $entry = $ledger->createDraft($user, $this->draft($branch, $category, '2026-08-15', $amount));
            $ledger->post($entry, $user);
        }

        return [$user, app(ProfitAndLossService::class)->report($user, ['ids' => null, 'warehouse_id' => null, 'label' => 'Company / Consolidated'], $this->range('2026-08-01', '2026-08-31'))];
    }

    /** @return array<string, mixed> */
    private function draft(Branch $branch, ExpenseCategory $category, string $date, string $amount): array
    {
        return ['branch_id' => $branch->id, 'expense_category_id' => $category->id, 'transaction_date' => $date, 'amount' => $amount, 'description' => 'Reporting qualification entry'];
    }

    /** @return array{from: CarbonImmutable, to: CarbonImmutable, timezone: string} */
    private function range(string $from, string $to): array
    {
        return ['from' => CarbonImmutable::parse($from, 'Asia/Kolkata')->startOfDay(), 'to' => CarbonImmutable::parse($to, 'Asia/Kolkata')->endOfDay(), 'timezone' => 'Asia/Kolkata'];
    }
}
