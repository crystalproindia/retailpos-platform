<?php

namespace App\Services\Purchases;

use App\Enums\Purchases\GoodsReceiptStatus;
use App\Events\Domain\Purchases\PurchaseDomainEvent;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Product;
use App\Models\Purchases\GoodsReceipt;
use App\Models\Purchases\GoodsReceiptItem;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseOrderItem;
use App\Models\Purchases\SupplierProduct;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(
        private readonly PurchaseNumberService $numbers,
        private readonly PurchaseOrderService $orders,
        private readonly SupplierScoreService $scores,
        private readonly StockService $stockService,
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($user, $data): GoodsReceipt {
            if (! empty($data['idempotency_key'])) {
                $existing = GoodsReceipt::query()
                    ->where('company_id', $user->company_id)
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($existing) {
                    return $existing->load(['supplier', 'warehouse', 'purchaseOrder.items', 'items.product']);
                }
            }
            if (empty($data['purchase_order_id']) && ! $this->numbers->settings($user->company_id)->allow_receive_without_po) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Receiving without a purchase order is disabled in purchase settings.',
                ]);
            }

            $order = empty($data['purchase_order_id'])
                ? null
                : PurchaseOrder::query()
                    ->with('items')
                    ->where('company_id', $user->company_id)
                    ->lockForUpdate()
                    ->findOrFail((int) $data['purchase_order_id']);

            if ($order && ! in_array($order->status->value, ['approved', 'sent', 'supplier_confirmed', 'partially_received'], true)) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Goods can only be received against an approved, sent, confirmed, or partially received purchase order.']);
            }

            $receipt = GoodsReceipt::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'warehouse_id' => $order?->warehouse_id ?? $data['warehouse_id'],
                'supplier_id' => $order?->supplier_id ?? $data['supplier_id'],
                'purchase_order_id' => $order?->id,
                'grn_number' => $this->numbers->next($user->company_id, 'grn'),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'status' => $data['status'] ?? GoodsReceiptStatus::Draft->value,
                'received_by' => $user->id,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'supplier_invoice_date' => $data['supplier_invoice_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $orderItem = isset($item['purchase_order_item_id'])
                    ? PurchaseOrderItem::query()
                        ->whereHas('purchaseOrder', fn ($query) => $query->where('company_id', $user->company_id))
                        ->findOrFail((int) $item['purchase_order_item_id'])
                    : ($order?->items->firstWhere('product_id', (int) ($item['product_id'] ?? 0)));

                $acceptedQuantity = $this->mills($item['accepted_quantity'] ?? $item['received_quantity']);
                $receivedQuantity = $this->mills($item['received_quantity']);
                $rejectedQuantity = $this->mills($item['rejected_quantity'] ?? max(0, $receivedQuantity - $acceptedQuantity));
                $damagedQuantity = $this->mills($item['damaged_quantity'] ?? 0);
                if ($acceptedQuantity < 0 || $rejectedQuantity < 0 || $damagedQuantity < 0 || $acceptedQuantity + $rejectedQuantity + $damagedQuantity > $receivedQuantity) {
                    throw ValidationException::withMessages(['items' => 'Accepted, rejected, and damaged quantities cannot exceed the received quantity.']);
                }
                if ($orderItem) {
                    $remaining = $this->mills($orderItem->pending_quantity);
                    if ($acceptedQuantity > $remaining) {
                        throw ValidationException::withMessages(['items' => "Accepted quantity for {$orderItem->product_name_snapshot} exceeds the remaining PO quantity."]);
                    }
                }

                $receipt->items()->create([
                    'purchase_order_item_id' => $orderItem?->id,
                    'product_id' => $orderItem?->product_id ?? $item['product_id'],
                    'stock_location_id' => $item['stock_location_id'] ?? null,
                    'ordered_quantity' => $orderItem?->ordered_quantity ?? $item['ordered_quantity'] ?? null,
                    'received_quantity' => $this->quantityDecimal($receivedQuantity),
                    'accepted_quantity' => $this->quantityDecimal($acceptedQuantity),
                    'rejected_quantity' => $this->quantityDecimal($rejectedQuantity),
                    'damaged_quantity' => $this->quantityDecimal($damagedQuantity),
                    'short_quantity' => $this->quantityDecimal(max(0, $this->mills($orderItem?->pending_quantity) - $acceptedQuantity)),
                    'unit_cost' => $item['unit_cost'] ?? $orderItem?->unit_price ?? 0,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'manufacture_date' => $item['manufacture_date'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $this->auditLogger->record('purchase.goods_receipt.created', $receipt, 'Goods receipt created');

            return $receipt->refresh()->load(['supplier', 'warehouse', 'purchaseOrder.items', 'items.product']);
        });
    }

    public function receive(GoodsReceipt $receipt, User $user): GoodsReceipt
    {
        if ($receipt->posted_at || $receipt->status === GoodsReceiptStatus::Received || $receipt->status === GoodsReceiptStatus::Closed) {
            return $receipt;
        }

        return DB::transaction(function () use ($receipt, $user): GoodsReceipt {
            $receipt = GoodsReceipt::query()->with(['items.purchaseOrderItem', 'items.product', 'purchaseOrder.items', 'supplier'])->lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->posted_at) {
                return $receipt;
            }

            foreach ($receipt->items as $item) {
                if ($this->mills($item->accepted_quantity) <= 0) {
                    continue;
                }

                $batch = $this->recordBatch($receipt, $item);

                $this->stockService->recordPurchaseReceipt($user, [
                    'branch_id' => $receipt->branch_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'stock_location_id' => $item->stock_location_id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->accepted_quantity,
                    'unit_cost' => $item->unit_cost,
                    'inventory_batch_id' => $batch?->id,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'reason' => 'Goods receipt '.$receipt->grn_number,
                    'notes' => $item->notes,
                ]);

                if ($item->purchaseOrderItem) {
                    $received = $this->mills($item->purchaseOrderItem->received_quantity) + $this->mills($item->accepted_quantity);
                    $pending = max(0, $this->mills($item->purchaseOrderItem->ordered_quantity) - $received);
                    $item->purchaseOrderItem->update([
                        'received_quantity' => $this->quantityDecimal($received),
                        'pending_quantity' => $this->quantityDecimal($pending),
                    ]);
                }

                SupplierProduct::query()
                    ->where('company_id', $receipt->company_id)
                    ->where('supplier_id', $receipt->supplier_id)
                    ->where('product_id', $item->product_id)
                    ->update([
                        'last_purchase_price' => $item->unit_cost,
                        'last_purchased_at' => $receipt->receipt_date,
                    ]);
            }

            $status = $receipt->items->sum(fn (GoodsReceiptItem $item) => $this->mills($item->rejected_quantity) + $this->mills($item->damaged_quantity)) > 0
                ? GoodsReceiptStatus::PartiallyAccepted->value
                : GoodsReceiptStatus::Received->value;

            $receipt->update([
                'status' => $status,
                'checked_by' => $user->id,
                'checked_at' => now(),
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            if ($receipt->purchaseOrder) {
                $this->orders->updateReceiptStatus($receipt->purchaseOrder);
            }

            $this->scores->snapshot($receipt->supplier, $user->id, 'Supplier score refreshed after goods receipt.');
            $this->auditLogger->record('purchase.goods_received', $receipt, 'Goods received and posted to stock');
            $this->domainEvents->dispatch(new PurchaseDomainEvent(
                key: 'purchase.goods_received',
                companyId: $receipt->company_id,
                actorId: $user->id,
                aggregateType: GoodsReceipt::class,
                aggregateId: $receipt->id,
                payload: [
                    'grn_number' => $receipt->grn_number,
                    'supplier_id' => $receipt->supplier_id,
                    'purchase_order_id' => $receipt->purchase_order_id,
                    'items_count' => $receipt->items->count(),
                ],
            ));

            return $receipt->refresh()->load(['supplier', 'warehouse', 'purchaseOrder.items', 'items.product']);
        });
    }

    private function recordBatch(GoodsReceipt $receipt, GoodsReceiptItem $item): ?InventoryBatch
    {
        $product = $item->product ?? Product::query()->findOrFail($item->product_id);
        if (! $product->track_batches || blank($item->batch_number)) {
            return null;
        }

        $query = InventoryBatch::query()
            ->where('company_id', $receipt->company_id)
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $receipt->warehouse_id)
            ->where('batch_number', $item->batch_number)
            ->lockForUpdate();
        $item->stock_location_id ? $query->where('stock_location_id', $item->stock_location_id) : $query->whereNull('stock_location_id');
        $batch = $query->first();
        if (! $batch) {
            $batch = InventoryBatch::create([
                'company_id' => $receipt->company_id,
                'product_id' => $item->product_id,
                'warehouse_id' => $receipt->warehouse_id,
                'stock_location_id' => $item->stock_location_id,
                'batch_number' => $item->batch_number,
                'manufactured_at' => $item->manufacture_date,
                'expires_at' => $item->expiry_date,
                'quantity_on_hand' => 0,
                'quantity_available' => 0,
                'unit_cost' => $item->unit_cost,
                'supplier_reference' => (string) $receipt->supplier_id,
                'receipt_reference' => $receipt->grn_number,
                'status' => 'active',
            ]);
        }
        $batch->increment('quantity_on_hand', $item->accepted_quantity);
        $batch->increment('quantity_available', $item->accepted_quantity);
        $item->update(['inventory_batch_id' => $batch->id]);

        return $batch->refresh();
    }

    private function mills(string|int|float|null $value): int
    {
        $value = trim((string) ($value ?? '0'));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction) ?: '';
        $mills = ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');
        if (isset($fraction[3]) && $fraction[3] >= '5') {
            $mills++;
        }

        return $negative ? -$mills : $mills;
    }

    private function quantityDecimal(int $mills): string
    {
        $negative = $mills < 0;
        $digits = str_pad((string) abs($mills), 4, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').substr($digits, 0, -3).'.'.substr($digits, -3);
    }
}
