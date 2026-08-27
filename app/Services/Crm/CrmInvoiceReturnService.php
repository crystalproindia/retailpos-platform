<?php

namespace App\Services\Crm;

use App\Enums\Crm\InvoiceStatus;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceItem;
use App\Models\Crm\CrmInvoiceReturn;
use App\Models\Crm\CrmInvoiceReturnItem;
use App\Models\Crm\CrmReturnSequence;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Outlets\OutletAccessService;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmInvoiceReturnService
{
    public function __construct(
        private readonly OutletAccessService $outlets,
        private readonly AuditLogger $audit,
        private readonly InvoiceService $invoices,
    ) {}

    public function invoiceForReturn(User $user, int $invoiceId, bool $lock = false): CrmInvoice
    {
        $query = CrmInvoice::query()->where('company_id', $user->company_id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $invoice = $query->findOrFail($invoiceId);
        $this->assertAccess($user, $invoice);
        $this->assertEligible($invoice);

        return $invoice->load(['items.returnItems.crmInvoiceReturn', 'returns.items', 'customer', 'company', 'branch']);
    }

    /** @return array<int, array<string, mixed>> */
    public function returnableLines(CrmInvoice $invoice): array
    {
        $invoice->loadMissing('items.returnItems.crmInvoiceReturn');

        return $invoice->items->map(function (CrmInvoiceItem $item): array {
            $original = $this->milli((string) $item->quantity);
            $returned = $item->returnItems
                ->filter(fn (CrmInvoiceReturnItem $returnItem) => $returnItem->crmInvoiceReturn?->status === CrmInvoiceReturn::STATUS_FINALIZED)
                ->sum(fn (CrmInvoiceReturnItem $returnItem): int => $this->milli((string) $returnItem->return_quantity));

            return [
                'item' => $item,
                'original_quantity' => $this->quantity($original),
                'returned_quantity' => $this->quantity($returned),
                'remaining_quantity' => $this->quantity(max(0, $original - $returned)),
                'can_restock' => $item->product_id && StockMovement::query()
                    ->where('company_id', $item->invoice->company_id)
                    ->where('reference_type', CrmInvoice::class)->where('reference_id', $item->invoice_id)
                    ->where('product_id', $item->product_id)->where('movement_type', 'sale')->exists(),
            ];
        })->all();
    }

    /** @param array<string, mixed> $data */
    public function finalize(User $user, int $invoiceId, array $data): CrmInvoiceReturn
    {
        return DB::transaction(function () use ($user, $invoiceId, $data): CrmInvoiceReturn {
            $idempotencyKey = (string) ($data['idempotency_key'] ?? '');
            $existing = CrmInvoiceReturn::query()->where('company_id', $user->company_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ((int) $existing->invoice_id !== $invoiceId) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to another invoice.']);
                }

                return $existing->load(['items', 'invoice', 'creator']);
            }

            $invoice = $this->invoiceForReturn($user, $invoiceId, true);
            $existing = CrmInvoiceReturn::query()->where('company_id', $user->company_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ((int) $existing->invoice_id !== $invoiceId) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to another invoice.']);
                }

                return $existing->load(['items', 'invoice', 'creator']);
            }
            $items = CrmInvoiceItem::query()->where('invoice_id', $invoice->id)->lockForUpdate()->get()->keyBy('id');
            $calculation = $this->calculate($invoice, $items, $data['items'] ?? []);
            if (! $calculation['items']) {
                throw ValidationException::withMessages(['items' => 'Choose at least one remaining quantity to return.']);
            }

            $numbers = $this->nextNumber($invoice, now());
            $balanceBefore = $this->minor((string) $invoice->balance_due);
            $credit = $calculation['credit_total'];
            $applied = min($balanceBefore, $credit);
            $companyAddress = collect([$invoice->company->address, $invoice->company->city, $invoice->company->state, $invoice->company->postal_code, $invoice->company->country])->filter()->join(', ');
            $return = CrmInvoiceReturn::create([
                'company_id' => $invoice->company_id, 'branch_id' => $invoice->branch_id, 'invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id,
                'credit_note_number' => $numbers['number'], 'financial_year' => $numbers['financial_year'], 'issue_date' => now()->toDateString(),
                'status' => CrmInvoiceReturn::STATUS_FINALIZED, 'currency' => $invoice->currency,
                'gross_total' => $this->decimal($calculation['gross']), 'discount_total' => $this->decimal($calculation['discount']),
                'taxable_total' => $this->decimal($calculation['taxable']), 'tax_total' => $this->decimal($calculation['tax']),
                'cgst_total' => $this->decimal($calculation['cgst']), 'sgst_total' => $this->decimal($calculation['sgst']),
                'igst_total' => $this->decimal($calculation['igst']), 'cess_total' => $this->decimal($calculation['cess']),
                'credit_total' => $this->decimal($credit), 'receivable_credit_applied' => $this->decimal($applied),
                'customer_credit_due' => $this->decimal($credit - $applied), 'known_cogs_reversal' => $this->decimal($calculation['known_cogs']),
                'known_profit_reversal' => $this->decimal($calculation['known_profit']), 'unavailable_cost_item_count' => $calculation['unavailable_cost_items'],
                'reason_code' => $data['reason_code'], 'reason_note' => $data['reason_note'] ?? null, 'idempotency_key' => $idempotencyKey,
                'company_name_snapshot' => $invoice->company->legal_name ?: $invoice->company->name, 'company_address_snapshot' => $companyAddress ?: null,
                'company_tax_number_snapshot' => $invoice->supplier_gstin_snapshot ?: $invoice->company->tax_id,
                'customer_name_snapshot' => $invoice->billing_name, 'customer_company_snapshot' => $invoice->billing_company,
                'customer_address_snapshot' => $invoice->billing_address, 'customer_tax_number_snapshot' => $invoice->customer_tax_number,
                'created_by' => $user->id, 'finalized_by' => $user->id, 'finalized_at' => now(),
            ]);

            foreach ($calculation['items'] as $line) {
                $returnItem = $return->items()->create($line);
                if ($returnItem->restock_requested) {
                    $this->restock($user, $invoice, $return, $returnItem);
                }
            }

            $credited = $this->minor((string) $invoice->credited_total) + $credit;
            $fullyReturned = $this->allLinesReturned($invoice->id);
            $invoice->update(['credited_total' => $this->decimal($credited), 'return_status' => $fullyReturned ? 'full' : 'partial', 'updated_by' => $user->id]);
            $this->invoices->refreshFinancialBalance($invoice->refresh(), $user);
            $this->audit->record('crm.invoice.credit_note_finalized', $return, "Credit note {$return->credit_note_number} finalized", [
                'company_id' => $invoice->company_id, 'invoice_id' => $invoice->id, 'reason_code' => $return->reason_code,
                'credit_note_number' => $return->credit_note_number, 'restocked_items' => $return->items()->where('restock_requested', true)->count(),
            ]);

            return $return->load(['items', 'invoice', 'creator', 'branch']);
        }, 3);
    }

    public function findForUser(User $user, int $returnId): CrmInvoiceReturn
    {
        $return = CrmInvoiceReturn::query()->where('company_id', $user->company_id)->with(['items.originalInvoiceItem', 'invoice', 'company', 'branch', 'creator', 'finalizer'])->findOrFail($returnId);
        $this->assertAccess($user, $return->invoice);

        return $return;
    }

    /** @param Collection<int, CrmInvoiceItem> $items @param array<int, array<string, mixed>> $requested */
    private function calculate(CrmInvoice $invoice, $items, array $requested): array
    {
        $totals = array_fill_keys(['gross', 'discount', 'taxable', 'tax', 'cgst', 'sgst', 'igst', 'cess', 'credit_total', 'known_cogs', 'known_profit', 'unavailable_cost_items'], 0);
        $lines = [];
        foreach ($requested as $input) {
            $item = $items->get((int) ($input['invoice_item_id'] ?? 0));
            if (! $item) {
                throw ValidationException::withMessages(['items' => 'An invoice line is invalid or belongs to another invoice.']);
            }
            $original = $this->milli((string) $item->quantity);
            $previous = CrmInvoiceReturnItem::query()->where('original_invoice_item_id', $item->id)
                ->whereHas('crmInvoiceReturn', fn ($query) => $query->where('status', CrmInvoiceReturn::STATUS_FINALIZED))->sum('return_quantity');
            $previous = $this->milli((string) $previous);
            $returning = $this->milli((string) ($input['return_quantity'] ?? '0'));
            if ($returning <= 0) {
                continue;
            }
            if ($original <= 0 || $previous + $returning > $original) {
                throw ValidationException::withMessages(['items' => "{$item->name} exceeds its remaining returnable quantity."]);
            }
            $restock = (bool) ($input['restock'] ?? false);
            if ($restock && ! $this->originalStockMovement($invoice, $item)) {
                throw ValidationException::withMessages(['items' => "{$item->name} cannot be restocked because the original invoice has no authoritative stock movement."]);
            }
            $proRate = fn (string $field, int $fallback = 0): int => $this->proRated($this->minor((string) ($item->{$field} ?? $fallback)), $original, $previous, $returning);
            $gross = $proRate('gross_sales_snapshot', $this->minor((string) $item->line_subtotal) + $this->minor((string) $item->discount_amount));
            $discount = $proRate('discount_amount');
            $taxable = $proRate('net_sales_snapshot', $this->minor((string) $item->line_subtotal));
            $costKnown = in_array($item->cost_snapshot_status, ['captured', 'reconstructed'], true) && $item->total_cost_snapshot !== null;
            $cogs = $costKnown ? $proRate('total_cost_snapshot') : null;
            $line = [
                'original_invoice_item_id' => $item->id, 'product_id' => $item->product_id, 'product_name_snapshot' => $item->name,
                'sku_snapshot' => $item->sku_snapshot, 'hsn_sac_snapshot' => $item->hsn_sac, 'unit_snapshot' => $item->unit,
                'original_quantity' => $this->quantity($original), 'previously_returned_quantity' => $this->quantity($previous), 'return_quantity' => $this->quantity($returning),
                'unit_price_snapshot' => $item->unit_price, 'gross_reversal' => $this->decimal($gross), 'discount_reversal' => $this->decimal($discount),
                'taxable_reversal' => $this->decimal($taxable), 'tax_reversal' => $this->decimal($proRate('tax_amount')),
                'cgst_reversal' => $this->decimal($proRate('cgst_amount')), 'sgst_reversal' => $this->decimal($proRate('sgst_amount')),
                'igst_reversal' => $this->decimal($proRate('igst_amount')), 'cess_reversal' => $this->decimal($proRate('cess_amount')),
                'credit_total' => $this->decimal($proRate('line_total')), 'cost_status' => $costKnown ? $item->cost_snapshot_status : 'unavailable',
                'unit_cost_snapshot' => $costKnown ? $item->unit_cost_snapshot : null, 'cogs_reversal' => $cogs === null ? null : $this->decimal($cogs),
                'gross_profit_reversal' => $cogs === null ? null : $this->decimal($taxable - $cogs), 'restock_requested' => $restock,
                'inventory_disposition' => $restock ? 'restocked' : 'not_restocked', 'condition_note' => $input['condition_note'] ?? null,
            ];
            foreach (['gross' => 'gross_reversal', 'discount' => 'discount_reversal', 'taxable' => 'taxable_reversal', 'tax' => 'tax_reversal', 'cgst' => 'cgst_reversal', 'sgst' => 'sgst_reversal', 'igst' => 'igst_reversal', 'cess' => 'cess_reversal', 'credit_total' => 'credit_total'] as $total => $field) {
                $totals[$total] += $this->minor((string) $line[$field]);
            }
            if ($cogs === null) {
                $totals['unavailable_cost_items']++;
            } else {
                $totals['known_cogs'] += $cogs;
                $totals['known_profit'] += $taxable - $cogs;
            }
            $lines[] = $line;
        }

        return $totals + ['items' => $lines];
    }

    private function restock(User $user, CrmInvoice $invoice, CrmInvoiceReturn $return, CrmInvoiceReturnItem $item): void
    {
        if (StockMovement::query()->where('crm_invoice_return_item_id', $item->id)->exists()) {
            return;
        }
        $original = $this->originalStockMovement($invoice, $item->originalInvoiceItem);
        if (! $original) {
            throw ValidationException::withMessages(['stock' => "{$item->product_name_snapshot} has no original stock movement to reverse."]);
        }
        $level = StockLevel::query()->where('company_id', $user->company_id)->where('warehouse_id', $original->warehouse_id)
            ->where('stock_location_id', $original->stock_location_id)->where('product_id', $item->product_id)->lockForUpdate()->first();
        if (! $level) {
            throw ValidationException::withMessages(['stock' => "The original stock location for {$item->product_name_snapshot} is unavailable."]);
        }
        $before = (float) $level->quantity_on_hand;
        $quantity = (float) $item->return_quantity;
        $level->update(['quantity_on_hand' => $before + $quantity, 'quantity_available' => (float) $level->quantity_available + $quantity, 'last_stock_movement_at' => now()]);
        StockMovement::create([
            'company_id' => $user->company_id, 'branch_id' => $invoice->branch_id, 'warehouse_id' => $level->warehouse_id,
            'stock_location_id' => $level->stock_location_id, 'product_id' => $item->product_id, 'crm_invoice_return_item_id' => $item->id,
            'movement_type' => 'crm_sale_return', 'direction' => 'in', 'quantity' => $item->return_quantity,
            'quantity_before' => $before, 'quantity_after' => $before + $quantity, 'unit_cost' => $item->unit_cost_snapshot,
            'reference_type' => CrmInvoiceReturn::class, 'reference_id' => $return->id, 'reason' => 'customer_return',
            'notes' => $item->condition_note, 'created_by' => $user->id, 'occurred_at' => now(),
        ]);
    }

    private function originalStockMovement(CrmInvoice $invoice, CrmInvoiceItem $item): ?StockMovement
    {
        if (! $item->product_id) {
            return null;
        }

        $linked = StockMovement::query()
            ->where('company_id', $invoice->company_id)
            ->where('crm_invoice_item_id', $item->id)
            ->where('movement_type', 'sale')
            ->first();
        if ($linked) {
            return $linked;
        }

        return StockMovement::query()->where('company_id', $invoice->company_id)->where('reference_type', CrmInvoice::class)
            ->where('reference_id', $invoice->id)->where('product_id', $item->product_id)->where('movement_type', 'sale')->latest('id')->first();
    }

    private function assertEligible(CrmInvoice $invoice): void
    {
        if (! $invoice->branch_id) {
            throw ValidationException::withMessages(['invoice' => 'Historical invoices without an outlet cannot be returned automatically.']);
        }
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
            throw ValidationException::withMessages(['invoice' => 'Only an active issued invoice can receive a credit note.']);
        }
    }

    private function assertAccess(User $user, CrmInvoice $invoice): void
    {
        if ($invoice->company_id !== $user->company_id || ! $invoice->branch || ! $this->outlets->canAccess($user, $invoice->branch)) {
            abort(404);
        }
    }

    private function allLinesReturned(int $invoiceId): bool
    {
        return CrmInvoiceItem::query()->where('invoice_id', $invoiceId)->get()->every(function (CrmInvoiceItem $item): bool {
            $returned = CrmInvoiceReturnItem::query()->where('original_invoice_item_id', $item->id)
                ->whereHas('crmInvoiceReturn', fn ($query) => $query->where('status', CrmInvoiceReturn::STATUS_FINALIZED))->sum('return_quantity');

            return $this->milli((string) $returned) >= $this->milli((string) $item->quantity);
        });
    }

    /** @return array{number:string,financial_year:string} */
    private function nextNumber(CrmInvoice $invoice, CarbonInterface $at): array
    {
        $year = $this->financialYear($at);
        $sequence = CrmReturnSequence::query()->where('company_id', $invoice->company_id)->where('branch_id', $invoice->branch_id)->where('financial_year', $year)->lockForUpdate()->first();
        if (! $sequence) {
            try {
                $sequence = CrmReturnSequence::create(['company_id' => $invoice->company_id, 'branch_id' => $invoice->branch_id, 'financial_year' => $year]);
            } catch (QueryException) {
                $sequence = CrmReturnSequence::query()->where('company_id', $invoice->company_id)->where('branch_id', $invoice->branch_id)->where('financial_year', $year)->lockForUpdate()->firstOrFail();
            }
        }
        $sequence->increment('last_sequence');

        return ['financial_year' => $year, 'number' => sprintf('CN-%s-%06d', $year, $sequence->last_sequence)];
    }

    private function proRated(int $amount, int $original, int $previous, int $returning): int
    {
        return intdiv($amount * ($previous + $returning) + intdiv($original, 2), $original)
            - intdiv($amount * $previous + intdiv($original, 2), $original);
    }

    private function financialYear(CarbonInterface $at): string
    {
        $year = $at->month >= 4 ? $at->year : $at->year - 1;

        return sprintf('%d-%02d', $year, ($year + 1) % 100);
    }

    private function milli(string $value): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', trim($value), $matches)) {
            throw ValidationException::withMessages(['items' => 'Return quantities must use up to three decimal places.']);
        }

        return ((int) $matches[1] * 1000) + (int) str_pad($matches[2] ?? '', 3, '0');
    }

    private function minor(string $value): int
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim($value), $matches)) {
            throw ValidationException::withMessages(['amount' => 'A valid money amount is required.']);
        } $minor = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');

        return $matches[1] === '-' ? -$minor : $minor;
    }

    private function quantity(int $value): string
    {
        return intdiv($value, 1000).'.'.str_pad((string) ($value % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function decimal(int $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $value = abs($value);

        return $sign.intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }
}
