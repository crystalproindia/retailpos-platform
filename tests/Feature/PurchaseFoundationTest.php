<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DomainEventLog;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryTaxRate;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\ReorderSuggestion;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Purchases\GoodsReceipt;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseRequest;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\PurchaseSettings;
use App\Models\Purchases\Supplier;
use App\Models\Purchases\SupplierProduct;
use App\Models\Purchases\SupplierPayment;
use App\Models\Purchases\SupplierScoreSnapshot;
use App\Models\User;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_module_registry_sidebar_and_role_filtering(): void
    {
        $registry = new ModuleRegistry;

        $purchases = $registry->find('purchases');
        $inventory = $registry->sidebar(UserRole::Administrator)->firstWhere('id', 'inventory');
        $adminPurchases = $registry->sidebar(UserRole::Administrator)->firstWhere('id', 'purchases');

        $this->assertSame('purchases.dashboard', $purchases->route);
        $this->assertSame('Retail Operations', $purchases->category);
        $this->assertContains('purchase-orders', collect($adminPurchases->children)->pluck('id'));
        $this->assertContains('goods-receipts', collect($adminPurchases->children)->pluck('id'));
        $this->assertContains('inventory-decision-dashboard', collect($inventory->children)->pluck('id'));
        $this->assertFalse($registry->sidebar(UserRole::Sales)->contains('id', 'purchases'));
        $this->assertFalse($registry->sidebar(UserRole::Staff)->contains('id', 'purchases'));
    }

    public function test_purchase_routes_are_manager_only(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);

        $this->actingAs($manager)->get('/purchases')->assertOk();
        $this->actingAs($sales)->get('/purchases')->assertForbidden();
        $this->actingAs($staff)->get('/purchases')->assertForbidden();
    }

    public function test_supplier_crud_contacts_addresses_product_mapping_and_score_snapshot(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->purchaseFixtures($manager);

        $this->actingAs($manager)->post('/purchases/suppliers', [
            'code' => 'SUP-NEW',
            'name' => 'New Supplier',
            'supplier_type' => 'distributor',
            'email' => 'supplier@example.test',
            'default_currency' => 'INR',
            'lead_time_days' => 4,
            'manual_rating' => 84,
            'is_active' => '1',
        ])->assertRedirect();

        $supplier = Supplier::query()->where('code', 'SUP-NEW')->firstOrFail();

        $this->actingAs($manager)->post("/purchases/suppliers/{$supplier->id}/contacts", [
            'name' => 'Procurement Contact',
            'email' => 'contact@example.test',
            'is_primary' => '1',
        ])->assertRedirect();

        $this->actingAs($manager)->post("/purchases/suppliers/{$supplier->id}/addresses", [
            'type' => 'office',
            'address_line_1' => 'Supplier Street',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'country' => 'India',
            'postal_code' => '560001',
            'is_default' => '1',
        ])->assertRedirect();

        $this->actingAs($manager)->post("/purchases/suppliers/{$supplier->id}/products", [
            'product_id' => $fixtures['product']->id,
            'purchase_price' => 58,
            'minimum_order_quantity' => 5,
            'lead_time_days' => 4,
            'is_preferred' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('supplier_contacts', ['supplier_id' => $supplier->id, 'is_primary' => true]);
        $this->assertDatabaseHas('supplier_addresses', ['supplier_id' => $supplier->id, 'is_default' => true]);
        $this->assertDatabaseHas('supplier_products', ['supplier_id' => $supplier->id, 'product_id' => $fixtures['product']->id, 'is_preferred' => true]);
        $this->assertDatabaseHas('supplier_score_snapshots', ['supplier_id' => $supplier->id]);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'purchase.supplier.score_updated']);
    }

    public function test_purchase_request_approval_and_conversion_to_purchase_order(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->purchaseFixtures($manager);

        $this->actingAs($manager)->post('/purchases/requests', [
            'warehouse_id' => $fixtures['warehouse']->id,
            'priority' => 'high',
            'items' => [[
                'product_id' => $fixtures['product']->id,
                'supplier_id' => $fixtures['supplier']->id,
                'requested_quantity' => 6,
                'estimated_price' => 58,
            ]],
        ])->assertRedirect();

        $request = PurchaseRequest::query()->firstOrFail();
        $this->actingAs($manager)->post("/purchases/requests/{$request->id}/submit")->assertRedirect();
        $this->actingAs($manager)->post("/purchases/requests/{$request->id}/approve")->assertRedirect();
        $this->actingAs($manager)->post("/purchases/requests/{$request->id}/convert")->assertRedirect();

        $this->assertSame('converted_to_po', $request->refresh()->status->value);
        $this->assertDatabaseHas('purchase_orders', ['purchase_request_id' => $request->id, 'supplier_id' => $fixtures['supplier']->id]);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'purchase.request.converted_to_po']);
    }

    public function test_purchase_order_lifecycle_and_print_view(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->purchaseFixtures($manager);

        $this->actingAs($manager)->post('/purchases/orders', [
            'warehouse_id' => $fixtures['warehouse']->id,
            'supplier_id' => $fixtures['supplier']->id,
            'items' => [[
                'product_id' => $fixtures['product']->id,
                'ordered_quantity' => 5,
                'unit_price' => 58,
                'tax_rate' => 5,
            ]],
        ])->assertRedirect();

        $order = PurchaseOrder::query()->firstOrFail();
        $this->actingAs($manager)->post("/purchases/orders/{$order->id}/submit")->assertRedirect();
        $this->actingAs($manager)->post("/purchases/orders/{$order->id}/approve")->assertRedirect();
        $this->actingAs($manager)->post("/purchases/orders/{$order->id}/send")->assertRedirect();
        $this->actingAs($manager)->get("/purchases/orders/{$order->id}/print")->assertOk()->assertSee($order->po_number);

        $this->assertSame('sent', $order->refresh()->status->value);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'purchase.order.sent']);
    }

    public function test_goods_receipt_posts_stock_updates_po_and_supplier_product(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->purchaseFixtures($manager);
        $order = $this->purchaseOrder($manager, $fixtures);
        $orderItem = $order->items()->firstOrFail();

        $this->actingAs($manager)->post('/purchases/grn', [
            'purchase_order_id' => $order->id,
            'items' => [[
                'purchase_order_item_id' => $orderItem->id,
                'product_id' => $fixtures['product']->id,
                'stock_location_id' => $fixtures['location']->id,
                'received_quantity' => 4,
                'accepted_quantity' => 4,
                'unit_cost' => 58,
            ]],
        ])->assertRedirect();

        $receipt = GoodsReceipt::query()->firstOrFail();
        $this->actingAs($manager)->post("/purchases/grn/{$receipt->id}/receive")->assertRedirect();

        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'purchase', 'reference_type' => GoodsReceipt::class, 'reference_id' => $receipt->id]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $fixtures['product']->id, 'quantity_on_hand' => 6]);
        $this->assertSame('6.000', $orderItem->refresh()->pending_quantity);
        $this->assertNotNull($fixtures['supplierProduct']->refresh()->last_purchased_at);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'purchase.goods_received']);
    }

    public function test_purchase_return_completes_stock_reduction_and_blocks_negative_stock(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->purchaseFixtures($manager);

        StockLevel::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'stock_location_id' => $fixtures['location']->id,
            'product_id' => $fixtures['product']->id,
            'quantity_on_hand' => 3,
            'quantity_available' => 3,
        ]);

        $this->actingAs($manager)->post('/purchases/returns', [
            'supplier_id' => $fixtures['supplier']->id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'reason' => 'Quality issue',
            'items' => [[
                'product_id' => $fixtures['product']->id,
                'stock_location_id' => $fixtures['location']->id,
                'quantity' => 2,
                'unit_cost' => 58,
            ]],
        ])->assertRedirect();

        $return = PurchaseReturn::query()->firstOrFail();
        $this->actingAs($manager)->post("/purchases/returns/{$return->id}/approve")->assertRedirect();
        $this->actingAs($manager)->post("/purchases/returns/{$return->id}/complete")->assertRedirect();

        $this->assertSame('completed', $return->refresh()->status->value);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'purchase_return', 'reference_type' => PurchaseReturn::class, 'reference_id' => $return->id]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $fixtures['product']->id, 'quantity_on_hand' => 1]);

        $this->actingAs($manager)->post('/purchases/returns', [
            'supplier_id' => $fixtures['supplier']->id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'reason' => 'Too much return',
            'items' => [[
                'product_id' => $fixtures['product']->id,
                'stock_location_id' => $fixtures['location']->id,
                'quantity' => 5,
                'unit_cost' => 58,
            ]],
        ])->assertRedirect();

        $largeReturn = PurchaseReturn::query()->latest('id')->firstOrFail();
        $this->actingAs($manager)->post("/purchases/returns/{$largeReturn->id}/complete")->assertSessionHasErrors('items');
    }

    public function test_inventory_decision_dashboard_labels_missing_sales_data(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->purchaseFixtures($manager);

        StockLevel::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'stock_location_id' => $fixtures['location']->id,
            'product_id' => $fixtures['product']->id,
            'quantity_on_hand' => 8,
            'quantity_available' => 8,
            'average_daily_sales' => 0,
        ]);

        $this->actingAs($manager)
            ->get('/inventory/decision-dashboard')
            ->assertOk()
            ->assertSee('Not enough sales data');
    }

    public function test_purchase_settings_reorder_conversion_and_seeded_demo_data(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->purchaseFixtures($manager);

        $this->actingAs($manager)->put('/purchases/settings', [
            'po_prefix' => 'PO',
            'pr_prefix' => 'REQ',
            'grn_prefix' => 'GRN',
            'return_prefix' => 'RTN',
            'allow_receive_without_po' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('purchase_settings', ['company_id' => $manager->company_id, 'pr_prefix' => 'REQ', 'allow_receive_without_po' => true]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'purchase.settings.updated']);

        $suggestion = ReorderSuggestion::create([
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'product_id' => $fixtures['product']->id,
            'current_stock' => 1,
            'available_stock' => 1,
            'reorder_point' => 3,
            'suggested_quantity' => 10,
            'stockout_risk_level' => 'medium',
            'reason' => 'Test reorder',
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->post('/purchases/requests/from-reorder', [
            'suggestion_ids' => [$suggestion->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('purchase_requests', ['company_id' => $manager->company_id, 'source_type' => 'reorder_suggestion']);

        $this->seed();
        $company = Company::query()->where('name', 'Crystal Retail Demo')->firstOrFail();
        $this->assertDatabaseHas('suppliers', ['company_id' => $company->id, 'code' => 'SUP-URBAN']);
        $this->assertDatabaseHas('purchase_orders', ['company_id' => $company->id, 'po_number' => 'PO-000024']);
        $this->assertDatabaseHas('goods_receipts', ['company_id' => $company->id, 'grn_number' => 'GRN-000011']);
        $this->assertDatabaseHas('purchase_returns', ['company_id' => $company->id, 'return_number' => 'PRN-000005']);
    }

    public function test_purchase_invoice_is_grn_matched_and_approved_before_it_becomes_payable(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->purchaseFixtures($manager);
        $receipt = GoodsReceipt::create([
            'company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'warehouse_id' => $fixtures['warehouse']->id,
            'supplier_id' => $fixtures['supplier']->id, 'grn_number' => 'GRN-INV-001', 'receipt_date' => now(), 'status' => 'received', 'received_by' => $manager->id,
        ]);
        $line = $receipt->items()->create(['product_id' => $fixtures['product']->id, 'ordered_quantity' => 5, 'received_quantity' => 5, 'accepted_quantity' => 5, 'rejected_quantity' => 0, 'unit_cost' => 100]);

        $this->actingAs($manager)->post('/purchases/invoices', [
            'supplier_id' => $fixtures['supplier']->id, 'supplier_invoice_number' => 'SUP-INV-1', 'supplier_invoice_date' => now()->toDateString(),
            'supplier_state_code' => 'KA', 'place_of_supply_state_code' => 'KA', 'idempotency_key' => 'invoice-test-one',
            'items' => [['goods_receipt_item_id' => $line->id, 'quantity' => 3, 'unit_price' => 100, 'tax_rate' => 18]],
        ])->assertRedirect();
        $invoice = PurchaseInvoice::query()->firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('354.00', $invoice->grand_total);
        $this->actingAs($manager)->post("/purchases/invoices/{$invoice->id}/approve")->assertRedirect();
        $this->assertSame('approved', $invoice->refresh()->status);
        $this->assertSame('354.00', $invoice->outstanding_total);

        $this->actingAs($manager)->post('/purchases/invoices', [
            'supplier_id' => $fixtures['supplier']->id, 'supplier_invoice_number' => 'SUP-INV-2', 'supplier_invoice_date' => now()->toDateString(),
            'items' => [['goods_receipt_item_id' => $line->id, 'quantity' => 3, 'unit_price' => 100, 'tax_rate' => 0]],
        ])->assertSessionHasErrors('items');
    }

    public function test_supplier_payment_allocates_and_reversal_restores_purchase_invoice_outstanding(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->purchaseFixtures($manager);
        $invoice = PurchaseInvoice::create([
            'company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'supplier_id' => $fixtures['supplier']->id,
            'invoice_number' => 'PINV-TEST-001', 'supplier_invoice_number' => 'SUP-PAY-001', 'supplier_invoice_date' => now(), 'financial_year' => '2026-27',
            'status' => 'approved', 'currency' => 'INR', 'grand_total' => 500, 'outstanding_total' => 500, 'created_by' => $manager->id, 'approved_by' => $manager->id, 'approved_at' => now(),
        ]);
        $this->actingAs($manager)->post('/purchases/payments', [
            'supplier_id' => $fixtures['supplier']->id, 'payment_date' => now()->toDateString(), 'payment_type' => 'invoice_payment', 'payment_method' => 'bank_transfer', 'amount' => 200,
            'idempotency_key' => 'payment-test-one', 'allocations' => [['purchase_invoice_id' => $invoice->id, 'amount' => 200]],
        ])->assertRedirect();
        $payment = SupplierPayment::query()->firstOrFail();
        $this->assertSame('200.00', $invoice->refresh()->paid_total);
        $this->assertSame('300.00', $invoice->outstanding_total);
        $administrator = $this->user(UserRole::Administrator, $manager->company, $manager->branch);
        $this->actingAs($administrator)->post("/purchases/payments/{$payment->id}/reverse", ['reason' => 'Bank transfer failed'])->assertRedirect();
        $this->assertSame('500.00', $invoice->refresh()->outstanding_total);
        $this->assertSame('reversed', $payment->refresh()->status);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseFixtures(User $user): array
    {
        $category = InventoryCategory::create(['company_id' => $user->company_id, 'name' => 'Demo Category', 'slug' => 'demo-category', 'is_active' => true]);
        $brand = InventoryBrand::create(['company_id' => $user->company_id, 'name' => 'Demo Brand', 'slug' => 'demo-brand', 'is_active' => true]);
        $unit = InventoryUnit::create(['company_id' => $user->company_id, 'name' => 'Piece', 'short_code' => 'PCS', 'type' => 'quantity', 'is_active' => true]);
        $taxRate = InventoryTaxRate::create(['company_id' => $user->company_id, 'name' => 'GST 5%', 'rate' => 5, 'tax_type' => 'gst', 'country' => 'India', 'is_active' => true]);
        $warehouse = Warehouse::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'name' => 'Purchase Warehouse', 'code' => 'PWH', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        $location = StockLocation::create(['company_id' => $user->company_id, 'warehouse_id' => $warehouse->id, 'name' => 'Receiving Bin', 'code' => 'RB1', 'type' => 'bin', 'is_active' => true]);
        $product = Product::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'tax_rate_id' => $taxRate->id,
            'type' => 'simple',
            'name' => 'Purchase Product',
            'slug' => 'purchase-product',
            'sku' => 'PUR-001',
            'barcode' => '890333300001',
            'selling_price' => 100,
            'cost_price' => 60,
            'purchase_price' => 58,
            'track_inventory' => true,
            'allow_negative_stock' => false,
            'status' => 'active',
            'is_active' => true,
        ]);
        $supplier = Supplier::create([
            'company_id' => $user->company_id,
            'code' => 'SUP-001',
            'name' => 'Purchase Supplier',
            'supplier_type' => 'distributor',
            'email' => 'supplier@example.test',
            'default_currency' => 'INR',
            'lead_time_days' => 4,
            'manual_rating' => 82,
            'is_active' => true,
        ]);
        $supplierProduct = SupplierProduct::create([
            'company_id' => $user->company_id,
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'purchase_price' => 58,
            'tax_rate_id' => $taxRate->id,
            'lead_time_days' => 4,
            'is_preferred' => true,
            'is_active' => true,
        ]);
        PurchaseSettings::create(['company_id' => $user->company_id]);

        return compact('category', 'brand', 'unit', 'taxRate', 'warehouse', 'location', 'product', 'supplier', 'supplierProduct');
    }

    /**
     * @param  array<string, mixed>  $fixtures
     */
    private function purchaseOrder(User $user, array $fixtures): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'supplier_id' => $fixtures['supplier']->id,
            'po_number' => 'PO-TEST-001',
            'status' => 'sent',
            'order_date' => now()->toDateString(),
            'currency' => 'INR',
            'subtotal' => 580,
            'tax_total' => 29,
            'grand_total' => 609,
            'created_by' => $user->id,
        ]);

        $order->items()->create([
            'product_id' => $fixtures['product']->id,
            'supplier_product_id' => $fixtures['supplierProduct']->id,
            'product_name_snapshot' => $fixtures['product']->name,
            'sku_snapshot' => $fixtures['product']->sku,
            'ordered_quantity' => 10,
            'received_quantity' => 0,
            'pending_quantity' => 10,
            'unit_price' => 58,
            'tax_rate' => 5,
            'tax_amount' => 29,
            'line_total' => 609,
        ]);

        StockLevel::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'warehouse_id' => $fixtures['warehouse']->id,
            'stock_location_id' => $fixtures['location']->id,
            'product_id' => $fixtures['product']->id,
            'quantity_on_hand' => 2,
            'quantity_available' => 2,
        ]);

        return $order;
    }
}
