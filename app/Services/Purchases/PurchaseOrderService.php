<?php

namespace App\Services\Purchases;

use App\Enums\Purchases\PurchaseOrderStatus;
use App\Enums\Purchases\PurchaseRequestStatus;
use App\Events\Domain\Purchases\PurchaseDomainEvent;
use App\Models\Inventory\Product;
use App\Models\Purchases\PurchaseApprovalLog;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseRequest;
use App\Models\Purchases\Supplier;
use App\Models\Purchases\SupplierProduct;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use App\Services\Inventory\InventoryLocationAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseNumberService $numbers,
        private readonly PurchaseRequestService $requests,
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
        private readonly InventoryLocationAccessService $locations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($user, $data): PurchaseOrder {
            $warehouse = $this->locations->authorize($user, (int) $data['warehouse_id']);
            $request = ! empty($data['purchase_request_id'])
                ? PurchaseRequest::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail((int) $data['purchase_request_id'])
                : null;
            if ($request && $request->warehouse_id !== $warehouse->id) {
                throw ValidationException::withMessages(['warehouse_id' => 'The purchase order must use the approved request destination warehouse.']);
            }
            $order = PurchaseOrder::create([
                'company_id' => $user->company_id,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $data['supplier_id'],
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'po_number' => $this->numbers->next($user->company_id, 'po'),
                'status' => $data['status'] ?? PurchaseOrderStatus::Draft->value,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency' => $data['currency'] ?? 'INR',
                'shipping_total' => $this->decimal($this->paise($data['shipping_total'] ?? 0)),
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
        return DB::transaction(function () use ($request, $user, $supplierId): PurchaseOrder {
            $request = PurchaseRequest::query()->with('items.product')->lockForUpdate()->findOrFail($request->id);
            if (! in_array($request->status, [PurchaseRequestStatus::Approved, PurchaseRequestStatus::PartiallyApproved], true)) {
                throw ValidationException::withMessages(['status' => 'Only approved purchase requests can be converted to purchase orders.']);
            }
            $supplierId ??= (int) $request->items->firstWhere('supplier_id', '!=', null)?->supplier_id;
            if (! $supplierId || ! Supplier::query()->where('company_id', $user->company_id)->whereKey($supplierId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['supplier_id' => 'Select an active supplier in your company before converting to a purchase order.']);
            }

            $items = $request->items->map(function ($item) use ($supplierId): ?array {
                $remaining = max(0, $this->mills($item->approved_quantity) - $this->mills($item->converted_quantity));
                if ($remaining === 0) {
                    return null;
                }
            $supplierProduct = SupplierProduct::query()
                ->where('supplier_id', $supplierId)
                ->where('product_id', $item->product_id)
                ->first();

            return [
                'product_id' => $item->product_id,
                'supplier_product_id' => $supplierProduct?->id,
                'ordered_quantity' => $this->quantityDecimal($remaining),
                'unit_price' => $supplierProduct?->purchase_price ?? $item->estimated_price ?? $item->product->cost_price ?? 0,
                'tax_rate' => $supplierProduct?->taxRate?->rate ?? 0,
                'discount_amount' => 0,
                'notes' => $item->notes,
            ];
            })->filter()->values();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['request' => 'All approved quantities from this request have already been converted to purchase orders.']);
            }

            $order = $this->create($user, [
            'warehouse_id' => $request->warehouse_id,
            'supplier_id' => $supplierId,
            'purchase_request_id' => $request->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => $request->expected_by?->toDateString(),
            'items' => $items->all(),
            ]);

            foreach ($request->items as $item) {
                $converted = $items->firstWhere('product_id', $item->product_id)['ordered_quantity'] ?? 0;
                if ($this->mills($converted) > 0) {
                    $item->increment('converted_quantity', $converted);
                }
            }
            $request->refresh()->load('items');
            $fullyConverted = $request->items->every(fn ($item) => $this->mills($item->converted_quantity) >= $this->mills($item->approved_quantity));
            if ($fullyConverted) {
                $this->requests->markConverted($request, $user, $order->id);
            }

            return $order;
        });
    }

    public function submit(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return $this->transition($order, $user, [PurchaseOrderStatus::Draft], PurchaseOrderStatus::PendingApproval, 'submitted', 'purchase.order.submitted', 'Purchase order submitted');
    }

    public function approve(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $user): PurchaseOrder {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === PurchaseOrderStatus::Approved) {
                return $order;
            }
            $this->ensureStatus($order, [PurchaseOrderStatus::PendingApproval], 'Only a submitted purchase order can be approved.');
            $from = $order->status->value;
            $order->update(['status' => PurchaseOrderStatus::Approved->value, 'approved_by' => $user->id, 'approved_at' => now()]);
            $this->approvalLog($order, $user, 'approved', $from, PurchaseOrderStatus::Approved->value);
            $this->auditLogger->record('purchase.order.approved', $order, 'Purchase order approved');
            $this->dispatch('purchase.order.approved', $order, $user, ['po_number' => $order->po_number]);

            return $order->refresh();
        });
    }

    public function markSent(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $user): PurchaseOrder {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === PurchaseOrderStatus::Sent) {
                return $order;
            }
            $this->ensureStatus($order, [PurchaseOrderStatus::Approved], 'Only an approved purchase order can be sent.');
            $from = $order->status->value;
            $order->update(['status' => PurchaseOrderStatus::Sent->value, 'sent_at' => now()]);
            $this->approvalLog($order, $user, 'sent', $from, PurchaseOrderStatus::Sent->value);
            $this->auditLogger->record('purchase.order.sent', $order, 'Purchase order marked sent');
            $this->dispatch('purchase.order.sent', $order, $user, ['po_number' => $order->po_number]);

            return $order->refresh();
        });
    }

    public function markSupplierConfirmed(PurchaseOrder $order, User $user, ?string $reference = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $user, $reference): PurchaseOrder {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === PurchaseOrderStatus::SupplierConfirmed) {
                return $order;
            }
            $this->ensureStatus($order, [PurchaseOrderStatus::Sent], 'Only a sent purchase order can be supplier confirmed.');
            $from = $order->status->value;
            $order->update(['status' => PurchaseOrderStatus::SupplierConfirmed->value, 'supplier_confirmed_at' => now(), 'supplier_confirmation_reference' => $reference]);
            $this->approvalLog($order, $user, 'supplier_confirmed', $from, PurchaseOrderStatus::SupplierConfirmed->value);
            $this->auditLogger->record('purchase.order.supplier_confirmed', $order, 'Supplier confirmed purchase order');
            $this->dispatch('purchase.order.supplier_confirmed', $order, $user, ['po_number' => $order->po_number]);

            return $order->refresh();
        });
    }

    public function cancel(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $user): PurchaseOrder {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === PurchaseOrderStatus::Cancelled) {
                return $order;
            }
            $this->ensureStatus($order, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::PendingApproval, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent, PurchaseOrderStatus::SupplierConfirmed], 'Only an unreceived purchase order can be cancelled.');
            $from = $order->status->value;
            $order->update(['status' => PurchaseOrderStatus::Cancelled->value, 'cancelled_by' => $user->id, 'cancelled_at' => now()]);
            $this->approvalLog($order, $user, 'cancelled', $from, PurchaseOrderStatus::Cancelled->value);
            $this->auditLogger->record('purchase.order.cancelled', $order, 'Purchase order cancelled');
            $this->dispatch('purchase.order.cancelled', $order, $user, ['po_number' => $order->po_number]);

            return $order->refresh();
        });
    }

    public function updateReceiptStatus(PurchaseOrder $order): PurchaseOrder
    {
        $order->load('items');
        $totalPending = $order->items->sum(fn ($item) => $this->mills($item->pending_quantity));
        $totalOrdered = $order->items->sum(fn ($item) => $this->mills($item->ordered_quantity));

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
        $quantity = $this->mills($item['ordered_quantity']);
        $unitPrice = $this->paise($item['unit_price']);
        $discount = $this->paise($item['discount_amount'] ?? 0);
        $taxRate = $this->mills($item['tax_rate'] ?? 0);
        $subtotal = intdiv(($quantity * $unitPrice) + 500, 1000);
        $taxable = max(0, $subtotal - $discount);
        $taxAmount = intdiv(($taxable * $taxRate) + 50_000, 100_000);
        $lineTotal = $taxable + $taxAmount;

        $order->items()->create([
            'product_id' => $product->id,
            'supplier_product_id' => $item['supplier_product_id'] ?? null,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'ordered_quantity' => $this->quantityDecimal($quantity),
            'received_quantity' => 0,
            'pending_quantity' => $this->quantityDecimal($quantity),
            'unit_price' => $this->decimal($unitPrice),
            'discount_amount' => $this->decimal($discount),
            'tax_rate' => $this->quantityDecimal($taxRate),
            'tax_amount' => $this->decimal($taxAmount),
            'line_total' => $this->decimal($lineTotal),
            'notes' => $item['notes'] ?? null,
        ]);
    }

    private function recalculate(PurchaseOrder $order): void
    {
        $order->load('items');
        $subtotal = $order->items->sum(fn ($item) => intdiv(($this->mills($item->ordered_quantity) * $this->paise($item->unit_price)) + 500, 1000));
        $discount = $order->items->sum(fn ($item) => $this->paise($item->discount_amount));
        $tax = $order->items->sum(fn ($item) => $this->paise($item->tax_amount));
        $shipping = $this->paise($order->shipping_total);

        $order->update([
            'subtotal' => $this->decimal($subtotal),
            'discount_total' => $this->decimal($discount),
            'tax_total' => $this->decimal($tax),
            'grand_total' => $this->decimal($subtotal - $discount + $tax + $shipping),
        ]);
    }

    /** @param array<int, PurchaseOrderStatus> $allowedStatuses */
    private function transition(PurchaseOrder $order, User $user, array $allowedStatuses, PurchaseOrderStatus $status, string $action, string $eventKey, string $description): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $user, $allowedStatuses, $status, $action, $eventKey, $description): PurchaseOrder {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === $status) {
                return $order;
            }
            $this->ensureStatus($order, $allowedStatuses, 'This purchase order cannot be moved to the requested status.');
            $from = $order->status->value;
            $order->update(['status' => $status->value]);
            $this->approvalLog($order, $user, $action, $from, $status->value);
            $this->auditLogger->record($eventKey, $order, $description);
            $this->dispatch($eventKey, $order, $user, ['po_number' => $order->po_number]);

            return $order->refresh();
        });
    }

    /** @param array<int, PurchaseOrderStatus> $allowedStatuses */
    private function ensureStatus(PurchaseOrder $order, array $allowedStatuses, string $message): void
    {
        if (! in_array($order->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
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

    private function paise(string|int|float|null $value): int { return $this->scaledInteger($value, 2); }
    private function mills(string|int|float|null $value): int { return $this->scaledInteger($value, 3); }
    private function decimal(int $value): string { return $this->formatScaled($value, 2); }
    private function quantityDecimal(int $value): string { return $this->formatScaled($value, 3); }

    private function scaledInteger(string|int|float|null $value, int $scale): int
    {
        $value = trim((string) ($value ?? '0'));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction) ?: '';
        $scaled = ((int) $whole * (10 ** $scale)) + (int) str_pad(substr($fraction, 0, $scale), $scale, '0');
        if (isset($fraction[$scale]) && $fraction[$scale] >= '5') {
            $scaled++;
        }

        return $negative ? -$scaled : $scaled;
    }

    private function formatScaled(int $value, int $scale): string
    {
        $negative = $value < 0;
        $digits = str_pad((string) abs($value), $scale + 1, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').substr($digits, 0, -$scale).'.'.substr($digits, -$scale);
    }
}
