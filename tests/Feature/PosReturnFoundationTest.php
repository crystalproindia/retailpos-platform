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
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosReturn;
use App\Models\Pos\PosReturnSetting;
use App\Models\Pos\PosSale;
use App\Models\User;
use App\Services\Pos\PosReturnService;
use App\Services\Reports\RetailReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosReturnFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_sale_can_be_approved_and_completed_once_with_stock_and_wallet_restored(): void
    {
        [$manager, $product, $customer, $sale] = $this->completedSale('1.000', '120.00');
        $administrator = User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Administrator]);
        $return = app(PosReturnService::class)->create($manager, $sale, $this->payload($sale, $sale->items->first()->id, '1.000', 'store_credit', '120.00'));

        $this->assertSame(PosReturn::STATUS_PENDING_APPROVAL, $return->status);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 4]);
        $return = app(PosReturnService::class)->approve($administrator, $return);
        $completed = app(PosReturnService::class)->complete($administrator, $return);

        $this->assertSame(PosReturn::STATUS_COMPLETED, $completed->status);
        $this->assertMatchesRegularExpression('/^CN-\d{4}-\d{2}-\d{6}$/', $completed->credit_note_number);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 5]);
        $this->assertDatabaseCount('customer_wallet_transactions', 1);
        $this->assertSame(120.0, (float) $customer->refresh()->wallet_balance);
        $this->assertSame('full', $sale->refresh()->return_status);
        $this->assertDatabaseCount('stock_movements', 2);
        app(PosReturnService::class)->complete($administrator, $completed);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('customer_wallet_transactions', 1);
    }

    public function test_partial_return_uses_original_snapshot_rounding_and_prevents_over_return(): void
    {
        [$manager, $product, , $sale] = $this->completedSale('3.000', '100.00');
        $item = $sale->items->first();
        PosReturnSetting::firstOrCreate(['company_id' => $manager->company_id])->update(['manager_approval_required' => false]);
        $service = app(PosReturnService::class);
        $first = $service->create($manager, $sale, $this->payload($sale, $item->id, '1.000', 'cash', '100.00', 'one-third'));
        $this->assertSame('100.00', $first->refund_total);
        $this->assertSame(PosReturn::STATUS_APPROVED, $first->status);
        $service->complete($manager, $first);

        $sale = $sale->refresh()->load(['items', 'returns.items', 'payments']);
        $second = $service->create($manager, $sale, $this->payload($sale, $item->id, '2.000', 'cash', '200.00', 'remaining'));
        $this->assertSame('200.00', $second->refund_total);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->create($manager, $sale, $this->payload($sale, $item->id, '3.001', 'cash', '100.00', 'over-return'));
    }

    public function test_each_return_line_restores_stock_once_when_a_sale_contains_the_same_product_twice(): void
    {
        [$manager, $product, , $sale] = $this->completedSale('1.000', '120.00', true);
        PosReturnSetting::firstOrCreate(['company_id' => $manager->company_id])->update(['manager_approval_required' => false]);
        $return = app(PosReturnService::class)->create($manager, $sale, [
            'return_type' => 'full_return',
            'reason_code' => 'changed_mind',
            'receipt_confirmed' => true,
            'items' => $sale->items->map(fn ($item) => ['original_sale_item_id' => $item->id, 'return_quantity' => '1.000', 'stock_disposition' => 'restock'])->all(),
            'refunds' => [['original_payment_id' => $sale->payments->first()->id, 'method' => 'cash', 'amount' => '240.00']],
            'idempotency_key' => '66666666-6666-4666-8666-666666666666',
        ]);

        app(PosReturnService::class)->complete($manager, $return);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 6, 'quantity_available' => 6]);
        $this->assertDatabaseCount('stock_movements', 4);
        $this->assertSame(2, StockMovement::query()->whereNotNull('pos_return_item_id')->count());
    }

    public function test_external_refund_references_are_unique_within_a_company(): void
    {
        [$manager, , , $sale] = $this->completedSale('2.000', '60.00');
        PosReturnSetting::firstOrCreate(['company_id' => $manager->company_id])->update(['manager_approval_required' => false]);
        $service = app(PosReturnService::class);
        $item = $sale->items->first();

        $first = $service->create($manager, $sale, $this->payload($sale, $item->id, '1.000', 'cash', '60.00', 'reference-one', 'CASH-REF-001'));
        $service->complete($manager, $first);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->create($manager, $sale->refresh()->load(['items', 'returns.items', 'payments']), $this->payload($sale, $item->id, '1.000', 'cash', '60.00', 'reference-two', 'CASH-REF-001'));
    }

    public function test_return_creation_retry_reuses_completed_return_before_recalculation(): void
    {
        [$manager, , , $sale] = $this->completedSale();
        PosReturnSetting::firstOrCreate(['company_id' => $manager->company_id])->update(['manager_approval_required' => false]);
        $service = app(PosReturnService::class);
        $payload = $this->payload($sale, $sale->items->first()->id, '1.000', 'cash', '120.00', 'retry', 'CASH-RETRY-001');
        $return = $service->create($manager, $sale, $payload);
        $service->complete($manager, $return);

        $retry = $service->create($manager, $sale->refresh()->load(['items', 'returns.items', 'payments']), $payload);

        $this->assertSame($return->id, $retry->id);
        $this->assertDatabaseCount('pos_returns', 1);
        $this->assertDatabaseCount('pos_refunds', 1);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_return_routes_enforce_tenant_scope_and_valid_screen_loads(): void
    {
        [$manager, , , $sale] = $this->completedSale();
        $return = app(PosReturnService::class)->create($manager, $sale, $this->payload($sale, $sale->items->first()->id, '1.000', 'cash', '120.00'));
        $outsider = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($manager)->get('/pos/returns')->assertOk()->assertSee('Returns and refunds');
        $this->actingAs($manager)->get('/pos/returns/create?sale='.$sale->id)->assertOk()->assertSee('Create return');
        $this->actingAs($manager)->get('/pos/returns/'.$return->id)->assertOk()->assertSee($return->return_number);
        $this->actingAs($outsider)->get('/pos/returns/create?sale='.$sale->id)->assertNotFound();
        $staff = User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Staff]);
        $this->actingAs($staff)->get('/pos/returns')->assertForbidden();
    }

    public function test_window_and_idempotency_protect_return_creation(): void
    {
        [$manager, , , $sale] = $this->completedSale();
        PosReturnSetting::firstOrCreate(['company_id' => $manager->company_id])->update(['manager_approval_required' => false, 'return_window_days' => 1]);
        $sale->update(['completed_at' => now()->subDays(3), 'sold_at' => now()->subDays(3)]);
        $service = app(PosReturnService::class);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->create($manager, $sale->refresh(), $this->payload($sale, $sale->items->first()->id, '1.000', 'cash', '120.00'));
    }

    public function test_damaged_return_is_audited_without_restoring_saleable_stock(): void
    {
        [$manager, $product, , $sale] = $this->completedSale();
        PosReturnSetting::firstOrCreate(['company_id' => $manager->company_id])->update(['manager_approval_required' => false]);
        $payload = $this->payload($sale, $sale->items->first()->id, '1.000', 'cash', '120.00');
        $payload['reason_code'] = 'damaged';
        $payload['items'][0]['stock_disposition'] = 'damaged';
        $return = app(PosReturnService::class)->create($manager, $sale, $payload);
        app(PosReturnService::class)->complete($manager, $return);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 4, 'quantity_available' => 4]);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => PosReturn::class, 'reference_id' => $return->id, 'direction' => 'neutral', 'reason' => 'damaged']);
    }

    public function test_completed_returns_appear_in_the_authorized_sales_return_report(): void
    {
        [$manager, , , $sale] = $this->completedSale();
        PosReturnSetting::firstOrCreate(['company_id' => $manager->company_id])->update(['manager_approval_required' => false]);
        $return = app(PosReturnService::class)->create($manager, $sale, $this->payload($sale, $sale->items->first()->id, '1.000', 'cash', '120.00'));
        app(PosReturnService::class)->complete($manager, $return);
        $report = app(RetailReportingService::class)->report($manager, 'sales_returns', ['outlet_id' => (string) $manager->branch_id]);

        $this->assertSame(12000, $report['detail']['refund_total']);
        $this->assertSame($return->return_number, $report['detail']['rows'][0]['return_number']);
        $overview = app(RetailReportingService::class)->overview($manager, ['outlet_id' => (string) $manager->branch_id]);
        $this->assertSame(0, $overview['metrics']['net_sales']);
        $this->assertSame(12000, $overview['reports']['sales']['returns_total']);
    }

    public function test_pending_return_can_be_cancelled_without_posting_stock_or_refunds(): void
    {
        [$manager, $product, , $sale] = $this->completedSale();
        $return = app(PosReturnService::class)->create($manager, $sale, $this->payload($sale, $sale->items->first()->id, '1.000', 'cash', '120.00'));

        $cancelled = app(PosReturnService::class)->cancel($manager, $return);

        $this->assertSame(PosReturn::STATUS_CANCELLED, $cancelled->status);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity_on_hand' => 4]);
        $this->assertDatabaseCount('pos_refunds', 1);
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => PosReturn::class, 'reference_id' => $return->id]);
    }

    /** @return array{User,Product,Customer,PosSale} */
    private function completedSale(string $quantity = '1.000', string $total = '120.00', bool $duplicateLine = false): array
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['is_active' => true]);
        $manager = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $customer = Customer::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'customer_number' => 'CUS-RET-001', 'first_name' => 'Return', 'display_name' => 'Return Customer', 'phone' => '9000001001', 'status' => 'active', 'is_active' => true]);
        $category = InventoryCategory::create(['company_id' => $company->id, 'name' => 'Returns', 'slug' => 'returns', 'is_active' => true]);
        $unit = InventoryUnit::create(['company_id' => $company->id, 'name' => 'Piece', 'short_code' => 'PCS', 'type' => 'quantity', 'is_active' => true]);
        $product = Product::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'category_id' => $category->id, 'unit_id' => $unit->id, 'name' => 'Return item', 'slug' => 'return-item', 'sku' => 'RET-ITEM', 'barcode' => '8900000000101', 'selling_price' => $total, 'cost_price' => '60.00', 'track_inventory' => true, 'status' => 'active', 'is_active' => true]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'Return store', 'code' => 'RET-WH', 'type' => 'store', 'country' => 'India', 'is_active' => true]);
        $location = StockLocation::create(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'name' => 'Return bin', 'code' => 'RET-BIN', 'type' => 'bin', 'is_active' => true]);
        StockLevel::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'stock_location_id' => $location->id, 'product_id' => $product->id, 'quantity_on_hand' => $duplicateLine ? 6 : 5, 'quantity_available' => $duplicateLine ? 6 : 5]);
        $items = [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $total]];
        if ($duplicateLine) {
            $items[] = ['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $total];
        }
        $payment = number_format((float) $quantity * (float) $total * count($items), 2, '.', '');
        $this->actingAs($manager)->post('/pos/checkout', ['customer_id' => $customer->id, 'items' => $items, 'payments' => [['method' => 'cash', 'amount' => $payment]]])->assertRedirect();

        return [$manager, $product, $customer, PosSale::query()->with(['items', 'payments'])->firstOrFail()];
    }

    private function payload(PosSale $sale, int $saleItemId, string $quantity, string $method, string $amount, string $key = 'request', ?string $externalReference = null): array
    {
        return ['return_type' => 'partial_return', 'reason_code' => 'changed_mind', 'receipt_confirmed' => true, 'items' => [['original_sale_item_id' => $saleItemId, 'return_quantity' => $quantity, 'stock_disposition' => 'restock']], 'refunds' => [['original_payment_id' => $sale->payments->first()->id, 'method' => $method, 'amount' => $amount, 'external_reference' => $externalReference]], 'idempotency_key' => match ($key) { 'one-third' => '77777777-7777-4777-8777-777777777777', 'remaining' => '88888888-8888-4888-8888-888888888888', 'reference-one' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'reference-two' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'retry' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', default => '99999999-9999-4999-8999-999999999999' }];
    }
}
