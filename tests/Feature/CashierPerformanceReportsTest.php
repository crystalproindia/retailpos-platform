<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class CashierPerformanceReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_cashier_performance_reports_operational_sales_only_for_the_authorized_outlet_scope(): void
    {
        $company = Company::factory()->create();
        $assigned = $this->reportBranch($company, 'Assigned Cashier Outlet');
        $unassigned = $this->reportBranch($company, 'Unassigned Cashier Outlet');
        $administrator = $this->reportUser($company, $assigned);
        $cashier = $this->reportUser($company, $assigned, UserRole::Sales);
        $otherCashier = $this->reportUser($company, $unassigned, UserRole::Sales);
        $firstSale = $this->reportSale($company, $assigned, $cashier, 'CASHIER-ONE', '10.00');
        $this->reportSale($company, $assigned, $cashier, 'CASHIER-TWO', '20.00');
        $this->reportSale($company, $unassigned, $otherCashier, 'CASHIER-HIDDEN', '99.00');
        $firstSale->update(['discount_amount' => '2.00']);

        $report = $this->reportFor($administrator, 'cashiers', ['outlet_id' => $assigned->id]);
        $row = collect($report['detail']['rows'])->firstWhere('cashier', $cashier->name);

        $this->assertSame(2, $row['sales_count']);
        $this->assertSame(3000, $row['net_sales']);
        $this->assertSame(200, $row['discounts']);
        $this->assertSame(1500, $row['average_order_value']);
        $this->assertNotContains($otherCashier->name, array_column($report['detail']['rows'], 'cashier'));
    }
}
