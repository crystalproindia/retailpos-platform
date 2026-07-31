<?php

namespace Tests\Concerns;

use App\Enums\Crm\InvoicePaymentStatus;
use App\Enums\Crm\InvoiceStatus;
use App\Enums\Purchases\PurchaseReturnStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\Customers\Customer;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosPayment;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceItem;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\PurchaseReturnItem;
use App\Models\Purchases\Supplier;
use App\Models\User;
use App\Services\Reports\RetailReportingService;
use Carbon\CarbonImmutable;

trait BuildsReportingData
{
    private int $reportingSequence = 0;

    protected function reportUser(Company $company, Branch $branch, UserRole $role = UserRole::Administrator): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    protected function reportBranch(Company $company, string $name): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    protected function reportSale(Company $company, Branch $branch, User $user, string $number, string $total, string $status = 'completed', mixed $soldAt = null): PosSale
    {
        return PosSale::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'sale_number' => $number,
            'status' => $status,
            'currency' => 'INR',
            'subtotal' => $total,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => $total,
            'paid_amount' => $total,
            'change_amount' => '0.00',
            'sold_at' => $soldAt ? CarbonImmutable::parse($soldAt)->utc() : now(),
            'completed_at' => $status === 'completed' ? ($soldAt ? CarbonImmutable::parse($soldAt)->utc() : now()) : null,
            'completed_by' => $user->id,
        ]);
    }

    protected function reportSupplier(Company $company, string $name): Supplier
    {
        return Supplier::create([
            'company_id' => $company->id,
            'code' => $this->reportKey('SUP'),
            'name' => $name,
            'supplier_type' => 'other',
            'default_currency' => 'INR',
            'is_active' => true,
        ]);
    }

    protected function reportWarehouse(Company $company, Branch $branch, string $name): Warehouse
    {
        return Warehouse::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => $name,
            'code' => $this->reportKey('WH'),
            'type' => 'store',
            'country' => 'India',
            'is_active' => true,
        ]);
    }

    protected function reportProduct(Company $company, Branch $branch, string $name, string $costPrice = '0.00'): Product
    {
        $unit = InventoryUnit::create([
            'company_id' => $company->id,
            'name' => 'Unit '.$this->reportKey('U'),
            'short_code' => $this->reportKey('U'),
            'type' => 'quantity',
            'decimal_allowed' => true,
            'is_active' => true,
        ]);

        $key = $this->reportKey('PRODUCT');

        return Product::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => $name,
            'slug' => strtolower($key),
            'sku' => $key,
            'cost_price' => $costPrice,
            'selling_price' => $costPrice,
            'track_inventory' => true,
            'status' => Product::STATUS_ACTIVE,
            'is_active' => true,
        ]);
    }

    protected function reportCategory(Company $company, string $name): InventoryCategory
    {
        $key = $this->reportKey('CATEGORY');

        return InventoryCategory::create([
            'company_id' => $company->id,
            'name' => $name,
            'slug' => strtolower($key),
            'is_active' => true,
        ]);
    }

    protected function reportCustomer(Company $company, Branch $branch, string $name): Customer
    {
        $key = $this->reportKey('CUSTOMER');

        return Customer::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_number' => $key,
            'first_name' => $name,
            'display_name' => $name,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    protected function reportSaleItem(PosSale $sale, Product $product, ?InventoryCategory $category = null, string $quantity = '1.000', ?string $lineTotal = null): PosSaleItem
    {
        $lineTotal ??= $sale->total_amount;

        return PosSaleItem::create([
            'company_id' => $sale->company_id,
            'pos_sale_id' => $sale->id,
            'product_id' => $product->id,
            'category_id' => $category?->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => $lineTotal,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'line_total' => $lineTotal,
        ]);
    }

    protected function reportPosPayment(PosSale $sale, User $user, string $method, ?string $amount = null): PosPayment
    {
        return PosPayment::create([
            'company_id' => $sale->company_id,
            'pos_sale_id' => $sale->id,
            'payment_method' => $method,
            'amount' => $amount ?? $sale->total_amount,
            'paid_at' => $sale->sold_at ?? now(),
            'created_by' => $user->id,
        ]);
    }

    protected function reportStockLevel(Company $company, Branch $branch, Warehouse $warehouse, Product $product, string $quantity, string $minimumStock = '0.000'): StockLevel
    {
        return StockLevel::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => '0.000',
            'quantity_available' => $quantity,
            'minimum_stock' => $minimumStock,
        ]);
    }

    protected function reportStockMovement(Company $company, Branch $branch, Warehouse $warehouse, Product $product, User $user, string $type, string $direction, string $quantity, string $before, string $after, mixed $occurredAt = null): StockMovement
    {
        return StockMovement::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => $type,
            'direction' => $direction,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'unit_cost' => $product->cost_price,
            'reason' => 'Reporting test movement',
            'created_by' => $user->id,
            'occurred_at' => $occurredAt ? CarbonImmutable::parse($occurredAt)->utc() : now(),
        ]);
    }

    protected function reportPurchaseInvoice(Company $company, Branch $branch, Warehouse $warehouse, Supplier $supplier, User $user, string $number, string $total, string $status = 'approved', mixed $date = null): PurchaseInvoice
    {
        $date ??= today($company->timezone ?: config('app.timezone'));

        return PurchaseInvoice::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => $number,
            'supplier_invoice_number' => 'SUP-'.$number,
            'supplier_invoice_date' => $date,
            'financial_year' => '2026-2027',
            'status' => $status,
            'currency' => 'INR',
            'subtotal' => $total,
            'taxable_total' => $total,
            'grand_total' => $total,
            'paid_total' => '0.00',
            'outstanding_total' => $total,
            'created_by' => $user->id,
        ]);
    }

    protected function reportPurchaseInvoiceItem(PurchaseInvoice $invoice, Product $product, string $quantity = '1.000', ?string $lineTotal = null): PurchaseInvoiceItem
    {
        $lineTotal ??= $invoice->grand_total;

        return PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'name_snapshot' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $lineTotal,
            'taxable_value' => $lineTotal,
            'line_total' => $lineTotal,
        ]);
    }

    protected function reportInvoice(Company $company, Branch $branch, string $number, string $grandTotal, string $balanceDue, string $status = InvoiceStatus::Issued->value, mixed $issueDate = null, mixed $dueDate = null): CrmInvoice
    {
        return CrmInvoice::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_number' => $number,
            'billing_name' => 'Reporting customer',
            'currency' => 'INR',
            'subtotal' => $grandTotal,
            'taxable_total' => $grandTotal,
            'tax_total' => '0.00',
            'grand_total' => $grandTotal,
            'amount_paid' => '0.00',
            'balance_due' => $balanceDue,
            'status' => $status,
            'issue_date' => $issueDate ?? today($company->timezone ?: config('app.timezone')),
            'due_date' => $dueDate,
        ]);
    }

    protected function reportPayment(Company $company, ?Branch $branch, CrmInvoice $invoice, string $reference, string $amount, string $status = InvoicePaymentStatus::Recorded->value, mixed $date = null): CrmInvoicePayment
    {
        return CrmInvoicePayment::create([
            'company_id' => $company->id,
            'branch_id' => $branch?->id,
            'invoice_id' => $invoice->id,
            'payment_reference' => $reference,
            'amount' => $amount,
            'currency' => 'INR',
            'payment_date' => $date ?? today($company->timezone ?: config('app.timezone')),
            'payment_method' => 'cash',
            'status' => $status,
        ]);
    }

    protected function reportPurchaseReturn(Company $company, Branch $branch, Warehouse $warehouse, Supplier $supplier, Product $product, User $user, string $number, string $quantity, string $unitCost, string $status = PurchaseReturnStatus::Approved->value, mixed $date = null): PurchaseReturn
    {
        $return = PurchaseReturn::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'return_number' => $number,
            'status' => $status,
            'return_date' => $date ?? today($company->timezone ?: config('app.timezone')),
            'reason' => 'Reporting test return',
            'created_by' => $user->id,
            'approved_by' => $status === PurchaseReturnStatus::Approved->value ? $user->id : null,
            'approved_at' => $status === PurchaseReturnStatus::Approved->value ? now() : null,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]);

        return $return;
    }

    /** @param array<string, mixed> $filters */
    protected function reportFor(User $user, string $report, array $filters = []): array
    {
        $timezone = $user->company?->timezone ?: config('app.timezone');

        return app(RetailReportingService::class)->report($user, $report, $filters + [
            'outlet_id' => $user->branch_id,
            'date_from' => now($timezone)->subDays(365)->toDateString(),
            'date_to' => now($timezone)->toDateString(),
        ]);
    }

    private function reportKey(string $prefix): string
    {
        $this->reportingSequence++;

        return $prefix.'-'.$this->reportingSequence;
    }
}
