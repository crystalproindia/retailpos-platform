<?php

namespace Database\Seeders;

use App\Enums\Crm\InvoiceStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceItem;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Pos\PosReturn;
use App\Models\Pos\PosReturnItem;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Local browser-review data only. It is intentionally not called by DatabaseSeeder. */
class LocalProfitabilityReviewSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Crystal Retail Demo')->firstOrFail();
        $outlet = Branch::query()->where('company_id', $company->id)->where('code', 'BLR-HQ')->firstOrFail();
        $salesperson = User::query()->where('company_id', $company->id)->where('email', 'sales@retailpos.test')->firstOrFail();
        $unit = InventoryUnit::firstOrCreate(['company_id' => $company->id, 'short_code' => 'P1REV'], ['name' => 'P1 Review Unit', 'type' => 'quantity', 'decimal_allowed' => true, 'is_active' => true]);
        $apparel = InventoryCategory::firstOrCreate(['company_id' => $company->id, 'slug' => 'p1-review-apparel'], ['name' => 'P1 Review Apparel', 'is_active' => true]);
        $grocery = InventoryCategory::firstOrCreate(['company_id' => $company->id, 'slug' => 'p1-review-grocery'], ['name' => 'P1 Review Grocery', 'is_active' => true]);
        $northstar = InventoryBrand::firstOrCreate(['company_id' => $company->id, 'slug' => 'p1-review-northstar'], ['name' => 'P1 Review Northstar', 'is_active' => true]);
        $harvest = InventoryBrand::firstOrCreate(['company_id' => $company->id, 'slug' => 'p1-review-harvest'], ['name' => 'P1 Review Harvest', 'is_active' => true]);
        $jacket = Product::updateOrCreate(['company_id' => $company->id, 'sku' => 'P1-REVIEW-JACKET'], ['branch_id' => $outlet->id, 'category_id' => $apparel->id, 'brand_id' => $northstar->id, 'unit_id' => $unit->id, 'name' => 'P1 Review Jacket', 'slug' => 'p1-review-jacket', 'cost_price' => '60.00', 'selling_price' => '100.00', 'track_inventory' => true, 'status' => Product::STATUS_ACTIVE, 'is_active' => true]);
        $coffee = Product::updateOrCreate(['company_id' => $company->id, 'sku' => 'P1-REVIEW-COFFEE'], ['branch_id' => $outlet->id, 'category_id' => $grocery->id, 'brand_id' => $harvest->id, 'unit_id' => $unit->id, 'name' => 'P1 Review Coffee', 'slug' => 'p1-review-coffee', 'cost_price' => '30.00', 'selling_price' => '80.00', 'track_inventory' => true, 'status' => Product::STATUS_ACTIVE, 'is_active' => true]);

        $posSale = PosSale::firstOrCreate(['company_id' => $company->id, 'sale_number' => 'P1-REVIEW-POS-001'], ['branch_id' => $outlet->id, 'receipt_number' => 'P1-REVIEW-POS-001', 'currency' => 'INR', 'status' => 'completed', 'subtotal' => '280.00', 'discount_amount' => '20.00', 'taxable_amount' => '260.00', 'tax_amount' => '0.00', 'total_amount' => '260.00', 'paid_amount' => '260.00', 'completed_by' => $salesperson->id, 'completed_at' => now(), 'sold_at' => now()]);
        if (! $posSale->items()->exists()) {
            $jacketItem = $posSale->items()->create(['company_id' => $company->id, 'product_id' => $jacket->id, 'category_id' => $apparel->id, 'brand_id_snapshot' => $northstar->id, 'product_name' => $jacket->name, 'sku' => $jacket->sku, 'category_name_snapshot' => $apparel->name, 'brand_name_snapshot' => $northstar->name, 'quantity' => '2.000', 'unit_price' => '100.00', 'gross_amount' => '200.00', 'gross_sales_snapshot' => '200.00', 'discount_amount' => '20.00', 'taxable_amount' => '180.00', 'net_sales_snapshot' => '180.00', 'line_total' => '180.00', 'unit_cost_snapshot' => '60.00', 'total_cost_snapshot' => '120.00', 'gross_profit_before_discount' => '80.00', 'gross_profit_snapshot' => '60.00', 'gross_margin_before_discount_percent' => '40.0000', 'gross_margin_percent_snapshot' => '33.3333', 'cost_snapshot_method' => 'standard_cost', 'cost_snapshot_status' => 'captured']);
            $posSale->items()->create(['company_id' => $company->id, 'product_id' => $coffee->id, 'category_id' => $grocery->id, 'brand_id_snapshot' => $harvest->id, 'product_name' => $coffee->name, 'sku' => $coffee->sku, 'category_name_snapshot' => $grocery->name, 'brand_name_snapshot' => $harvest->name, 'quantity' => '1.000', 'unit_price' => '80.00', 'gross_amount' => '80.00', 'gross_sales_snapshot' => '80.00', 'discount_amount' => '0.00', 'taxable_amount' => '80.00', 'net_sales_snapshot' => '80.00', 'line_total' => '80.00', 'unit_cost_snapshot' => '30.00', 'total_cost_snapshot' => '30.00', 'gross_profit_before_discount' => '50.00', 'gross_profit_snapshot' => '50.00', 'gross_margin_before_discount_percent' => '62.5000', 'gross_margin_percent_snapshot' => '62.5000', 'cost_snapshot_method' => 'standard_cost', 'cost_snapshot_status' => 'captured']);
            $return = PosReturn::firstOrCreate(['company_id' => $company->id, 'return_number' => 'P1-REVIEW-RETURN-001'], ['branch_id' => $outlet->id, 'original_sale_id' => $posSale->id, 'financial_year' => now()->format('Y').'-'.now()->addYear()->format('y'), 'return_type' => 'partial_return', 'status' => PosReturn::STATUS_COMPLETED, 'return_date' => today(), 'timezone' => 'Asia/Kolkata', 'currency' => 'INR', 'refund_total' => '45.00', 'idempotency_key' => 'p1-review-return-001', 'requested_by' => $salesperson->id, 'completed_by' => $salesperson->id, 'completed_at' => now()]);
            PosReturnItem::firstOrCreate(['pos_return_id' => $return->id, 'original_sale_item_id' => $jacketItem->id], ['product_id' => $jacket->id, 'product_name' => $jacket->name, 'sku' => $jacket->sku, 'original_quantity' => '2.000', 'previously_returned_quantity' => '0.000', 'return_quantity' => '0.500', 'unit_price_snapshot' => '100.00', 'gross_adjustment' => '50.00', 'discount_adjustment' => '5.00', 'taxable_adjustment' => '45.00', 'tax_adjustment' => '0.00', 'line_refund_total' => '45.00']);
        }

        $invoice = CrmInvoice::firstOrCreate(['company_id' => $company->id, 'invoice_number' => 'P1-REVIEW-CRM-001'], ['branch_id' => $outlet->id, 'billing_name' => 'P1 Review CRM Customer', 'currency' => 'INR', 'status' => InvoiceStatus::Issued, 'issue_date' => today(), 'subtotal' => '255.00', 'discount_total' => '15.00', 'taxable_total' => '255.00', 'tax_total' => '0.00', 'grand_total' => '255.00', 'amount_paid' => '0.00', 'balance_due' => '255.00']);
        if (! $invoice->items()->exists()) {
            $invoice->items()->create(['product_id' => $coffee->id, 'category_id_snapshot' => $grocery->id, 'brand_id_snapshot' => $harvest->id, 'name' => 'P1 Review Coffee', 'sku_snapshot' => $coffee->sku, 'category_name_snapshot' => $grocery->name, 'brand_name_snapshot' => $harvest->name, 'quantity' => '3.000', 'unit_price' => '50.00', 'gross_sales_snapshot' => '150.00', 'discount_amount' => '15.00', 'line_subtotal' => '135.00', 'net_sales_snapshot' => '135.00', 'line_total' => '135.00', 'unit_cost_snapshot' => '30.00', 'total_cost_snapshot' => '90.00', 'gross_profit_before_discount' => '60.00', 'gross_profit_snapshot' => '45.00', 'gross_margin_percent_snapshot' => '33.3333', 'cost_snapshot_method' => 'standard_cost', 'cost_snapshot_status' => 'captured']);
            $invoice->items()->create(['name' => 'P1 Review Custom Service', 'quantity' => '1.000', 'unit_price' => '120.00', 'gross_sales_snapshot' => '120.00', 'discount_amount' => '0.00', 'line_subtotal' => '120.00', 'net_sales_snapshot' => '120.00', 'line_total' => '120.00', 'cost_snapshot_status' => 'unavailable']);
        }
    }
}
