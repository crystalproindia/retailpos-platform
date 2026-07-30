<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class PurchaseReturnReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_only_approved_purchase_returns_are_included_with_exact_quantity_and_minor_unit_amounts(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Returns Report Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Returns Report Warehouse');
        $supplier = $this->reportSupplier($company, 'Returns Report Supplier');
        $product = $this->reportProduct($company, $outlet, 'Returns Report Product', '19.99');

        $this->reportPurchaseReturn($company, $outlet, $warehouse, $supplier, $product, $administrator, 'RET-APPROVED', '1.250', '19.99');
        $this->reportPurchaseReturn($company, $outlet, $warehouse, $supplier, $product, $administrator, 'RET-PENDING', '2.000', '19.99', 'pending_approval');
        $this->reportPurchaseReturn($company, $outlet, $warehouse, $supplier, $product, $administrator, 'RET-CANCELLED', '3.000', '19.99', 'cancelled');

        $report = $this->reportFor($administrator, 'returns');

        $this->assertSame(2499, $report['detail']['value']);
        $this->assertSame(1, $report['detail']['count']);
        $this->assertSame('RET-APPROVED', $report['detail']['rows'][0]['reference']);
        $this->assertSame('1.250', $report['detail']['rows'][0]['quantity']);
        $this->assertSame(2499, $report['detail']['rows'][0]['value']);
    }

    public function test_purchase_return_reports_are_outlet_and_tenant_isolated(): void
    {
        $company = Company::factory()->create();
        $assigned = $this->reportBranch($company, 'Assigned Returns Outlet');
        $unassigned = $this->reportBranch($company, 'Unassigned Returns Outlet');
        $manager = $this->reportUser($company, $assigned, UserRole::Manager);
        $administrator = $this->reportUser($company, $assigned);
        $assignedWarehouse = $this->reportWarehouse($company, $assigned, 'Assigned Returns Warehouse');
        $unassignedWarehouse = $this->reportWarehouse($company, $unassigned, 'Unassigned Returns Warehouse');
        $supplier = $this->reportSupplier($company, 'Outlet Returns Supplier');
        $product = $this->reportProduct($company, $assigned, 'Outlet Returns Product', '10.00');

        $this->reportPurchaseReturn($company, $assigned, $assignedWarehouse, $supplier, $product, $administrator, 'RET-ASSIGNED', '1.000', '10.00');
        $this->reportPurchaseReturn($company, $unassigned, $unassignedWarehouse, $supplier, $product, $administrator, 'RET-UNASSIGNED', '2.000', '10.00');

        $managerReport = $this->reportFor($manager, 'returns');

        $this->assertSame(1000, $managerReport['detail']['value']);
        $this->assertSame(['RET-ASSIGNED'], array_column($managerReport['detail']['rows'], 'reference'));
        $this->actingAs($manager)->get('/reports/returns?outlet_id='.$unassigned->id)->assertSessionHasErrors('outlet_id');
    }

    public function test_purchase_return_csv_matches_the_authorized_detail_rows(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Returns CSV Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Returns CSV Warehouse');
        $supplier = $this->reportSupplier($company, 'Returns CSV Supplier');
        $product = $this->reportProduct($company, $outlet, 'Returns CSV Product', '15.00');
        $this->reportPurchaseReturn($company, $outlet, $warehouse, $supplier, $product, $administrator, 'RET-CSV', '2.000', '15.00');
        $this->reportPurchaseReturn($company, $outlet, $warehouse, $supplier, $product, $administrator, 'RET-CSV-PENDING', '2.000', '15.00', 'pending_approval');

        $detail = $this->actingAs($administrator)->get('/reports/returns?outlet_id='.$outlet->id);
        $csv = $this->actingAs($administrator)->get('/reports/returns/export?outlet_id='.$outlet->id);

        $detail->assertOk()->assertSee('RET-CSV')->assertDontSee('RET-CSV-PENDING');
        $csv->assertOk();
        $this->assertStringContainsString('RET-CSV', $csv->streamedContent());
        $this->assertStringNotContainsString('RET-CSV-PENDING', $csv->streamedContent());
        $this->assertStringContainsString('30.00', $csv->streamedContent());
    }
}
