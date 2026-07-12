<?php

namespace App\Services\Purchases;

use App\Enums\Purchases\PurchaseOrderStatus;
use App\Enums\Purchases\PurchaseRequestStatus;
use App\Events\Domain\Purchases\PurchaseDomainEvent;
use App\Models\Inventory\Product;
use App\Models\Purchases\PurchaseApprovalLog;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseRequest;
use App\Models\Purchases\SupplierProduct;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseNumberService $numbers,
        private readonly PurchaseRequestService $requests,
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($user, $data): PurchaseOrder {
            $order = PurchaseOrder::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'po_number' => $this->numbers->next($user->company_id, 'po'),
                'status' => $data['status'] ?? PurchaseOrderStatus::Draft->value,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency' => $data['currency'] ?? 'INR',
                'shipping_total' => $data['shipping_total'] ?? 0,
                'payment_terms' => $data['payment_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $item) {
                $this->createItem($order, $item);
            }

            $this->recalculate($order);
            $this->auditLogger->record('purchase.order.created', $order, 'Purchase order created');
            $this->dispatch('purchase.order.created', $order, $user, ['po_number' => $order->po_number, 'grand_total' => $order->grand_total]);

            return $order->refresh()->load(['supplier', 'warehouse', 'items.product']);
        });
    }

    public function createFromRequest(PurchaseRequest $request, User $user, ?int $supplierId = null): PurchaseOrder
    {
        abort_unless($request->status === PurchaseRequestStatus::Approved, 422, 'Only approved purchase requests can be converted to purchase orders.');

        $request->load('items.product');
        $supplierId ??= (int) $request->items->firstWhere('supplier_id', '!=', null)?->supplier_id;
        abort_if(! $supplierId, 422, 'Select a supplier before converting to a purchase order.');

        $items = $request->items->map(function ($item) use ($supplierId): array {
            $supplierProduct = SupplierProduct::query()
                ->where('supplier_id', $supplierId)
                ->where('product_id', $item->product_id)
                ->first();

            return [
                'product_id' => $item->product_id,
                'supplier_product_id' => $supplierProduct?->id,
                'ordered_quantity' => $item->approved_quantity ?? $item->requested_quantity,
                'unit_price' => $supplierProduct?->purchase_price ?? $item->estimated_price ?? $item->product->cost_price ?? 0,
                'tax_rate' => $supplierProduct?->taxRate?->rate ?? 0,
                'discount_amount' => 0,
                'notes' => $item->notes,
            ];
        })->all();

        $order = $this->create($user, [
            'warehouse_id' => $request->warehouse_id,
            'supplier_id' => $supplierId,
            'purchase_request_id' => $request->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => $request->expected_by?->toDateString(),
            'items' => $items,
        ]);

        $this->requests->markConverted($request, $user, $order->id);

        return $order;
    }

    public function submit(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return $this->transition($order, $user, PurchaseOrderStatus::PendingApproval, 'submitted', 'purchase.order.submitted', 'Purchase order submitted');
    }

    public function approve(PurchaseOrder $order, User $user): PurchaseOrder
    {
        $from = $order->status->value;
        $order->update([
            'status' => PurchaseOrderStatus::Approved->value,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this->approvalLog($order, $user, 'approved', $from, PurchaseOrderStatus::Approved->value);
        $this->auditLogger->record('purchase.order.approved', $order, 'Purchase order approved');
        $this->dispatch('purchase.order.approved', $order, $user, ['po_number' => $order->po_number]);

        return $order->refresh();
    }

    public function markSent(PurchaseOrder $order, User $user): PurchaseOrder
    {
        $from = $order->status->value;
        $order->update([
            'status' => PurchaseOrderStatus::Sent->value,
            'sent_at' => now(),
        ]);

        $this->approvalLog($order, $user, 'sent', $from, PurchaseOrderStatus::Sent->value);
        $this->auditLogger->record('purchase.order.sent', $order, 'Purchase order marked sent');
        $this->dispatch('purchase.order.sent', $order, $user, ['po_number' => $order->po_number]);

        return $order->refresh();
    }

    public function cancel(PurchaseOrder $order, User $user): PurchaseOrder
    {
        $from = $order->status->value;
        $order->update([
            'status' => PurchaseOrderStatus::Cancelled->value,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
        ]);

        $this->approvalLog($order, $user, 'cancelled', $from, PurchaseOrderStatus::Cancelled->value);
        $this->auditLogger->record('purchase.order.cancelled', $order, 'Purchase order cancelled');
        $this->dispatch('purchase.order.cancelled', $order, $user, ['po_number' => $order->po_number]);

        return $order->refresh();
    }

    public function updateReceiptStatus(PurchaseOrder $order): PurchaseOrder
    {
        $order->load('items');
        $totalPending = (float) $order->items->sum('pending_quantity');
        $totalOrdered = (float) $order->items->sum('ordered_quantity');

        $status = $totalPending <= 0
            ? PurchaseOrderStatus::Received->value
            : ($totalPending < $totalOrdered ? PurchaseOrderStatus::PartiallyReceived->value : $order->status->value);

        $order->update(['status' => $status]);

        return $order->refresh();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createItem(PurchaseOrder $order, array $item): void
    {
        $product = Product::query()->where('company_id', $order->company_id)->findOrFail($item['product_id']);
        $quantity = (float) $item['ordered_quantity'];
        $unitPrice = (float) $item['unit_price'];
        $discount = (float) ($item['discount_amount'] ?? 0);
        $taxRate = (float) ($item['tax_rate'] ?? 0);
        $taxAmount = (($quantity * $unitPrice) - $discount) * ($taxRate / 100);
        $lineTotal = (($quantity * $unitPrice) - $discount) + $taxAmount;

        $order->items()->create([
            'product_id' => $product->id,
            'supplier_product_id' => $item['supplier_product_id'] ?? null,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'ordered_quantity' => $quantity,
            'received_quantity' => 0,
            'pending_quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'notes' => $item['notes'] ?? null,
        ]);
    }

    private function recalculate(PurchaseOrder $order): void
    {
        $order->load('items');
        $subtotal = (float) $order->items->sum(fn ($item) => ((float) $item->ordered_quantity * (float) $item->unit_price));
        $discount = (float) $order->items->sum('discount_amount');
        $tax = (float) $order->items->sum('tax_amount');
        $shipping = (float) $order->shipping_total;

        $order->update([
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'grand_total' => $subtotal - $discount + $tax + $shipping,
        ]);
    }

    private function transition(PurchaseOrder $order, User $user, PurchaseOrderStatus $status, string $action, string $eventKey, string $description): PurchaseOrder
    {
        $from = $order->status->value;
        $order->update(['status' => $status->value]);
        $this->approvalLog($order, $user, $action, $from, $status->value);
        $this->auditLogger->record($eventKey, $order, $description);
        $this->dispatch($eventKey, $order, $user, ['po_number' => $order->po_number]);

        return $order->refresh();
    }

    private function approvalLog(PurchaseOrder $order, User $user, string $action, ?string $from, string $to): void
    {
        PurchaseApprovalLog::create([
            'company_id' => $order->company_id,
            'approvable_type' => PurchaseOrder::class,
            'approvable_id' => $order->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $eventKey, PurchaseOrder $order, User $user, array $payload): void
    {
        $this->domainEvents->dispatch(new PurchaseDomainEvent(
            key: $eventKey,
            companyId: $order->company_id,
            actorId: $user->id,
            aggregateType: PurchaseOrder::class,
            aggregateId: $order->id,
            payload: $payload,
        ));
    }
}
