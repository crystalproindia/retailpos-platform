<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class PurchaseReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_authorized_purchase_invoices_are_included_and_cancelled_invoices_are_excluded(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Purchase Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Purchase Warehouse');
        $supplier = $this->reportSupplier($company, 'Verified Supplier');

        $this->reportPurchaseInvoice($company, $outlet, $warehouse, $supplier, $administrator, 'PINV-APPROVED', '150.25');
        $this->reportPurchaseInvoice($company, $outlet, $warehouse, $supplier, $administrator, 'PINV-CANCELLED', '999.99', 'cancelled');

        $report = $this->reportFor($administrator, 'purchases');

        $this->assertSame(15025, $report['detail']['gross_total']);
        $this->assertSame(15025, $report['detail']['total']);
        $this->assertSame(['PINV-APPROVED'], array_column($report['detail']['rows'], 'reference'));
    }

    public function test_approved_purchase_returns_reduce_the_net_purchase_total_in_minor_units(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Returns Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Returns Warehouse');
        $supplier = $this->reportSupplier($company, 'Returns Supplier');
        $product = $this->reportProduct($company, $outlet, 'Returned Product', '12.50');

        $this->reportPurchaseInvoice($company, $outlet, $warehouse, $supplier, $administrator, 'PINV-NET', '100.00');
        $this->reportPurchaseReturn($company, $outlet, $warehouse, $supplier, $product, $administrator, 'PRET-APPROVED', '1.000', '12.50');
        $this->reportPurchaseReturn($company, $outlet, $warehouse, $supplier, $product, $administrator, 'PRET-PENDING', '2.000', '12.50', 'pending_approval');

        $report = $this->reportFor($administrator, 'purchases');

        $this->assertSame(10000, $report['detail']['gross_total']);
        $this->assertSame(1250, $report['detail']['return_value']);
        $this->assertSame(8750, $report['detail']['total']);
    }

    public function test_purchase_rows_reconcile_by_supplier_and_honor_authorized_warehouse_and_outlet_filters(): void
    {
        $company = Company::factory()->create();
        $first = $this->reportBranch($company, 'First Purchase Outlet');
        $second = $this->reportBranch($company, 'Second Purchase Outlet');
        $administrator = $this->reportUser($company, $first);
        $firstWarehouse = $this->reportWarehouse($company, $first, 'First Purchase Warehouse');
        $secondWarehouse = $this->reportWarehouse($company, $second, 'Second Purchase Warehouse');
        $firstSupplier = $this->reportSupplier($company, 'First Supplier');
        $secondSupplier = $this->reportSupplier($company, 'Second Supplier');

        $this->reportPurchaseInvoice($company, $first, $firstWarehouse, $firstSupplier, $administrator, 'PINV-FIRST', '40.40');
        $this->reportPurchaseInvoice($company, $second, $secondWarehouse, $secondSupplier, $administrator, 'PINV-SECOND', '50.50');

        $all = $this->reportFor($administrator, 'purchases', ['outlet_id' => 'all']);
        $firstWarehouseOnly = $this->reportFor($administrator, 'purchases', ['outlet_id' => 'all', 'warehouse_id' => $firstWarehouse->id]);

        $this->assertSame(9090, $all['detail']['total']);
        $this->assertSame(['First Supplier' => 4040, 'Second Supplier' => 5050], collect($all['detail']['rows'])->mapWithKeys(fn (array $row) => [$row['supplier'] => $row['total']])->all());
        $this->assertSame(4040, $firstWarehouseOnly['detail']['total']);
        $this->assertSame(['PINV-FIRST'], array_column($firstWarehouseOnly['detail']['rows'], 'reference'));
    }

    public function test_purchase_reports_reject_cross_tenant_and_unassigned_outlet_filters(): void
    {
        $company = Company::factory()->create();
        $assigned = $this->reportBranch($company, 'Assigned Purchase Outlet');
        $unassigned = $this->reportBranch($company, 'Unassigned Purchase Outlet');
        $manager = $this->reportUser($company, $assigned, UserRole::Manager);
        $otherCompany = Company::factory()->create();
        $otherOutlet = $this->reportBranch($otherCompany, 'Other Tenant Purchase Outlet');

        $this->actingAs($manager)->get('/reports/purchases?outlet_id='.$unassigned->id)->assertSessionHasErrors('outlet_id');
        $this->actingAs($manager)->get('/reports/purchases?outlet_id='.$otherOutlet->id)->assertSessionHasErrors('outlet_id');
    }

    public function test_purchase_csv_exports_the_same_authorized_rows_as_the_detail_report(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Purchase CSV Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Purchase CSV Warehouse');
        $supplier = $this->reportSupplier($company, 'Purchase CSV Supplier');
        $this->reportPurchaseInvoice($company, $outlet, $warehouse, $supplier, $administrator, 'PINV-CSV', '88.88');
        $this->reportPurchaseInvoice($company, $outlet, $warehouse, $supplier, $administrator, 'PINV-CSV-CANCELLED', '77.77', 'cancelled');

        $detail = $this->actingAs($administrator)->get('/reports/purchases?outlet_id='.$outlet->id);
        $csv = $this->actingAs($administrator)->get('/reports/purchases/export?outlet_id='.$outlet->id);

        $detail->assertOk()->assertSee('PINV-CSV')->assertDontSee('PINV-CSV-CANCELLED');
        $csv->assertOk();
        $this->assertStringContainsString('PINV-CSV', $csv->streamedContent());
        $this->assertStringNotContainsString('PINV-CSV-CANCELLED', $csv->streamedContent());
        $this->assertStringContainsString('88.88', $csv->streamedContent());
    }
}
