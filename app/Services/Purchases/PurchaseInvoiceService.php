<?php

namespace App\Services\Purchases;

use App\Models\Purchases\GoodsReceiptItem;
use App\Models\Purchases\PurchaseApprovalLog;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurchaseNumberService $numbers,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($user, $data): PurchaseInvoice {
            if (! empty($data['idempotency_key'])) {
                $existing = PurchaseInvoice::query()
                    ->where('company_id', $user->company_id)
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $supplierId = (int) $data['supplier_id'];
            $lineTotals = $this->linesForInvoice($user, $supplierId, $data);
            $financialYear = $data['financial_year'] ?? $this->financialYear($data['supplier_invoice_date']);

            if (PurchaseInvoice::query()
                ->where('supplier_id', $supplierId)
                ->where('supplier_invoice_number', $data['supplier_invoice_number'])
                ->where('financial_year', $financialYear)
                ->exists()) {
                throw ValidationException::withMessages([
                    'supplier_invoice_number' => 'This supplier invoice number already exists for this financial year.',
                ]);
            }

            $invoice = PurchaseInvoice::create([
                'company_id' => $user->company_id,
                'branch_id' => $data['branch_id'] ?? $user->branch_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'supplier_id' => $supplierId,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'invoice_number' => $this->numbers->next($user->company_id, 'invoice'),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'supplier_invoice_number' => $data['supplier_invoice_number'],
                'supplier_invoice_date' => $data['supplier_invoice_date'],
                'financial_year' => $financialYear,
                'status' => 'draft',
                'currency' => $data['currency'] ?? 'INR',
                'place_of_supply_state_code' => $data['place_of_supply_state_code'] ?? null,
                'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
                'subtotal' => $this->decimal($lineTotals['subtotal']),
                'taxable_total' => $this->decimal($lineTotals['subtotal']),
                'input_cgst' => $this->decimal($lineTotals['cgst']),
                'input_sgst' => $this->decimal($lineTotals['sgst']),
                'input_igst' => $this->decimal($lineTotals['igst']),
                'input_cess' => $this->decimal($lineTotals['cess']),
                'grand_total' => $this->decimal($lineTotals['total']),
                'outstanding_total' => $this->decimal($lineTotals['total']),
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($lineTotals['items'] as $line) {
                $invoice->items()->create($line);
            }

            $this->audit->record('purchase.invoice.created', $invoice, 'Purchase invoice created from GRN lines.');

            return $invoice->load(['supplier', 'items']);
        });
    }

    public function verify(PurchaseInvoice $invoice, User $user): PurchaseInvoice
    {
        return $this->transition($invoice, $user, 'pending_verification', 'verified');
    }

    public function approve(PurchaseInvoice $invoice, User $user): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $user): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (in_array($invoice->status, ['approved', 'partially_paid', 'paid'], true)) {
                return $invoice;
            }
            if (! in_array($invoice->status, ['draft', 'pending_verification'], true)) {
                throw ValidationException::withMessages(['status' => 'Only a draft or verified invoice can be approved.']);
            }

            $from = $invoice->status;
            $invoice->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            $this->log($invoice, $user, 'approved', $from, 'approved');
            $this->audit->record('purchase.invoice.approved', $invoice, 'Purchase invoice approved and included in payables.');

            return $invoice->refresh();
        });
    }

    public function cancel(PurchaseInvoice $invoice, User $user, string $reason): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $user, $reason): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ((int) round((float) $invoice->paid_total * 100) > 0) {
                throw ValidationException::withMessages(['status' => 'Reverse payment allocations before cancelling an invoice.']);
            }
            if ($invoice->status === 'cancelled') {
                return $invoice;
            }
            $from = $invoice->status;
            $invoice->update([
                'status' => 'cancelled',
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $this->log($invoice, $user, 'cancelled', $from, 'cancelled', $reason);
            $this->audit->record('purchase.invoice.cancelled', $invoice, 'Purchase invoice cancelled.');

            return $invoice->refresh();
        });
    }

    /** @param array<string, mixed> $data @return array{subtotal:int,cgst:int,sgst:int,igst:int,cess:int,total:int,items:array<int,array<string,mixed>>} */
    private function linesForInvoice(User $user, int $supplierId, array $data): array
    {
        $totals = ['subtotal' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'cess' => 0, 'total' => 0, 'items' => []];
        $intraState = ! empty($data['place_of_supply_state_code'])
            && $data['place_of_supply_state_code'] === ($data['supplier_state_code'] ?? null);

        foreach ($data['items'] as $item) {
            $quantityMills = $this->mills($item['quantity']);
            $unitPrice = $this->paise($item['unit_price']);
            if ($quantityMills <= 0 || $unitPrice < 0) {
                throw ValidationException::withMessages(['items' => 'Invoice quantities and unit prices must be valid.']);
            }

            $receiptItem = null;
            if (! empty($item['goods_receipt_item_id'])) {
                $receiptItem = GoodsReceiptItem::query()
                    ->with('goodsReceipt')
                    ->whereHas('goodsReceipt', fn ($query) => $query->where('company_id', $user->company_id)->where('supplier_id', $supplierId))
                    ->lockForUpdate()
                    ->findOrFail($item['goods_receipt_item_id']);
                $alreadyInvoiced = (int) PurchaseInvoice::query()
                    ->whereIn('status', ['draft', 'pending_verification', 'approved', 'partially_paid', 'paid', 'overdue', 'disputed'])
                    ->whereHas('items', fn ($query) => $query->where('goods_receipt_item_id', $receiptItem->id))
                    ->lockForUpdate()
                    ->get()
                    ->flatMap->items
                    ->where('goods_receipt_item_id', $receiptItem->id)
                    ->sum(fn ($line) => $this->mills($line->quantity));
                $remaining = $this->mills($receiptItem->accepted_quantity) - $alreadyInvoiced;
                if ($quantityMills > $remaining && ! $user->can('purchase-invoices.override-quantity')) {
                    throw ValidationException::withMessages(['items' => 'A GRN line cannot be invoiced above its accepted, uninvoiced quantity.']);
                }
            }

            $taxable = intdiv(($quantityMills * $unitPrice) + 500, 1000);
            $taxRateBasisPoints = (int) round(((float) ($item['tax_rate'] ?? 0)) * 100);
            $tax = intdiv(($taxable * $taxRateBasisPoints) + 5000, 10000);
            $cgst = $intraState ? intdiv($tax, 2) : 0;
            $sgst = $intraState ? $tax - $cgst : 0;
            $igst = $intraState ? 0 : $tax;

            $totals['subtotal'] += $taxable;
            $totals['cgst'] += $cgst;
            $totals['sgst'] += $sgst;
            $totals['igst'] += $igst;
            $totals['total'] += $taxable + $tax;
            $totals['items'][] = [
                'goods_receipt_item_id' => $receiptItem?->id,
                'product_id' => $receiptItem?->product_id ?? ($item['product_id'] ?? null),
                'name_snapshot' => $item['name_snapshot'] ?? $receiptItem?->product?->name ?? 'Purchase line',
                'hsn_sac' => $item['hsn_sac'] ?? null,
                'quantity' => $this->quantityDecimal($quantityMills),
                'unit_price' => $this->decimal($unitPrice),
                'taxable_value' => $this->decimal($taxable),
                'tax_rate' => number_format($taxRateBasisPoints / 100, 3, '.', ''),
                'cgst_amount' => $this->decimal($cgst),
                'sgst_amount' => $this->decimal($sgst),
                'igst_amount' => $this->decimal($igst),
                'cess_amount' => '0.00',
                'line_total' => $this->decimal($taxable + $tax),
            ];
        }

        return $totals;
    }

    private function transition(PurchaseInvoice $invoice, User $user, string $status, string $action): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $user, $status, $action): PurchaseInvoice {
            $invoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== 'draft') {
                return $invoice;
            }
            $invoice->update(['status' => $status, 'verified_by' => $user->id, 'verified_at' => now()]);
            $this->log($invoice, $user, $action, 'draft', $status);
            $this->audit->record('purchase.invoice.verified', $invoice, 'Purchase invoice verified.');
            return $invoice->refresh();
        });
    }

    private function log(PurchaseInvoice $invoice, User $user, string $action, ?string $from, string $to, ?string $comments = null): void
    {
        PurchaseApprovalLog::create(['company_id' => $invoice->company_id, 'approvable_type' => PurchaseInvoice::class, 'approvable_id' => $invoice->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'user_id' => $user->id, 'comments' => $comments]);
    }

    private function financialYear(string $date): string
    {
        $date = \Carbon\Carbon::parse($date);
        $start = $date->month < 4 ? $date->year - 1 : $date->year;
        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }

    private function paise(string|int|float $value): int { return (int) round(((float) $value) * 100); }
    private function mills(string|int|float $value): int { return (int) round(((float) $value) * 1000); }
    private function decimal(int $paise): string { return number_format($paise / 100, 2, '.', ''); }
    private function quantityDecimal(int $mills): string { return number_format($mills / 1000, 3, '.', ''); }
}
