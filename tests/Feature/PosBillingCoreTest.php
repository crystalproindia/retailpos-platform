<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Compliance\GstSetting;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryTaxRate;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosBillingSetting;
use App\Models\Pos\PosSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosBillingCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_exclusive_sale_snapshots_cgst_and_sgst_using_integer_minor_units(): void
    {
        $manager = $this->manager();
        $this->gst($manager, '27');
        $product = $this->product($manager, 'GST-EX-01', '100.00', '18.000');

        $this->checkout($manager, $product, [['method' => 'cash', 'amount' => '118.00']]);

        $sale = PosSale::firstOrFail();
        $this->assertSame('100.00', $sale->subtotal);
        $this->assertSame('100.00', $sale->taxable_amount);
        $this->assertSame('9.00', $sale->cgst_total);
        $this->assertSame('9.00', $sale->sgst_total);
        $this->assertSame('0.00', $sale->igst_total);
        $this->assertSame('18.00', $sale->tax_amount);
        $this->assertSame('118.00', $sale->total_amount);
        $this->assertSame('intra_state', $sale->tax_treatment_snapshot);
        $this->assertSame('GST-EX-01', $sale->items()->firstOrFail()->sku);
    }

    public function test_interstate_and_tax_inclusive_sales_reconcile_per_line(): void
    {
        $manager = $this->manager();
        $this->gst($manager, '27');
        $product = $this->product($manager, 'GST-IN-01', '118.00', '18.000');
        PosBillingSetting::firstOrCreate(['company_id' => $manager->company_id])->update(['tax_inclusive_pricing' => true]);

        $this->actingAs($manager)->post('/pos/checkout', $this->payload($product, [['method' => 'upi', 'amount' => '118.00', 'reference' => 'UPI-001']], ['place_of_supply_state_code' => '07']))->assertRedirect();

        $sale = PosSale::firstOrFail();
        $this->assertSame('100.00', $sale->taxable_amount);
        $this->assertSame('0.00', $sale->cgst_total);
        $this->assertSame('18.00', $sale->igst_total);
        $this->assertSame('118.00', $sale->total_amount);
        $this->assertSame('inter_state', $sale->tax_treatment_snapshot);
    }

    public function test_missing_gst_setup_blocks_taxable_sale_but_zero_rated_sale_remains_valid(): void
    {
        $manager = $this->manager();
        $taxable = $this->product($manager, 'GST-MISSING', '100.00', '18.000');
        $this->actingAs($manager)->post('/pos/checkout', $this->payload($taxable, [['method' => 'cash', 'amount' => '118.00']]))->assertSessionHasErrors('tax');
        $this->assertDatabaseCount('pos_sales', 0);

        $zeroRated = $this->product($manager, 'GST-ZERO', '100.00', '0.000');
        $this->checkout($manager, $zeroRated, [['method' => 'cash', 'amount' => '100.00']]);
        $this->assertSame('100.00', PosSale::firstOrFail()->total_amount);
    }

    public function test_cash_change_is_recorded_and_non_cash_overpayment_is_rejected(): void
    {
        $manager = $this->manager();
        $product = $this->product($manager, 'PAY-CASH', '100.00');

        $this->checkout($manager, $product, [['method' => 'cash', 'amount' => '150.00']]);
        $sale = PosSale::firstOrFail();
        $this->assertSame('150.00', $sale->paid_amount);
        $this->assertSame('50.00', $sale->change_amount);

        $product = $this->product($manager, 'PAY-UPI', '100.00');
        $this->actingAs($manager)->post('/pos/checkout', $this->payload($product, [['method' => 'upi', 'amount' => '101.00', 'reference' => 'UPI-OVER']]))->assertSessionHasErrors('payments');
        $this->assertDatabaseCount('pos_sales', 1);
    }

    public function test_completion_key_prevents_duplicate_stock_movements_and_receipts(): void
    {
        $manager = $this->manager();
        $product = $this->product($manager, 'IDEMPOTENT', '100.00', null, 3);
        $payload = $this->payload($product, [['method' => 'cash', 'amount' => '100.00']], ['completion_key' => '6c3b6307-9403-4b2e-a15c-fc6505723384']);

        $this->actingAs($manager)->post('/pos/checkout', $payload)->assertRedirect();
        $this->actingAs($manager)->post('/pos/checkout', $payload)->assertRedirect();

        $this->assertDatabaseCount('pos_sales', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 2]);
        $this->assertMatchesRegularExpression('/^POS-\d{4}-\d{2}-\d{6}$/', PosSale::firstOrFail()->receipt_number);
    }

    public function test_held_sale_is_finalized_once_and_can_be_discarded_without_stock_movement(): void
    {
        $manager = $this->manager();
        $product = $this->product($manager, 'HOLD-CORE', '100.00', null, 3);
        $this->actingAs($manager)->post('/pos/hold', $this->payload($product, []))->assertRedirect();
        $held = PosSale::firstOrFail();
        $this->assertSame('held', $held->status);
        $this->assertStringStartsWith('HLD-', $held->sale_number);

        $this->actingAs($manager)->post('/pos/checkout', $this->payload($product, [['method' => 'cash', 'amount' => '100.00']], ['held_sale_id' => $held->id]))->assertRedirect();
        $this->assertDatabaseCount('pos_sales', 1);
        $this->assertSame('completed', $held->refresh()->status);

        $this->actingAs($manager)->post('/pos/hold', $this->payload($product, []))->assertRedirect();
        $discard = PosSale::where('status', 'held')->firstOrFail();
        $this->actingAs($manager)->delete('/pos/held/'.$discard->id)->assertRedirect(route('pos.held.index'));
        $this->assertDatabaseMissing('pos_sales', ['id' => $discard->id]);
    }

    public function test_cashier_cannot_override_price_and_exact_scan_prefers_barcode(): void
    {
        $manager = $this->manager();
        $product = $this->product($manager, 'SKU-CORE', '100.00', null, 3, '8900000000012');
        $cashier = User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Sales]);

        $payload = $this->payload($product, [['method' => 'cash', 'amount' => '90.00']], [
            'items' => [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => '90.00']],
        ]);
        $this->actingAs($cashier)->post('/pos/checkout', $payload)->assertForbidden();
        $this->actingAs($manager)->getJson('/pos/catalog?scan=8900000000012')->assertOk()->assertJsonPath('products.0.id', $product->id);
    }

    private function manager(): User
    {
        $company = Company::factory()->create(['state' => 'Maharashtra']);
        $branch = Branch::factory()->for($company)->create(['state' => 'Maharashtra', 'is_active' => true, 'receipt_prefix' => 'POS']);

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }

    private function gst(User $user, string $state): void
    {
        GstSetting::create(['company_id' => $user->company_id, 'legal_name' => $user->company->name, 'state_code' => $state, 'default_place_of_supply_state_code' => $state]);
    }

    private function product(User $user, string $sku, string $price, ?string $taxRate = null, int $stock = 5, ?string $barcode = null): Product
    {
        $category = InventoryCategory::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'pos-billing-core'], ['name' => 'POS Billing Core', 'is_active' => true]);
        $unit = InventoryUnit::firstOrCreate(['company_id' => $user->company_id, 'short_code' => 'PCS'], ['name' => 'Piece', 'type' => 'quantity', 'is_active' => true]);
        $tax = $taxRate === null ? null : InventoryTaxRate::firstOrCreate(['company_id' => $user->company_id, 'name' => 'GST '.$taxRate, 'rate' => $taxRate], ['tax_type' => 'gst', 'country' => 'IN', 'is_active' => true]);
        $product = Product::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'category_id' => $category->id, 'unit_id' => $unit->id, 'tax_rate_id' => $tax?->id, 'type' => 'simple', 'name' => $sku, 'slug' => str($sku)->lower(), 'sku' => $sku, 'barcode' => $barcode ?: '890'.str_pad((string) (Product::query()->count() + 1), 10, '0', STR_PAD_LEFT), 'selling_price' => $price, 'cost_price' => '50.00', 'track_inventory' => true, 'allow_negative_stock' => false, 'status' => 'active', 'is_active' => true]);
        $warehouse = Warehouse::firstOrCreate(['company_id' => $user->company_id, 'code' => 'CORE-POS'], ['branch_id' => $user->branch_id, 'name' => 'Core POS', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        $location = StockLocation::firstOrCreate(['warehouse_id' => $warehouse->id, 'code' => 'CORE-A1'], ['company_id' => $user->company_id, 'name' => 'Core A1', 'type' => 'bin', 'is_active' => true]);
        StockLevel::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'warehouse_id' => $warehouse->id, 'stock_location_id' => $location->id, 'product_id' => $product->id, 'quantity_on_hand' => $stock, 'quantity_available' => $stock]);

        return $product;
    }

    /** @param array<int, array<string, string>> $payments @param array<string, mixed> $overrides */
    private function payload(Product $product, array $payments, array $overrides = []): array
    {
        return $overrides + ['items' => [['product_id' => $product->id, 'quantity' => '1.000', 'unit_price' => (string) $product->selling_price]], 'payments' => $payments];
    }

    /** @param array<int, array<string, string>> $payments */
    private function checkout(User $user, Product $product, array $payments): void
    {
        $this->actingAs($user)->post('/pos/checkout', $this->payload($product, $payments))->assertRedirect();
    }
}
