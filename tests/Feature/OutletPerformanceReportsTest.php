<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class OutletPerformanceReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_administrator_outlet_performance_reconciles_to_completed_pos_sales_and_manager_scope_remains_single_outlet(): void
    {
        $company = Company::factory()->create();
        $first = $this->reportBranch($company, 'First Performance Outlet');
        $second = $this->reportBranch($company, 'Second Performance Outlet');
        $administrator = $this->reportUser($company, $first);
        $manager = $this->reportUser($company, $first, UserRole::Manager);
        $firstSale = $this->reportSale($company, $first, $administrator, 'PERF-FIRST-ONE', '10.00');
        $this->reportSale($company, $first, $administrator, 'PERF-FIRST-TWO', '20.00');
        $this->reportSale($company, $second, $administrator, 'PERF-SECOND', '40.00');
        $firstSale->update(['discount_amount' => '1.00']);

        $administratorReport = $this->reportFor($administrator, 'outlets', ['outlet_id' => 'all']);
        $managerReport = $this->reportFor($manager, 'outlets');
        $allRows = collect($administratorReport['detail']['rows'])->keyBy('outlet');

        $this->assertSame(3000, $allRows['First Performance Outlet']['net_sales']);
        $this->assertSame(100, $allRows['First Performance Outlet']['discounts']);
        $this->assertSame(1500, $allRows['First Performance Outlet']['average_order_value']);
        $this->assertSame(4000, $allRows['Second Performance Outlet']['net_sales']);
        $this->assertSame(['First Performance Outlet'], array_column($managerReport['detail']['rows'], 'outlet'));
    }
}
