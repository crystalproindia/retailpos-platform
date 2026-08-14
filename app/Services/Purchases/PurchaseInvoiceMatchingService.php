<?php

namespace App\Services\Purchases;

use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseInvoiceMatchException;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceMatchingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function reconcile(PurchaseInvoice $invoice, ?User $actor = null): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $actor): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()
                ->with(['items.product', 'items.goodsReceiptItem.purchaseOrderItem', 'matchExceptions'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $invoice->matchExceptions()->where('status', 'open')->delete();
            foreach ($invoice->items as $line) {
                $receiptItem = $line->goodsReceiptItem;
                if (! $receiptItem) {
                    $this->exception($invoice, $line->id, null, 'unreceived_item', 'This invoice line is not linked to an accepted goods receipt item.');
                    continue;
                }
                if ($line->product_id !== $receiptItem->product_id) {
                    $this->exception($invoice, $line->id, $receiptItem->id, 'product_mismatch', 'The invoice product does not match the received product.');
                }
                if ($this->compareDecimal($line->quantity, $receiptItem->accepted_quantity, 3) > 0) {
                    $this->exception($invoice, $line->id, $receiptItem->id, 'quantity_mismatch', 'The supplier invoiced more than the accepted quantity.', $receiptItem->accepted_quantity, $line->quantity);
                }
                $orderItem = $receiptItem->purchaseOrderItem;
                if ($orderItem && $this->compareDecimal($line->unit_price, $orderItem->unit_price, 2) !== 0) {
                    $this->exception($invoice, $line->id, $receiptItem->id, 'price_mismatch', 'The invoiced unit price differs from the purchase order.', null, null, $orderItem->unit_price, $line->unit_price);
                }
                if ($orderItem && $this->compareDecimal($line->tax_rate, $orderItem->tax_rate, 3) !== 0) {
                    $this->exception($invoice, $line->id, $receiptItem->id, 'tax_mismatch', 'The invoiced GST rate differs from the purchase order.', null, null, $orderItem->tax_rate, $line->tax_rate);
                }
            }
            $hasExceptions = $invoice->matchExceptions()->where('status', 'open')->exists();
            $invoice->update(['match_status' => $hasExceptions ? 'exceptions' : 'matched', 'matched_at' => now()]);
            $this->audit->record('purchase.invoice.matched', $invoice, $hasExceptions ? 'Purchase invoice matched with exceptions.' : 'Purchase invoice three-way match completed.');

            return $invoice->refresh()->load('matchExceptions');
        });
    }

    public function resolve(PurchaseInvoiceMatchException $exception, User $user, string $notes): PurchaseInvoiceMatchException
    {
        if ($exception->company_id !== $user->company_id) {
            abort(404);
        }
        if (blank($notes)) {
            throw ValidationException::withMessages(['resolution_notes' => 'Record why this purchasing exception is being resolved.']);
        }
        $exception->update(['status' => 'resolved', 'resolved_by' => $user->id, 'resolved_at' => now(), 'resolution_notes' => $notes]);
        $invoice = $exception->invoice;
        if (! $invoice->matchExceptions()->where('status', 'open')->exists()) {
            $invoice->update(['match_status' => 'resolved', 'match_reviewed_by' => $user->id, 'match_reviewed_at' => now()]);
        }
        $this->audit->record('purchase.invoice.match_exception.resolved', $exception, 'Purchase invoice match exception resolved.');

        return $exception->refresh();
    }

    private function exception(PurchaseInvoice $invoice, ?int $invoiceItemId, ?int $receiptItemId, string $type, string $details, ?string $expectedQuantity = null, ?string $actualQuantity = null, ?string $expectedAmount = null, ?string $actualAmount = null): void
    {
        PurchaseInvoiceMatchException::create([
            'company_id' => $invoice->company_id,
            'purchase_invoice_id' => $invoice->id,
            'purchase_order_id' => $invoice->purchase_order_id,
            'goods_receipt_item_id' => $receiptItemId,
            'purchase_invoice_item_id' => $invoiceItemId,
            'type' => $type,
            'expected_quantity' => $expectedQuantity,
            'actual_quantity' => $actualQuantity,
            'expected_amount' => $expectedAmount,
            'actual_amount' => $actualAmount,
            'details' => $details,
        ]);
    }

    private function compareDecimal(string|int|float|null $left, string|int|float|null $right, int $scale): int
    {
        $left = $this->decimalParts($left, $scale);
        $right = $this->decimalParts($right, $scale);

        if ($left['negative'] !== $right['negative']) {
            return $left['negative'] ? -1 : 1;
        }

        $comparison = strlen($left['value']) <=> strlen($right['value']) ?: $left['value'] <=> $right['value'];

        return $left['negative'] ? -$comparison : $comparison;
    }

    /** @return array{negative: bool, value: string} */
    private function decimalParts(string|int|float|null $value, int $scale): array
    {
        $value = trim((string) ($value ?? '0'));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $normalized = (ltrim($whole, '0') ?: '0').str_pad(substr($fraction, 0, $scale), $scale, '0');
        $normalized = ltrim($normalized, '0') ?: '0';

        return ['negative' => $negative && $normalized !== '0', 'value' => $normalized];
    }
}
