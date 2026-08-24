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
use App\Models\Pos\PosProductFavorite;
use App\Models\Pos\PosSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosPremiumExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_cashier_receives_premium_desktop_mobile_and_accessibility_contracts(): void
    {
        $cashier = $this->user(UserRole::Sales);
        $product = $this->product($cashier, 'POS-PREMIUM-001', 'Premium Long Product Name for Counter Search', 8, 3499);

        $response = $this->actingAs($cashier)->get('/pos');

        $response->assertOk()
            ->assertSee('data-pos-app', false)
            ->assertSee('data-pos-mobile', false)
            ->assertSee('data-pos-open-held', false)
            ->assertSee('data-pos-open-shortcuts', false)
            ->assertSee('data-pos-hold-panel', false)
            ->assertSee('data-pos-confirm-hold', false)
            ->assertSee('data-favorite-products', false)
            ->assertSee('Keyboard shortcuts')
            ->assertSee('Held sales')
            ->assertSee($product->name);
        $this->actingAs($this->user(UserRole::Staff, $cashier->company, $cashier->branch))->get('/pos')->assertForbidden();
    }

    public function test_catalog_search_supports_name_sku_and_barcode_without_cross_tenant_results(): void
    {
        $cashier = $this->user(UserRole::Manager);
        $product = $this->product($cashier, 'SEARCH-SKU-10', 'Counter Search Kettle', 5, 1200, '8901111111111');
        $outsider = $this->user(UserRole::Manager);
        $this->product($outsider, 'SEARCH-SKU-OTHER', 'Other Tenant Kettle', 5, 900, '8902222222222');

        foreach (['Counter Search', 'SEARCH-SKU-10'] as $term) {
            $this->actingAs($cashier)->getJson('/pos/catalog?q='.urlencode($term))
                ->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $product->id);
        }

        $this->actingAs($cashier)->getJson('/pos/catalog?scan=8901111111111')
            ->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $product->id);
        $this->actingAs($cashier)->getJson('/pos/catalog?q=Other%20Tenant')->assertOk()->assertJsonCount(0, 'products');
    }

    public function test_favorites_are_persisted_per_cashier_outlet_and_tenant(): void
    {
        $cashier = $this->user(UserRole::Sales);
        $product = $this->product($cashier, 'FAV-001', 'Favorite Product', 5, 250);

        $this->actingAs($cashier)->postJson("/pos/favorites/{$product->id}")
            ->assertOk()->assertJson(['product_id' => $product->id, 'favorite' => true]);
        $this->assertDatabaseHas('pos_product_favorites', [
            'company_id' => $cashier->company_id,
            'branch_id' => $cashier->branch_id,
            'user_id' => $cashier->id,
            'product_id' => $product->id,
        ]);
        $this->actingAs($cashier)->get('/pos')->assertOk()->assertSee('data-pos-category="favorites"', false)->assertSee('is-favorite', false);

        $colleague = $this->user(UserRole::Sales, $cashier->company, $cashier->branch);
        $this->assertSame(0, PosProductFavorite::query()->where('user_id', $colleague->id)->count());
        $this->actingAs($colleague)->getJson('/pos/catalog?q=Favorite')->assertOk()->assertJsonPath('products.0.favorite', false);

        $outsider = $this->user(UserRole::Manager);
        $this->actingAs($outsider)->postJson("/pos/favorites/{$product->id}")->assertNotFound();

        $this->actingAs($cashier)->postJson("/pos/favorites/{$product->id}")->assertOk()->assertJsonPath('favorite', false);
        $this->assertDatabaseMissing('pos_product_favorites', ['user_id' => $cashier->id, 'product_id' => $product->id]);
    }

    public function test_customer_lookup_searches_name_and_mobile_with_tenant_isolation(): void
    {
        $cashier = $this->user(UserRole::Manager);
        $customer = $this->customer($cashier, 'Priya Retail Customer', '9000012345');
        $outsider = $this->user(UserRole::Manager);
        $this->customer($outsider, 'Priya Other Company', '9111111111');

        $this->actingAs($cashier)->getJson('/pos/customers/lookup?q=Priya')
            ->assertOk()->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.id', $customer->id);
        $this->actingAs($cashier)->getJson('/pos/customers/lookup?mobile=9000012345')
            ->assertOk()->assertJsonPath('customer.id', $customer->id)->assertJsonCount(0, 'customers');
    }

    public function test_existing_line_discount_and_payment_rules_remain_authoritative(): void
    {
        $manager = $this->user(UserRole::Manager);
        $product = $this->product($manager, 'DISC-001', 'Discount Product', 6, 100);

        $this->actingAs($manager)->post('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'discount_type' => 'fixed', 'discount_value' => 20]],
            'payments' => [['method' => 'cash', 'amount' => 180]],
        ])->assertRedirect();

        $sale = PosSale::query()->firstOrFail();
        $this->assertSame('200.00', $sale->subtotal);
        $this->assertSame('20.00', $sale->discount_amount);
        $this->assertSame('180.00', $sale->total_amount);
        $this->assertSame('20.00', $sale->items()->firstOrFail()->discount_amount);
    }

    public function test_held_sales_remain_owned_by_the_cashier_and_resume_once(): void
    {
        $cashier = $this->user(UserRole::Sales);
        $product = $this->product($cashier, 'HELD-UX-001', 'Held UX Product', 4, 75);

        $this->actingAs($cashier)->post('/pos/hold', ['notes' => 'Counter three', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect();
        $held = PosSale::query()->firstOrFail();

        $this->actingAs($cashier)->get('/pos')->assertOk()->assertSee($held->sale_number)->assertSee('Counter three');
        $this->actingAs($cashier)->get("/pos/held/{$held->id}")->assertOk()->assertSee('Resumed bill');

        $colleague = $this->user(UserRole::Sales, $cashier->company, $cashier->branch);
        $this->actingAs($colleague)->get("/pos/held/{$held->id}")->assertForbidden();
    }

    public function test_keyboard_and_dark_mode_contracts_protect_form_input(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString("const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes", $javascript);
        $this->assertStringContainsString("event.key === 'F6'", $javascript);
        $this->assertStringContainsString("event.key === 'F8'", $javascript);
        $this->assertStringContainsString("event.key === 'F9'", $javascript);
        $this->assertStringContainsString('visibleProductButtons', $javascript);
        $this->assertStringContainsString('button.offsetParent !== null', $javascript);
        $this->assertStringContainsString("state.categoryId = 'all'", $javascript);
        $this->assertStringContainsString('.dark .pos-cart-line', $css);
        $this->assertStringContainsString('.dark .pos-workflow-sheet', $css);
        $this->assertStringContainsString('.dark .pos-favorite-button', $css);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create(['is_active' => true]);

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }

    private function product(User $user, string $sku, string $name, float $stock, float $price, ?string $barcode = null): Product
    {
        $category = InventoryCategory::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'premium-pos'], ['name' => 'Premium POS', 'is_active' => true]);
        $unit = InventoryUnit::firstOrCreate(['company_id' => $user->company_id, 'short_code' => 'PCS'], ['name' => 'Piece', 'type' => 'quantity', 'is_active' => true]);
        $product = Product::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'category_id' => $category->id, 'unit_id' => $unit->id, 'type' => 'simple', 'name' => $name, 'slug' => str($sku)->lower(), 'sku' => $sku, 'barcode' => $barcode, 'selling_price' => $price, 'cost_price' => $price / 2, 'track_inventory' => true, 'allow_negative_stock' => false, 'status' => 'active', 'is_active' => true]);
        $warehouse = Warehouse::firstOrCreate(['company_id' => $user->company_id, 'code' => 'PREMIUM-POS'], ['branch_id' => $user->branch_id, 'name' => 'Premium POS Stock', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        $location = StockLocation::firstOrCreate(['warehouse_id' => $warehouse->id, 'code' => 'COUNTER'], ['company_id' => $user->company_id, 'name' => 'Counter', 'type' => 'bin', 'is_active' => true]);
        StockLevel::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'warehouse_id' => $warehouse->id, 'stock_location_id' => $location->id, 'product_id' => $product->id, 'quantity_on_hand' => $stock, 'quantity_available' => $stock]);

        return $product;
    }

    private function customer(User $user, string $name, string $mobile): Customer
    {
        return Customer::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'customer_number' => 'POS-'.str_pad((string) (Customer::query()->count() + 1), 6, '0', STR_PAD_LEFT), 'first_name' => str($name)->before(' '), 'display_name' => $name, 'phone' => $mobile, 'customer_type' => 'retail', 'status' => 'active', 'created_by' => $user->id]);
    }
}
