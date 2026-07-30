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
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\PurchaseReturnItem;
use App\Models\Purchases\Supplier;
use App\Models\User;
use App\Services\Reports\RetailReportingService;

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
            'sold_at' => $soldAt ?? now(),
            'completed_at' => $status === 'completed' ? ($soldAt ?? now()) : null,
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

    protected function reportPurchaseInvoice(Company $company, Branch $branch, Warehouse $warehouse, Supplier $supplier, User $user, string $number, string $total, string $status = 'approved', mixed $date = null): PurchaseInvoice
    {
        $date ??= today();

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
            'issue_date' => $issueDate ?? today(),
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
            'payment_date' => $date ?? today(),
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
            'return_date' => $date ?? today(),
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
        return app(RetailReportingService::class)->report($user, $report, $filters + [
            'outlet_id' => $user->branch_id,
            'date_from' => now()->subDays(365)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);
    }

    private function reportKey(string $prefix): string
    {
        $this->reportingSequence++;

        return $prefix.'-'.$this->reportingSequence;
    }
}
