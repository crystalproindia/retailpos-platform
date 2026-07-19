<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosRegister;
use App\Models\Pos\PosSale;
use App\Models\User;
use App\Services\Pos\PosRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosCoreOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_has_one_open_session_and_register_aware_checkout_links_the_sale(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)->get('/pos/registers')->assertOk()->assertSee('Registers and cash sessions');
        $registers = app(PosRegisterService::class);
        $register = $registers->create($manager, ['branch_id' => $manager->branch_id, 'code' => 'COUNTER-1', 'name' => 'Counter 1', 'receipt_prefix' => 'CT1']);
        $session = $registers->open($register, $manager, 500);
        $this->assertSame('open', $session->status);

        $this->expectException(ValidationException::class);
        $registers->open($register, $manager, 0);
    }

    public function test_completed_registered_sale_can_be_voided_transactionally_and_receipt_pdf_remains_available(): void
    {
        $manager = $this->manager();
        $product = $this->product($manager, 5, 100);
        $registers = app(PosRegisterService::class);
        $register = $registers->create($manager, ['branch_id' => $manager->branch_id, 'code' => 'COUNTER-1', 'name' => 'Counter 1', 'receipt_prefix' => 'CT1']);
        $session = $registers->open($register, $manager, 0);

        $this->actingAs($manager)->post('/pos/checkout', [
            'branch_id' => $manager->branch_id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertSessionHasErrors('register_id');

        $this->actingAs($manager)->post('/pos/checkout', [
            'branch_id' => $manager->branch_id,
            'register_id' => $register->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ])->assertRedirect();

        $sale = PosSale::query()->firstOrFail();
        $this->assertSame($register->id, $sale->register_id);
        $this->assertSame($session->id, $sale->register_session_id);
        $this->assertStringStartsWith('CT1-', $sale->receipt_number);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 3]);
        $this->actingAs($manager)->get('/pos/receipts/'.$sale->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($manager)->post('/pos/sales/'.$sale->id.'/void', ['reason' => 'Customer requested cancellation'])->assertRedirect();
        $this->assertSame('voided', $sale->refresh()->status);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 5]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'movement_type' => 'sale_void', 'reference_id' => $sale->id]);
        $this->assertDatabaseHas('pos_payments', ['pos_sale_id' => $sale->id, 'status' => 'reversed']);

        $closed = $registers->close($session, $manager, 0);
        $this->assertSame('closed', $closed->status);
        $this->assertSame('0.00', $closed->expected_cash);
    }

    private function manager(): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['is_active' => true]);

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }

    private function product(User $user, int $stock, int $price): Product
    {
        $category = InventoryCategory::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'core-pos'], ['name' => 'Core POS', 'is_active' => true]);
        $unit = InventoryUnit::firstOrCreate(['company_id' => $user->company_id, 'short_code' => 'PCS'], ['name' => 'Piece', 'type' => 'quantity', 'is_active' => true]);
        $product = Product::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'category_id' => $category->id, 'unit_id' => $unit->id, 'type' => 'simple', 'name' => 'Register Product', 'slug' => 'register-product', 'sku' => 'REG-001', 'selling_price' => $price, 'cost_price' => 50, 'track_inventory' => true, 'allow_negative_stock' => false, 'status' => 'active', 'is_active' => true]);
        $warehouse = Warehouse::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'name' => 'Register Stock', 'code' => 'REG-STOCK', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        $location = StockLocation::create(['company_id' => $user->company_id, 'warehouse_id' => $warehouse->id, 'name' => 'Counter', 'code' => 'COUNTER', 'type' => 'bin', 'is_active' => true]);
        StockLevel::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'warehouse_id' => $warehouse->id, 'stock_location_id' => $location->id, 'product_id' => $product->id, 'quantity_on_hand' => $stock, 'quantity_available' => $stock]);

        return $product;
    }
}
