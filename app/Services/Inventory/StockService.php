<?php

namespace App\Services\Inventory;

use App\Events\Domain\Inventory\LowStockDetected;
use App\Events\Domain\Inventory\OpeningStockRecorded;
use App\Events\Domain\Inventory\OutOfStockDetected;
use App\Events\Domain\Inventory\StockAdjusted;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use App\Repositories\Inventory\StockRepository;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(
        private readonly StockRepository $stocks,
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordOpeningStock(User $user, array $data): StockMovement
    {
        return DB::transaction(function () use ($user, $data): StockMovement {
            $product = Product::query()->where('company_id', $user->company_id)->findOrFail((int) $data['product_id']);
            $locationId = $data['stock_location_id'] ?? null;

            $duplicate = StockMovement::query()
                ->where('company_id', $user->company_id)
                ->where('warehouse_id', $data['warehouse_id'])
                ->where('product_id', $product->id)
                ->where('movement_type', 'opening')
                ->when($locationId, fn ($query) => $query->where('stock_location_id', $locationId), fn ($query) => $query->whereNull('stock_location_id'))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'product_id' => 'Opening stock already exists for this product and location. Use stock adjustment for changes.',
                ]);
            }

            $level = $this->stocks->level($user->company_id, (int) $data['warehouse_id'], $locationId ? (int) $locationId : null, $product->id);
            $quantityBefore = (float) $level->quantity_on_hand;
            $quantityAfter = (float) $data['quantity'];

            if ($quantityAfter < 0 && ! $product->allow_negative_stock) {
                throw ValidationException::withMessages(['quantity' => 'This product does not allow negative stock.']);
            }

            $this->setLevelQuantity($level, $quantityAfter, $user->branch_id);

            $movement = StockMovement::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'warehouse_id' => $data['warehouse_id'],
                'stock_location_id' => $locationId,
                'product_id' => $product->id,
                'movement_type' => 'opening',
                'direction' => 'initial',
                'quantity' => abs($quantityAfter - $quantityBefore),
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'unit_cost' => $data['unit_cost'] ?? $product->cost_price,
                'reason' => 'Opening stock',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'occurred_at' => now(),
            ]);

            $this->auditLogger->record('inventory.stock.opening_recorded', $movement, 'Opening stock recorded');
            $this->domainEvents->dispatch(new OpeningStockRecorded(
                companyId: $user->company_id,
                actorId: $user->id,
                aggregateType: StockMovement::class,
                aggregateId: $movement->id,
                payload: $this->movementPayload($movement),
            ));
            $this->dispatchStockThresholdEvents($product, $level, $user);

            return $movement;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAdjustment(User $user, array $data): StockAdjustment
    {
        return DB::transaction(function () use ($user, $data): StockAdjustment {
            $adjustment = StockAdjustment::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'warehouse_id' => $data['warehouse_id'],
                'adjustment_number' => 'ADJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'status' => StockAdjustment::STATUS_DRAFT,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $item) {
                $level = $this->stocks->level($user->company_id, (int) $data['warehouse_id'], isset($item['stock_location_id']) ? (int) $item['stock_location_id'] : null, (int) $item['product_id']);
                $adjusted = (float) $item['adjusted_quantity'];
                $current = (float) $level->quantity_on_hand;

                $adjustment->items()->create([
                    'product_id' => $item['product_id'],
                    'stock_location_id' => $item['stock_location_id'] ?? null,
                    'current_quantity' => $current,
                    'adjusted_quantity' => $adjusted,
                    'difference' => $adjusted - $current,
                    'reason' => $item['reason'] ?? null,
                ]);
            }

            $this->auditLogger->record('inventory.stock.adjustment_created', $adjustment, 'Stock adjustment draft created');

            return $adjustment->load('items.product');
        });
    }

    public function approveAdjustment(StockAdjustment $adjustment, User $user): StockAdjustment
    {
        if ($adjustment->status === StockAdjustment::STATUS_APPROVED) {
            return $adjustment;
        }

        return DB::transaction(function () use ($adjustment, $user): StockAdjustment {
            $adjustment->load('items.product');

            foreach ($adjustment->items as $item) {
                if ((float) $item->adjusted_quantity < 0 && ! $item->product->allow_negative_stock) {
                    throw ValidationException::withMessages([
                        'items' => "{$item->product->name} does not allow negative stock.",
                    ]);
                }
            }

            foreach ($adjustment->items as $item) {
                $level = $this->stocks->level($adjustment->company_id, $adjustment->warehouse_id, $item->stock_location_id, $item->product_id);
                $before = (float) $level->quantity_on_hand;
                $after = (float) $item->adjusted_quantity;
                $difference = $after - $before;

                $this->setLevelQuantity($level, $after, $adjustment->branch_id);

                StockMovement::create([
                    'company_id' => $adjustment->company_id,
                    'branch_id' => $adjustment->branch_id,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'stock_location_id' => $item->stock_location_id,
                    'product_id' => $item->product_id,
                    'movement_type' => 'adjustment',
                    'direction' => $difference > 0 ? 'in' : ($difference < 0 ? 'out' : 'neutral'),
                    'quantity' => abs($difference),
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reference_type' => StockAdjustment::class,
                    'reference_id' => $adjustment->id,
                    'reason' => $adjustment->reason,
                    'notes' => $item->reason,
                    'created_by' => $user->id,
                    'occurred_at' => now(),
                ]);

                $this->dispatchStockThresholdEvents($item->product, $level, $user);
            }

            $adjustment->update([
                'status' => StockAdjustment::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->auditLogger->record('inventory.stock.adjustment_approved', $adjustment, 'Stock adjustment approved');
            $this->domainEvents->dispatch(new StockAdjusted(
                companyId: $adjustment->company_id,
                actorId: $user->id,
                aggregateType: StockAdjustment::class,
                aggregateId: $adjustment->id,
                payload: [
                    'adjustment_id' => $adjustment->id,
                    'adjustment_number' => $adjustment->adjustment_number,
                    'items_count' => $adjustment->items->count(),
                ],
            ));

            return $adjustment->refresh()->load('items.product');
        });
    }

    private function setLevelQuantity(StockLevel $level, float $quantity, ?int $branchId): void
    {
        $reserved = (float) $level->quantity_reserved;

        $level->update([
            'branch_id' => $branchId,
            'quantity_on_hand' => $quantity,
            'quantity_available' => $quantity - $reserved,
            'last_stock_movement_at' => now(),
        ]);
    }

    private function dispatchStockThresholdEvents(Product $product, StockLevel $level, User $user): void
    {
        $available = (float) $level->quantity_available;

        if ($available <= 0) {
            $this->domainEvents->dispatch(new OutOfStockDetected(
                companyId: $product->company_id,
                actorId: $user->id,
                aggregateType: Product::class,
                aggregateId: $product->id,
                payload: $this->stockPayload($product, $level, 'out'),
            ));

            return;
        }

        $threshold = (float) ($level->reorder_point ?? $level->minimum_stock ?? 0);
        if ($threshold > 0 && $available <= $threshold) {
            $this->domainEvents->dispatch(new LowStockDetected(
                companyId: $product->company_id,
                actorId: $user->id,
                aggregateType: Product::class,
                aggregateId: $product->id,
                payload: $this->stockPayload($product, $level, 'low'),
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function movementPayload(StockMovement $movement): array
    {
        return [
            'movement_id' => $movement->id,
            'product_id' => $movement->product_id,
            'warehouse_id' => $movement->warehouse_id,
            'stock_location_id' => $movement->stock_location_id,
            'movement_type' => $movement->movement_type,
            'quantity_after' => $movement->quantity_after,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockPayload(Product $product, StockLevel $level, string $status): array
    {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'warehouse_id' => $level->warehouse_id,
            'stock_location_id' => $level->stock_location_id,
            'quantity_available' => $level->quantity_available,
            'threshold_status' => $status,
        ];
    }
}
