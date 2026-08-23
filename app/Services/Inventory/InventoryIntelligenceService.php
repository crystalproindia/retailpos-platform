<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use App\Services\Reports\ProfitabilityReportingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryIntelligenceService
{
    private const DETAIL_LIMIT = 500;

    public function __construct(
        private readonly InventoryLocationAccessService $locations,
        private readonly ProfitabilityReportingService $profitability,
        private readonly OutletAccessService $outlets,
    ) {}

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function dashboard(User $user, array $filters = []): array
    {
        $settings = $this->settings($user->company_id);
        $period = (int) ($filters['velocity_period'] ?? 30);
        $warehouseIds = $this->authorizedWarehouseIds($user, $filters['warehouse_id'] ?? null, $filters['outlet_id'] ?? null);
        $range = $this->range($user, $period);
        $stock = $this->stockRows($user, $warehouseIds, $filters)->get();
        $imageUrls = Product::query()
            ->where('company_id', $user->company_id)
            ->whereIn('id', $stock->pluck('product_id'))
            ->get()
            ->mapWithKeys(fn (Product $product): array => [$product->id => $product->imageUrl(true)]);
        $movement = $this->movementEvidence($user, $warehouseIds, $range['from'])->keyBy(fn ($row) => $row->warehouse_id.'-'.$row->product_id);
        $incoming = $this->incomingPurchaseQuantities($user, $warehouseIds)->keyBy(fn ($row) => $row->warehouse_id.'-'.$row->product_id);
        $suppliers = $this->supplierEvidence($user)->keyBy('product_id');
        $profitability = $this->profitabilityByProduct($user, $warehouseIds, $range, $filters);

        $rows = $stock->map(function ($row) use ($movement, $incoming, $suppliers, $profitability, $imageUrls, $period, $settings): array {
            $key = $row->warehouse_id.'-'.$row->product_id;
            $evidence = $movement->get($key);
            $available = (float) $row->quantity_available;
            $onHand = (float) $row->quantity_on_hand;
            $sold = max(0, (float) ($evidence->sold_quantity ?? 0) - (float) ($evidence->returned_quantity ?? 0));
            $velocity = $sold / $period;
            $minimum = (float) ($row->minimum_stock ?? $row->reorder_point ?? 0);
            $reorderPoint = (float) ($row->reorder_point ?? $minimum);
            $maximum = (float) ($row->maximum_stock ?? 0);
            $safety = (float) ($row->safety_stock ?? $minimum);
            $incomingQuantity = max(0, (float) ($incoming->get($key)->incoming_quantity ?? 0));
            $leadDays = max(1, (int) ($row->supplier_lead_time_days ?? $settings['default_lead_time_days']));
            $projectedNeed = max($reorderPoint, $minimum, $safety + ($velocity * $leadDays));
            $target = $maximum > 0 ? $maximum : max($projectedNeed, $reorderPoint + max((float) ($row->reorder_quantity ?? 0), $velocity * $period));
            $suggested = max(0, $target - $available - $incomingQuantity);
            $costMinor = $this->minor($row->cost_price ?? $row->purchase_price ?? 0);
            $stockValue = $this->quantityValue($onHand, $costMinor);
            $daysRemaining = $velocity > 0 ? round(max(0, $available) / $velocity, 1) : null;
            $lastSale = $evidence?->last_sale_at ? CarbonImmutable::parse($evidence->last_sale_at) : null;
            $lastInbound = $evidence?->last_inbound_at ? CarbonImmutable::parse($evidence->last_inbound_at) : null;
            $firstInbound = $evidence?->first_inbound_at ? CarbonImmutable::parse($evidence->first_inbound_at) : null;
            $daysSinceSale = $lastSale?->diffInDays(now()) ?? null;
            $stockAge = $lastInbound?->diffInDays(now()) ?? null;
            $newlyStocked = $firstInbound && $firstInbound->greaterThanOrEqualTo(now()->subDays($settings['new_stock_grace_days']));
            $dead = $onHand > 0 && ! $newlyStocked && ($lastSale === null || $daysSinceSale >= $settings['dead_stock_days']);
            $slow = $onHand > 0 && ! $dead && ! $newlyStocked && $sold <= $settings['slow_mover_max_units'];
            $fast = $sold >= $settings['fast_mover_min_units'] && $velocity >= $settings['fast_mover_min_daily_velocity'];
            $overstocked = $maximum > 0 && $available > $maximum;
            $reorder = $available <= $reorderPoint || ($daysRemaining !== null && $daysRemaining <= $leadDays);
            $profit = $profitability->get((int) $row->product_id);
            $margin = $profit['margin_percent'] ?? null;
            $costCoverage = $profit['revenue_cost_coverage_percent'] ?? null;
            $health = match (true) {
                $dead => 'dead_stock',
                $overstocked => 'overstocked',
                $reorder && $fast => 'reorder_soon',
                $slow => 'slow_moving',
                $fast && $margin !== null && (float) $margin >= 20 => 'star',
                $costCoverage !== null && (float) $costCoverage < 100 => 'cost_unavailable',
                default => 'healthy',
            };
            $supplier = $suppliers->get((int) $row->product_id);
            $purchaseCostMinor = $this->minor($supplier?->last_purchase_price ?? $supplier?->purchase_price ?? $row->purchase_price ?? $row->cost_price ?? 0);

            return [
                'product_id' => (int) $row->product_id,
                'product' => $row->product_name,
                'image_url' => $imageUrls->get((int) $row->product_id),
                'sku' => $row->sku,
                'category' => $row->category_name ?: 'Uncategorized',
                'brand' => $row->brand_name ?: 'Unbranded',
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse' => $row->warehouse_name,
                'outlet_id' => $row->branch_id ? (int) $row->branch_id : null,
                'outlet' => $row->branch_name ?: 'Unassigned',
                'on_hand' => $onHand,
                'available' => $available,
                'minimum' => $minimum,
                'maximum' => $maximum,
                'safety_stock' => $safety,
                'incoming' => $incomingQuantity,
                'units_sold' => $sold,
                'daily_velocity' => round($velocity, 3),
                'days_remaining' => $daysRemaining,
                'last_sale_at' => $lastSale,
                'last_inbound_at' => $lastInbound,
                'days_since_sale' => $daysSinceSale,
                'stock_age_days' => $stockAge,
                'stock_value_minor' => $stockValue,
                'suggested_reorder_quantity' => round($reorder ? $suggested : 0, 3),
                'recommended_purchase_value_minor' => $this->quantityValue($reorder ? $suggested : 0, $purchaseCostMinor),
                'supplier_id' => $supplier?->supplier_id ? (int) $supplier->supplier_id : null,
                'supplier' => $supplier?->supplier_name,
                'purchase_cost_minor' => $purchaseCostMinor,
                'net_sales_minor' => $profit['net_sales'] ?? 0,
                'gross_profit_minor' => $profit['gross_profit'] ?? 0,
                'margin_percent' => $margin,
                'cost_coverage_percent' => $costCoverage,
                'is_low' => $available > 0 && $available < $minimum,
                'is_at_minimum' => $available > 0 && abs($available - $minimum) < 0.0005,
                'is_out' => $available <= 0,
                'is_overstocked' => $overstocked,
                'is_fast' => $fast,
                'is_slow' => $slow,
                'is_dead' => $dead,
                'is_new' => (bool) $newlyStocked,
                'needs_reorder' => $reorder && $suggested > 0,
                'health' => $health,
                'reason' => $this->reason($available, $minimum, $daysRemaining, $leadDays, $fast),
            ];
        })->values();

        $transferRecommendations = $this->transferRecommendations($rows);
        $aging = $this->aging($rows);
        $visibleRows = $this->applyAgingFilter($this->applyStatusFilter($rows, $filters['stock_status'] ?? null), $filters['aging_range'] ?? null);

        return [
            'cards' => [
                'stock_value_minor' => $rows->sum('stock_value_minor'),
                'units_on_hand' => round($rows->sum('on_hand'), 3),
                'low_stock_count' => $rows->where('is_low', true)->count() + $rows->where('is_at_minimum', true)->count(),
                'out_of_stock_count' => $rows->where('is_out', true)->count(),
                'dead_stock_value_minor' => $rows->where('is_dead', true)->sum('stock_value_minor'),
                'slow_stock_value_minor' => $rows->where('is_slow', true)->sum('stock_value_minor'),
                'fast_product_count' => $rows->where('is_fast', true)->count(),
                'reorder_value_minor' => $rows->where('needs_reorder', true)->sum('recommended_purchase_value_minor'),
                'transfer_opportunity_count' => $transferRecommendations->count(),
            ],
            'rows' => $visibleRows->take(self::DETAIL_LIMIT),
            'reorder' => $rows->where('needs_reorder', true)->sortByDesc('daily_velocity')->take(100)->values(),
            'fast' => $rows->where('is_fast', true)->sortByDesc('daily_velocity')->take(100)->values(),
            'slow' => $rows->where('is_slow', true)->sortByDesc('stock_value_minor')->take(100)->values(),
            'dead' => $rows->where('is_dead', true)->sortByDesc('stock_value_minor')->take(100)->values(),
            'transfers' => $transferRecommendations->take(100),
            'aging' => $aging,
            'value_by_category' => $this->groupValue($rows, 'category'),
            'value_by_warehouse' => $this->groupValue($rows, 'warehouse'),
            'settings' => $settings,
            'period' => $period,
            'scope_count' => $warehouseIds->count(),
            'methodology' => 'Velocity is completed POS sale quantity less completed returns during the selected window. Aging assigns each current stock row to its latest qualifying inbound movement; it is an operational approximation, not FIFO layer aging.',
        ];
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    public function exportRows(User $user, string $dataset, array $filters): Collection
    {
        $dashboard = $this->dashboard($user, $filters);

        return match ($dataset) {
            'reorder' => $dashboard['reorder'],
            'fast' => $dashboard['fast'],
            'slow' => $dashboard['slow'],
            'dead' => $dashboard['dead'],
            'transfers' => $dashboard['transfers'],
            'aging' => collect($dashboard['aging']),
            default => abort(404),
        };
    }

    /** @param array<string, mixed> $filters */
    private function stockRows(User $user, Collection $warehouseIds, array $filters): Builder
    {
        return DB::table('stock_levels as level')
            ->join('products as product', 'product.id', '=', 'level.product_id')
            ->join('warehouses as warehouse', 'warehouse.id', '=', 'level.warehouse_id')
            ->leftJoin('branches as branch', 'branch.id', '=', 'warehouse.branch_id')
            ->leftJoin('inventory_categories as category', 'category.id', '=', 'product.category_id')
            ->leftJoin('inventory_brands as brand', 'brand.id', '=', 'product.brand_id')
            ->where('level.company_id', $user->company_id)
            ->whereIn('level.warehouse_id', $warehouseIds)
            ->whereNull('product.deleted_at')
            ->where('product.track_inventory', true)
            ->when($filters['product_id'] ?? null, fn (Builder $query, $id) => $query->where('product.id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('product.category_id', $id))
            ->when($filters['brand_id'] ?? null, fn (Builder $query, $id) => $query->where('product.brand_id', $id))
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, $id) => $query->whereExists(fn (Builder $supplier) => $supplier->selectRaw('1')->from('supplier_products')->whereColumn('supplier_products.product_id', 'product.id')->where('supplier_products.company_id', $user->company_id)->where('supplier_products.supplier_id', $id)->where('supplier_products.is_active', true)->whereNull('supplier_products.deleted_at')))
            ->groupBy('product.id', 'product.name', 'product.sku', 'product.cost_price', 'product.purchase_price', 'category.name', 'brand.name', 'warehouse.id', 'warehouse.name', 'warehouse.branch_id', 'branch.name')
            ->selectRaw('product.id product_id, product.name product_name, product.sku, product.cost_price, product.purchase_price, category.name category_name, brand.name brand_name, warehouse.id warehouse_id, warehouse.name warehouse_name, warehouse.branch_id branch_id, branch.name branch_name, SUM(level.quantity_on_hand) quantity_on_hand, SUM(level.quantity_available) quantity_available, MAX(level.minimum_stock) minimum_stock, MAX(level.maximum_stock) maximum_stock, MAX(level.reorder_point) reorder_point, MAX(level.reorder_quantity) reorder_quantity, MAX(level.safety_stock) safety_stock, MAX(level.supplier_lead_time_days) supplier_lead_time_days')
            ->orderBy('product.name')
            ->limit(self::DETAIL_LIMIT);
    }

    private function movementEvidence(User $user, Collection $warehouseIds, CarbonImmutable $from): Collection
    {
        return DB::table('stock_movements')
            ->where('company_id', $user->company_id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->groupBy('warehouse_id', 'product_id')
            ->selectRaw("warehouse_id, product_id, SUM(CASE WHEN movement_type = 'sale' AND occurred_at >= ? THEN quantity ELSE 0 END) sold_quantity, SUM(CASE WHEN movement_type = 'sale_return' AND occurred_at >= ? THEN quantity ELSE 0 END) returned_quantity, MAX(CASE WHEN movement_type = 'sale' THEN occurred_at END) last_sale_at, MAX(CASE WHEN direction = 'in' THEN occurred_at END) last_inbound_at, MIN(CASE WHEN direction = 'in' THEN occurred_at END) first_inbound_at", [$from->utc(), $from->utc()])
            ->get();
    }

    private function incomingPurchaseQuantities(User $user, Collection $warehouseIds): Collection
    {
        return DB::table('purchase_order_items as item')
            ->join('purchase_orders as purchase', 'purchase.id', '=', 'item.purchase_order_id')
            ->where('purchase.company_id', $user->company_id)
            ->whereIn('purchase.warehouse_id', $warehouseIds)
            ->whereIn('purchase.status', ['approved', 'sent', 'supplier_confirmed', 'partially_received'])
            ->whereNull('purchase.deleted_at')
            ->groupBy('purchase.warehouse_id', 'item.product_id')
            ->selectRaw('purchase.warehouse_id, item.product_id, SUM(CASE WHEN COALESCE(item.pending_quantity, item.ordered_quantity - item.received_quantity) > 0 THEN COALESCE(item.pending_quantity, item.ordered_quantity - item.received_quantity) ELSE 0 END) incoming_quantity')
            ->get();
    }

    private function supplierEvidence(User $user): Collection
    {
        return DB::table('supplier_products as link')
            ->join('suppliers as supplier', 'supplier.id', '=', 'link.supplier_id')
            ->where('link.company_id', $user->company_id)
            ->where('link.is_active', true)
            ->whereNull('link.deleted_at')
            ->whereNull('supplier.deleted_at')
            ->orderByDesc('link.is_preferred')
            ->orderByDesc('link.last_purchased_at')
            ->get(['link.product_id', 'link.supplier_id', 'supplier.name as supplier_name', 'link.purchase_price', 'link.last_purchase_price'])
            ->unique('product_id');
    }

    private function profitabilityByProduct(User $user, Collection $warehouseIds, array $range, array $filters): Collection
    {
        $branchIds = DB::table('warehouses')->whereIn('id', $warehouseIds)->whereNotNull('branch_id')->pluck('branch_id')->unique()->values()->all();
        $report = $this->profitability->report($user, ['ids' => $user->isAdministrator() ? $branchIds : $branchIds, 'warehouse_id' => $filters['warehouse_id'] ?? null], $range, [
            'product_id' => $filters['product_id'] ?? null,
            'category_id' => $filters['category_id'] ?? null,
            'brand_id' => $filters['brand_id'] ?? null,
            'source' => 'pos',
        ]);

        $productIds = DB::table('products')->where('company_id', $user->company_id)->whereIn('sku', collect($report['product_rows'])->pluck('sku')->filter())->pluck('id', 'sku');

        return collect($report['product_rows'])->keyBy(fn (array $row) => (int) ($productIds[$row['sku']] ?? 0));
    }

    private function transferRecommendations(Collection $rows): Collection
    {
        return $rows->groupBy('product_id')->flatMap(function (Collection $productRows): Collection {
            $sources = $productRows->filter(fn (array $row) => $row['available'] > max($row['maximum'] ?: $row['minimum'], $row['safety_stock']));
            $targets = $productRows->filter(fn (array $row) => $row['needs_reorder']);

            return $targets->flatMap(function (array $target) use ($sources): Collection {
                $need = $target['suggested_reorder_quantity'];

                return $sources->filter(fn (array $source) => $source['warehouse_id'] !== $target['warehouse_id'])
                    ->sortByDesc('available')->take(1)->map(function (array $source) use ($target, $need): array {
                        $sourceFloor = max($source['maximum'] ?: $source['minimum'], $source['safety_stock']);
                        $excess = max(0, $source['available'] - $sourceFloor);
                        $quantity = round(min($excess, $need), 3);

                        return [
                            'product_id' => $target['product_id'], 'product' => $target['product'], 'sku' => $target['sku'], 'image_url' => $target['image_url'],
                            'source_warehouse_id' => $source['warehouse_id'], 'source_warehouse' => $source['warehouse'], 'source_stock' => $source['available'], 'source_safety_stock' => $sourceFloor,
                            'destination_warehouse_id' => $target['warehouse_id'], 'destination_warehouse' => $target['warehouse'], 'destination_stock' => $target['available'],
                            'suggested_quantity' => $quantity,
                            'reason' => $source['is_slow'] ? 'Move slow stock to a replenishment need.' : 'Balance excess stock against a replenishment need.',
                        ];
                    })->filter(fn (array $row) => $row['suggested_quantity'] > 0);
            });
        })->values();
    }

    private function aging(Collection $rows): array
    {
        $buckets = collect([
            '0_30' => ['label' => '0-30 days', 'quantity' => 0.0, 'value_minor' => 0],
            '31_60' => ['label' => '31-60 days', 'quantity' => 0.0, 'value_minor' => 0],
            '61_90' => ['label' => '61-90 days', 'quantity' => 0.0, 'value_minor' => 0],
            '91_180' => ['label' => '91-180 days', 'quantity' => 0.0, 'value_minor' => 0],
            '180_plus' => ['label' => '180+ days', 'quantity' => 0.0, 'value_minor' => 0],
            'unknown' => ['label' => 'Unknown', 'quantity' => 0.0, 'value_minor' => 0],
        ]);
        foreach ($rows->where('on_hand', '>', 0) as $row) {
            $days = $row['stock_age_days'];
            $key = match (true) {
                $days === null => 'unknown', $days <= 30 => '0_30', $days <= 60 => '31_60', $days <= 90 => '61_90', $days <= 180 => '91_180', default => '180_plus'
            };
            $bucket = $buckets[$key];
            $bucket['quantity'] += $row['on_hand'];
            $bucket['value_minor'] += $row['stock_value_minor'];
            $buckets[$key] = $bucket;
        }
        $total = max(1, $buckets->sum('value_minor'));

        return $buckets->map(fn (array $bucket) => $bucket + ['percentage' => round(($bucket['value_minor'] / $total) * 100, 2)])->values()->all();
    }

    private function groupValue(Collection $rows, string $field): Collection
    {
        return $rows->groupBy($field)->map(fn (Collection $group, string $label) => ['label' => $label, 'value_minor' => $group->sum('stock_value_minor')])->sortByDesc('value_minor')->take(8)->values();
    }

    private function applyStatusFilter(Collection $rows, ?string $status): Collection
    {
        return match ($status) {
            'low' => $rows->filter(fn (array $row) => $row['is_low'] || $row['is_at_minimum']),
            'out' => $rows->where('is_out', true), 'fast' => $rows->where('is_fast', true), 'slow' => $rows->where('is_slow', true),
            'dead' => $rows->where('is_dead', true), 'overstocked' => $rows->where('is_overstocked', true), 'reorder' => $rows->where('needs_reorder', true),
            default => $rows,
        };
    }

    private function applyAgingFilter(Collection $rows, ?string $range): Collection
    {
        return $rows->filter(function (array $row) use ($range): bool {
            $days = $row['stock_age_days'];

            return match ($range) {
                '0_30' => $days !== null && $days <= 30,
                '31_60' => $days !== null && $days >= 31 && $days <= 60,
                '61_90' => $days !== null && $days >= 61 && $days <= 90,
                '91_180' => $days !== null && $days >= 91 && $days <= 180,
                '180_plus' => $days !== null && $days > 180,
                'unknown' => $days === null,
                default => true,
            };
        })->values();
    }

    private function authorizedWarehouseIds(User $user, mixed $warehouseId, mixed $outletId = null): Collection
    {
        $warehouses = $this->locations->accessibleWarehouses($user, false);
        if (filled($outletId) && $outletId !== 'all') {
            abort_unless($this->outlets->accessibleOutlets($user, false)->contains('id', (int) $outletId), 403);
            $warehouses = $warehouses->where('branch_id', (int) $outletId);
        }
        $ids = $warehouses->pluck('id');
        if ($warehouseId) {
            abort_unless($ids->contains((int) $warehouseId), 403);

            return collect([(int) $warehouseId]);
        }

        return $ids;
    }

    /** @return array<string, int|float> */
    private function settings(int $companyId): array
    {
        $stored = Setting::query()->where('company_id', $companyId)->where('group', 'inventory')->get()->mapWithKeys(fn (Setting $setting) => [$setting->key => data_get($setting->value, 'value')]);

        return [
            'dead_stock_days' => (int) $stored->get('dead_stock_days', 120),
            'new_stock_grace_days' => (int) $stored->get('new_stock_grace_days', 30),
            'slow_mover_max_units' => (float) $stored->get('slow_mover_max_units', 2),
            'fast_mover_min_units' => (float) $stored->get('fast_mover_min_units', 10),
            'fast_mover_min_daily_velocity' => (float) $stored->get('fast_mover_min_daily_velocity', 0.25),
            'default_lead_time_days' => (int) $stored->get('default_lead_time_days', 7),
        ];
    }

    private function range(User $user, int $days): array
    {
        $timezone = $user->company?->timezone ?: config('app.timezone');
        $to = CarbonImmutable::now($timezone)->endOfDay();

        return ['from' => $to->subDays($days - 1)->startOfDay(), 'to' => $to, 'timezone' => $timezone];
    }

    private function reason(float $available, float $minimum, ?float $days, int $leadDays, bool $fast): string
    {
        return match (true) {
            $available <= 0 => 'Out of stock', $available < $minimum => 'Below minimum stock',
            $days !== null && $days <= $leadDays => 'Projected stockout within supplier lead time',
            $fast => 'High recent velocity', default => 'Configured replenishment threshold',
        };
    }

    private function minor(mixed $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) ($value ?? 0), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function quantityValue(float $quantity, int $unitMinor): int
    {
        return (int) round($quantity * $unitMinor);
    }
}
