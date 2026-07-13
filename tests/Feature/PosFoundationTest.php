<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customers\Customer;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\CustomerProductSummary;
use App\Models\Pos\PosProductPairSummary;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosOfflineSyncRecord;
use App\Models\User;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_registry_and_role_access_are_configured(): void
    {
        $registry = new ModuleRegistry;
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);

        $this->assertSame('pos.index', $registry->find('pos')->route);
        $this->assertContains('pos-billing', collect($registry->sidebar(UserRole::Administrator)->firstWhere('id', 'pos')->children)->pluck('id'));
        $this->actingAs($sales)->get('/pos')->assertOk()->assertSee('data-pos-mobile', false);
        $this->actingAs($staff)->get('/pos')->assertForbidden();
    }

    public function test_pos_checkout_posts_stock_customer_history_and_receipt(): void
    {
        $manager = $this->user(UserRole::Manager);
        $product = $this->product($manager, 'POS-TEA-001', 'POS Tea', 6, 120);
        $customer = $this->customer($manager, '9000000101');

        $this->actingAs($manager)->post('/pos/checkout', $this->cartPayload($product, $customer, 2, 240))->assertRedirect();

        $sale = PosSale::query()->firstOrFail();
        $this->assertSame('completed', $sale->status);
        $this->assertSame(240.0, (float) $sale->total_amount);
        $this->assertDatabaseHas('pos_sale_items', ['pos_sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->assertDatabaseHas('pos_payments', ['pos_sale_id' => $sale->id, 'payment_method' => 'cash', 'amount' => 240]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 4]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'movement_type' => 'sale', 'reference_id' => $sale->id]);
        $this->assertDatabaseHas('customer_product_summaries', ['customer_id' => $customer->id, 'product_id' => $product->id, 'purchase_count' => 1]);
        $this->assertSame(240.0, (float) $customer->refresh()->total_purchase_amount);
        $this->assertDatabaseHas('customer_activity_logs', ['customer_id' => $customer->id, 'reference_id' => $sale->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'pos.sale.completed', 'auditable_id' => $sale->id]);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'pos.sale.completed', 'aggregate_id' => $sale->id]);
        $this->actingAs($manager)->get("/pos/receipts/{$sale->id}")->assertOk()->assertSee($sale->sale_number);
    }

    public function test_pos_can_hold_and_resume_a_bill_without_posting_stock(): void
    {
        $manager = $this->user(UserRole::Manager);
        $product = $this->product($manager, 'POS-HOLD-001', 'Held Product', 5, 50);

        $this->actingAs($manager)->post('/pos/hold', $this->cartPayload($product, null, 1))->assertRedirect();
        $sale = PosSale::query()->firstOrFail();
        $this->assertSame('held', $sale->status);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 5]);
        $this->actingAs($manager)->get("/pos/held/{$sale->id}")->assertOk()->assertSee('Current bill');

        $outsider = $this->user(UserRole::Manager);
        $this->actingAs($outsider)->get("/pos/held/{$sale->id}")->assertNotFound();
    }

    public function test_pos_checkout_accepts_supported_split_cash_and_card_payments(): void
    {
        $manager = $this->user(UserRole::Manager);
        $product = $this->product($manager, 'POS-SPLIT-001', 'Split Payment Product', 4, 120);

        $this->actingAs($manager)->post('/pos/checkout', [
            'device_type' => 'desktop',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 120]],
            'payments' => [['method' => 'cash', 'amount' => 60], ['method' => 'card', 'amount' => 60, 'reference' => 'APPROVAL-1']],
        ])->assertRedirect();

        $sale = PosSale::query()->firstOrFail();
        $this->assertDatabaseHas('pos_payments', ['pos_sale_id' => $sale->id, 'payment_method' => 'cash', 'amount' => 60]);
        $this->assertDatabaseHas('pos_payments', ['pos_sale_id' => $sale->id, 'payment_method' => 'card', 'amount' => 60, 'reference' => 'APPROVAL-1']);
    }

    public function test_mobile_lookup_quick_capture_and_rule_based_product_suggestions_work(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager, '9000000202');
        $regular = $this->product($manager, 'POS-REG-001', 'Regular Tea', 10, 90);
        $addon = $this->product($manager, 'POS-ADD-001', 'Suggested Biscuits', 10, 40);
        $unavailable = $this->product($manager, 'POS-OOS-001', 'Unavailable Product', 0, 50);
        CustomerProductSummary::create(['company_id' => $manager->company_id, 'customer_id' => $customer->id, 'product_id' => $regular->id, 'category_id' => $regular->category_id, 'purchase_count' => 8, 'quantity_purchased' => 12, 'total_spent' => 1080, 'first_purchased_at' => now()->subMonths(3), 'last_purchased_at' => now()->subDay()]);
        CustomerProductSummary::create(['company_id' => $manager->company_id, 'customer_id' => $customer->id, 'product_id' => $unavailable->id, 'category_id' => $unavailable->category_id, 'purchase_count' => 12, 'quantity_purchased' => 12, 'total_spent' => 600, 'first_purchased_at' => now()->subMonths(3), 'last_purchased_at' => now()]);
        PosProductPairSummary::create(['company_id' => $manager->company_id, 'product_id' => $regular->id, 'related_product_id' => $addon->id, 'co_purchase_count' => 7, 'last_purchased_together_at' => now()]);

        $response = $this->actingAs($manager)->getJson('/pos/customers/lookup?mobile=9000000202')->assertOk();
        $response->assertJsonPath('customer.id', $customer->id)->assertJsonFragment(['name' => 'Regular Tea'])->assertJsonFragment(['name' => 'Suggested Biscuits'])->assertJsonMissing(['name' => 'Unavailable Product']);

        $this->actingAs($manager)->postJson('/pos/customers/quick-create', ['mobile' => '9000000303', 'name' => 'Quick Customer'])->assertCreated()->assertJsonPath('customer.name', 'Quick Customer');
        $this->assertDatabaseHas('customers', ['company_id' => $manager->company_id, 'phone' => '9000000303']);
    }

    public function test_database_seeder_adds_transparent_pos_demo_history(): void
    {
        $this->seed();
        $company = Company::query()->where('name', 'Crystal Retail Demo')->firstOrFail();

        $this->assertDatabaseHas('pos_sales', ['company_id' => $company->id, 'sale_number' => 'POS-DEMO-0001', 'status' => 'completed']);
        $this->assertDatabaseHas('customer_product_summaries', ['company_id' => $company->id]);
        $this->assertDatabaseHas('pos_product_pair_summaries', ['company_id' => $company->id, 'co_purchase_count' => 4]);
    }

    public function test_pos_terminal_dashboard_mobile_held_bill_and_pwa_foundations_load(): void
    {
        $manager = $this->user(UserRole::Manager);
        $product = $this->product($manager, 'POS-UI-001', 'Terminal Product', 5, 75);

        $this->actingAs($manager)->post('/pos/hold', $this->cartPayload($product, null, 1))->assertRedirect();

        $this->actingAs($manager)->get('/pos/dashboard')->assertOk()->assertSee('POS dashboard');
        $this->actingAs($manager)->get('/pos/terminal')->assertOk()->assertSee('data-pos-mode="terminal"', false)->assertSee('Scan barcode or search product')->assertSee('data-pos-payment-modal', false)->assertSee('data-pos-split-payment', false)->assertSee('Checkout');
        $this->actingAs($manager)->get('/pos/mobile')->assertOk()->assertSee('data-pos-mode="mobile"', false)->assertSee('Products')->assertSee('data-pos-payment-modal', false);
        $this->actingAs($manager)->get('/pos/held')->assertOk()->assertSee('Held bills')->assertSee(PosSale::query()->value('sale_number'));
        $this->assertFileExists(public_path('pos-manifest.webmanifest'));
        $this->assertFileExists(public_path('pos-sw.js'));
        $this->assertFileExists(public_path('pos-offline.html'));
    }

    public function test_offline_bootstrap_and_idempotent_bill_sync_use_existing_pos_checkout(): void
    {
        $manager = $this->user(UserRole::Manager);
        $product = $this->product($manager, 'POS-OFFLINE-001', 'Offline Product', 5, 100);
        $customer = $this->customer($manager, '9000000404');
        $record = ['offline_uuid' => '0c2092d0-95d9-482f-bccc-6fe9d5d6f62b', 'offline_reference' => 'OFF-TERM01-20260713-0001', 'offline_created_at' => now()->subMinute()->toIso8601String(), 'customer' => ['mobile' => $customer->phone, 'name' => $customer->display_name], 'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]], 'payments' => [['method' => 'cash', 'amount' => 100]]];

        $this->get('/pos/offline/bootstrap')->assertRedirect(route('login'));
        $this->actingAs($manager)->getJson('/pos/offline/bootstrap')->assertOk()->assertJsonFragment(['id' => $product->id])->assertJsonPath('settings.enable_offline_pos', true);
        $response = $this->actingAs($manager)->postJson('/pos/offline/sync', ['batch_uuid' => 'e3cf0004-a8d8-4f0a-a191-8cef1d8032cb', 'device_id' => 'browser-device', 'records' => [$record]])->assertOk();
        $sale = PosSale::query()->firstOrFail();
        $response->assertJsonPath('results.0.sale_number', $sale->sale_number);
        $this->assertTrue($sale->synced_from_offline);
        $this->assertSame($record['offline_uuid'], $sale->offline_uuid);
        $this->assertDatabaseHas('pos_payments', ['pos_sale_id' => $sale->id, 'payment_method' => 'cash', 'amount' => 100]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 4]);
        $this->assertDatabaseHas('customer_product_summaries', ['customer_id' => $customer->id, 'product_id' => $product->id]);
        $this->actingAs($manager)->postJson('/pos/offline/sync', ['batch_uuid' => 'b4154c19-c18d-4bb3-89f6-b6e92d987e2d', 'device_id' => 'browser-device', 'records' => [$record]])->assertOk()->assertJsonPath('results.0.status', 'duplicate');
        $this->assertSame(1, PosSale::query()->count());
        $this->assertDatabaseHas('pos_offline_sync_records', ['offline_uuid' => $record['offline_uuid']]);
        $this->actingAs($manager)->get('/pos/offline')->assertOk()->assertSee($record['offline_reference']);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $this->actingAs($sales)->get('/pos/offline')->assertForbidden();
        $this->assertContains(PosOfflineSyncRecord::query()->firstOrFail()->status, ['synced', 'warning']);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }

    private function customer(User $user, string $mobile): Customer
    {
        return Customer::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'customer_number' => 'CUS-'.str_pad((string) (Customer::query()->count() + 1), 6, '0', STR_PAD_LEFT), 'first_name' => 'POS', 'last_name' => 'Customer', 'display_name' => 'POS Customer', 'phone' => $mobile, 'whatsapp' => $mobile, 'customer_type' => 'retail', 'status' => 'active', 'created_by' => $user->id]);
    }

    private function product(User $user, string $sku, string $name, float $stock, float $price): Product
    {
        $category = InventoryCategory::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'pos-category'], ['name' => 'POS Category', 'is_active' => true]);
        $unit = InventoryUnit::firstOrCreate(['company_id' => $user->company_id, 'short_code' => 'PCS'], ['name' => 'Piece', 'type' => 'quantity', 'is_active' => true]);
        $product = Product::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'category_id' => $category->id, 'unit_id' => $unit->id, 'type' => 'simple', 'name' => $name, 'slug' => str($sku)->lower(), 'sku' => $sku, 'barcode' => '890'.str_pad((string) (Product::query()->count() + 1), 9, '0', STR_PAD_LEFT), 'selling_price' => $price, 'cost_price' => $price / 2, 'track_inventory' => true, 'allow_negative_stock' => false, 'status' => 'active', 'is_active' => true]);
        $warehouse = Warehouse::firstOrCreate(['company_id' => $user->company_id, 'code' => 'POS-MAIN'], ['branch_id' => $user->branch_id, 'name' => 'POS Main', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        $location = StockLocation::firstOrCreate(['warehouse_id' => $warehouse->id, 'code' => 'POS-A1'], ['company_id' => $user->company_id, 'name' => 'POS A1', 'type' => 'bin', 'is_active' => true]);
        StockLevel::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'warehouse_id' => $warehouse->id, 'stock_location_id' => $location->id, 'product_id' => $product->id, 'quantity_on_hand' => $stock, 'quantity_available' => $stock]);

        return $product;
    }

    /** @return array<string, mixed> */
    private function cartPayload(Product $product, ?Customer $customer, float $quantity, ?float $payment = null): array
    {
        $payload = ['customer_id' => $customer?->id, 'device_type' => 'desktop', 'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $product->selling_price]]];
        if ($payment !== null) $payload['payments'] = [['method' => 'cash', 'amount' => $payment]];

        return $payload;
    }
}
