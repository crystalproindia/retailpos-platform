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
                ->with(['items.product', 'items', 'matchExceptions'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $invoice->matchExceptions()->where('status', 'open')->delete();
            foreach ($invoice->items as $line) {
                $receiptItem = $line->goodsReceiptItem()->with('purchaseOrderItem')->first();
                if (! $receiptItem) {
                    $this->exception($invoice, $line->id, null, 'unreceived_item', 'This invoice line is not linked to an accepted goods receipt item.');
                    continue;
                }
                if ($line->product_id !== $receiptItem->product_id) {
                    $this->exception($invoice, $line->id, $receiptItem->id, 'product_mismatch', 'The invoice product does not match the received product.');
                }
                if ((float) $line->quantity > (float) $receiptItem->accepted_quantity + 0.0005) {
                    $this->exception($invoice, $line->id, $receiptItem->id, 'quantity_mismatch', 'The supplier invoiced more than the accepted quantity.', (float) $receiptItem->accepted_quantity, (float) $line->quantity);
                }
                $orderItem = $receiptItem->purchaseOrderItem;
                if ($orderItem && abs((float) $line->unit_price - (float) $orderItem->unit_price) > 0.005) {
                    $this->exception($invoice, $line->id, $receiptItem->id, 'price_mismatch', 'The invoiced unit price differs from the purchase order.', null, null, (float) $orderItem->unit_price, (float) $line->unit_price);
                }
                if ($orderItem && abs((float) $line->tax_rate - (float) $orderItem->tax_rate) > 0.005) {
                    $this->exception($invoice, $line->id, $receiptItem->id, 'tax_mismatch', 'The invoiced GST rate differs from the purchase order.', null, null, (float) $orderItem->tax_rate, (float) $line->tax_rate);
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

    private function exception(PurchaseInvoice $invoice, ?int $invoiceItemId, ?int $receiptItemId, string $type, string $details, ?float $expectedQuantity = null, ?float $actualQuantity = null, ?float $expectedAmount = null, ?float $actualAmount = null): void
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
}
