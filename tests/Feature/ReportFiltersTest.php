<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoicePaymentStatus;
use App\Enums\Crm\InvoiceStatus;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class ReportFiltersTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_sales_filters_change_summary_rows_and_csv_with_the_same_authorized_pos_records(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Sales Filter Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $category = $this->reportCategory($company, 'Filter Category');
        $product = $this->reportProduct($company, $outlet, 'Included Product', '10.01');
        $product->update(['category_id' => $category->id]);
        $excludedProduct = $this->reportProduct($company, $outlet, 'Excluded Product', '20.02');
        $customer = $this->reportCustomer($company, $outlet, 'Included Customer');

        $included = $this->reportSale($company, $outlet, $administrator, 'FILTER-INCLUDED', '10.01');
        $included->update(['customer_id' => $customer->id, 'sale_type' => 'retail', 'discount_amount' => '1.00']);
        $this->reportSaleItem($included, $product, $category);
        $this->reportPosPayment($included, $administrator, 'upi');

        $excluded = $this->reportSale($company, $outlet, $administrator, 'FILTER-EXCLUDED', '20.02');
        $excluded->update(['sale_type' => 'online']);
        $this->reportSaleItem($excluded, $excludedProduct);
        $this->reportPosPayment($excluded, $administrator, 'card');

        $filters = ['outlet_id' => $outlet->id, 'product_id' => $product->id, 'category_id' => $category->id, 'customer_id' => $customer->id, 'cashier_id' => $administrator->id, 'payment_method' => 'upi', 'sale_channel' => 'retail', 'discounted' => '1'];
        $report = $this->reportFor($administrator, 'sales', $filters);
        $csv = $this->actingAs($administrator)->get('/reports/sales/export?'.http_build_query($filters));

        $this->assertSame(1001, $report['detail']['net_sales']);
        $this->assertSame(1, $report['detail']['count']);
        $this->assertSame(['FILTER-INCLUDED'], array_column($report['detail']['rows'], 'reference'));
        $csv->assertOk();
        $this->assertStringContainsString('FILTER-INCLUDED', $csv->streamedContent());
        $this->assertStringNotContainsString('FILTER-EXCLUDED', $csv->streamedContent());
        $this->actingAs($administrator)->get('/reports/sales')->assertOk()->assertSee('More filters')->assertSee('Product')->assertSee('Customer');
    }

    public function test_purchase_inventory_and_movement_filters_use_the_same_persisted_product_category_supplier_and_stock_scope(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Operational Filter Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $warehouse = $this->reportWarehouse($company, $outlet, 'Operational Filter Warehouse');
        $category = $this->reportCategory($company, 'Operational Filter Category');
        $product = $this->reportProduct($company, $outlet, 'Included Operational Product', '12.34');
        $product->update(['category_id' => $category->id]);
        $otherProduct = $this->reportProduct($company, $outlet, 'Excluded Operational Product', '12.34');
        $supplier = $this->reportSupplier($company, 'Included Supplier');
        $otherSupplier = $this->reportSupplier($company, 'Excluded Supplier');

        $includedInvoice = $this->reportPurchaseInvoice($company, $outlet, $warehouse, $supplier, $administrator, 'PURCHASE-INCLUDED', '12.34');
        $this->reportPurchaseInvoiceItem($includedInvoice, $product);
        $excludedInvoice = $this->reportPurchaseInvoice($company, $outlet, $warehouse, $otherSupplier, $administrator, 'PURCHASE-EXCLUDED', '56.78');
        $this->reportPurchaseInvoiceItem($excludedInvoice, $otherProduct);

        $this->reportStockLevel($company, $outlet, $warehouse, $product, '-1.000');
        $this->reportStockLevel($company, $outlet, $warehouse, $otherProduct, '4.000');
        $this->reportStockMovement($company, $outlet, $warehouse, $product, $administrator, 'adjustment', 'out', '1.000', '0.000', '-1.000');
        $this->reportStockMovement($company, $outlet, $warehouse, $otherProduct, $administrator, 'purchase', 'in', '4.000', '0.000', '4.000');

        $purchase = $this->reportFor($administrator, 'purchases', ['outlet_id' => $outlet->id, 'supplier_id' => $supplier->id, 'product_id' => $product->id, 'category_id' => $category->id]);
        $inventory = $this->reportFor($administrator, 'inventory', ['outlet_id' => $outlet->id, 'category_id' => $category->id, 'stock_status' => 'negative']);
        $movements = $this->reportFor($administrator, 'movements', ['outlet_id' => $outlet->id, 'product_id' => $product->id, 'movement_type' => 'adjustment']);

        $this->assertSame(1234, $purchase['detail']['total']);
        $this->assertSame(['PURCHASE-INCLUDED'], array_column($purchase['detail']['rows'], 'reference'));
        $this->assertSame(['Included Operational Product'], array_column($inventory['detail']['rows'], 'product'));
        $this->assertSame(['adjustment'], array_column($movements['detail']['rows'], 'movement_type'));
    }

    public function test_payment_status_and_gst_tax_classification_filters_preserve_financial_scope(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Finance Filter Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $intraState = $this->reportInvoice($company, $outlet, 'GST-INTRA', '100.00', '50.00', InvoiceStatus::Issued->value);
        $intraState->update(['tax_classification' => 'intra_state', 'cgst_total' => '9.00', 'sgst_total' => '9.00']);
        $interState = $this->reportInvoice($company, $outlet, 'GST-INTER', '200.00', '0.00', InvoiceStatus::Paid->value);
        $interState->update(['tax_classification' => 'inter_state', 'igst_total' => '36.00']);
        $recorded = $this->reportPayment($company, $outlet, $intraState, 'PAY-RECORDED', '50.00', InvoicePaymentStatus::Recorded->value);
        $recorded->update(['payment_method' => 'upi']);
        $cleared = $this->reportPayment($company, $outlet, $interState, 'PAY-CLEARED', '200.00', InvoicePaymentStatus::Cleared->value);
        $cleared->update(['payment_method' => 'card']);

        $payments = $this->reportFor($administrator, 'payments', ['outlet_id' => $outlet->id, 'payment_method' => 'upi', 'status' => InvoicePaymentStatus::Recorded->value]);
        $gst = $this->reportFor($administrator, 'gst', ['outlet_id' => $outlet->id, 'tax_classification' => 'intra_state', 'status' => InvoiceStatus::Issued->value]);

        $this->assertSame(5000, $payments['detail']['received']);
        $this->assertSame(['PAY-RECORDED'], array_column($payments['detail']['rows'], 'reference'));
        $this->assertSame(10000, $gst['detail']['taxable_sales']);
        $this->assertSame(900, $gst['detail']['cgst']);
        $this->assertSame(900, $gst['detail']['sgst']);
        $this->assertSame(0, $gst['detail']['igst']);
    }

    public function test_cross_tenant_filter_ids_are_rejected_before_report_data_is_loaded(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Filter Authorization Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $otherCompany = Company::factory()->create();
        $otherOutlet = $this->reportBranch($otherCompany, 'Other Filter Authorization Outlet');
        $otherProduct = $this->reportProduct($otherCompany, $otherOutlet, 'Other Tenant Product');

        $this->actingAs($administrator)->get('/reports/sales?outlet_id='.$outlet->id.'&product_id='.$otherProduct->id)->assertSessionHasErrors('product_id');
    }
}
