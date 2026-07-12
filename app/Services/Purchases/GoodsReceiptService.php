<?php

namespace App\Services\Purchases;

use App\Enums\Purchases\GoodsReceiptStatus;
use App\Events\Domain\Purchases\PurchaseDomainEvent;
use App\Models\Purchases\GoodsReceipt;
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
                    ->findOrFail((int) $data['purchase_order_id']);

            $receipt = GoodsReceipt::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'warehouse_id' => $order?->warehouse_id ?? $data['warehouse_id'],
                'supplier_id' => $order?->supplier_id ?? $data['supplier_id'],
                'purchase_order_id' => $order?->id,
                'grn_number' => $this->numbers->next($user->company_id, 'grn'),
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

                $acceptedQuantity = (float) ($item['accepted_quantity'] ?? $item['received_quantity']);
                $receivedQuantity = (float) $item['received_quantity'];
                $rejectedQuantity = (float) ($item['rejected_quantity'] ?? max(0, $receivedQuantity - $acceptedQuantity));

                $receipt->items()->create([
                    'purchase_order_item_id' => $orderItem?->id,
                    'product_id' => $orderItem?->product_id ?? $item['product_id'],
                    'stock_location_id' => $item['stock_location_id'] ?? null,
                    'ordered_quantity' => $orderItem?->ordered_quantity ?? $item['ordered_quantity'] ?? null,
                    'received_quantity' => $receivedQuantity,
                    'accepted_quantity' => $acceptedQuantity,
                    'rejected_quantity' => $rejectedQuantity,
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
        if ($receipt->status === GoodsReceiptStatus::Received || $receipt->status === GoodsReceiptStatus::Closed) {
            return $receipt;
        }

        return DB::transaction(function () use ($receipt, $user): GoodsReceipt {
            $receipt->load(['items.purchaseOrderItem', 'purchaseOrder.items', 'supplier']);

            foreach ($receipt->items as $item) {
                if ((float) $item->accepted_quantity <= 0) {
                    continue;
                }

                $this->stockService->recordPurchaseReceipt($user, [
                    'branch_id' => $receipt->branch_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'stock_location_id' => $item->stock_location_id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->accepted_quantity,
                    'unit_cost' => $item->unit_cost,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'reason' => 'Goods receipt '.$receipt->grn_number,
                    'notes' => $item->notes,
                ]);

                if ($item->purchaseOrderItem) {
                    $received = (float) $item->purchaseOrderItem->received_quantity + (float) $item->accepted_quantity;
                    $pending = max(0, (float) $item->purchaseOrderItem->ordered_quantity - $received);
                    $item->purchaseOrderItem->update([
                        'received_quantity' => $received,
                        'pending_quantity' => $pending,
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

            $status = $receipt->items->sum('rejected_quantity') > 0
                ? GoodsReceiptStatus::PartiallyAccepted->value
                : GoodsReceiptStatus::Received->value;

            $receipt->update([
                'status' => $status,
                'checked_by' => $user->id,
                'checked_at' => now(),
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
}
