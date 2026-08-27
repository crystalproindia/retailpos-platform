<?php

namespace App\Services\Crm;

use App\Enums\Crm\InvoiceStatus;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceAmendment;
use App\Models\Inventory\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Compliance\GstTaxCalculator;
use App\Services\Finance\CreditLimitService;
use App\Services\Finance\FinanceBalanceService;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Inventory\StockService;
use App\Services\Outlets\OutletAccessService;
use App\Support\Finance\FinanceAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceAmendmentService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly CreditLimitService $creditLimits,
        private readonly FinanceBalanceService $balances,
        private readonly GstTaxCalculator $taxes,
        private readonly InventoryLocationAccessService $locations,
        private readonly StockService $stock,
        private readonly OutletAccessService $outlets,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function finalize(User $user, int $invoiceId, array $data): CrmInvoiceAmendment
    {
        return DB::transaction(function () use ($user, $invoiceId, $data): CrmInvoiceAmendment {
            $idempotencyKey = hash('sha256', (string) $data['idempotency_key']);
            $existing = CrmInvoiceAmendment::query()
                ->where('company_id', $user->company_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                if ((int) $existing->invoice_id !== $invoiceId) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This amendment request has already been used.']);
                }

                return $existing->load(['items.invoiceItem', 'invoice']);
            }

            $invoice = CrmInvoice::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($invoiceId);
            $this->assertEligible($invoice, $user);
            $version = (int) ($invoice->amendment_version ?: 1);
            if ((int) $data['expected_version'] !== $version) {
                throw ValidationException::withMessages(['invoice' => 'This invoice changed while you were preparing the amendment. Refresh and review the latest version.']);
            }

            $calculation = $this->withTaxComponents(
                $invoice,
                $this->invoices->calculateForUser($user, $data['items'], (string) $invoice->tax_mode),
            );
            $additionalMinor = FinanceAmount::minor($calculation['grand_total']);
            $this->creditLimits->assertCanIncreaseExposure(
                $invoice,
                $user,
                $additionalMinor,
                (bool) ($data['credit_limit_override'] ?? false),
                $data['credit_limit_override_reason'] ?? null,
            );

            $amountBefore = FinanceAmount::minor($invoice->grand_total);
            $amendment = CrmInvoiceAmendment::create([
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'invoice_id' => $invoice->id,
                'version_from' => $version,
                'version_to' => $version + 1,
                'reason' => trim((string) $data['reason']),
                'amount_before' => FinanceAmount::decimal($amountBefore),
                'subtotal_added' => $calculation['subtotal'],
                'discount_added' => $calculation['discount_total'],
                'taxable_added' => $calculation['taxable_total'],
                'tax_added' => $calculation['tax_total'],
                'cgst_added' => $calculation['cgst_total'],
                'sgst_added' => $calculation['sgst_total'],
                'igst_added' => $calculation['igst_total'],
                'cess_added' => $calculation['cess_total'],
                'amount_added' => $calculation['grand_total'],
                'amount_after' => FinanceAmount::decimal($amountBefore + $additionalMinor),
                'idempotency_key' => $idempotencyKey,
                'created_by' => $user->id,
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);

            $nextSort = (int) $invoice->items()->max('sort_order');
            foreach ($calculation['items'] as $index => $line) {
                $warehouseId = $line['product_id'] ? ($data['warehouse_id'] ?? null) : null;
                $product = $line['product_id'] ? Product::query()->where('company_id', $user->company_id)->findOrFail($line['product_id']) : null;
                if ($product && ($product->track_batches || $product->track_serials)) {
                    throw ValidationException::withMessages([
                        'items' => 'Batch- or serial-tracked products must be sold through a traceability-enabled workflow.',
                    ]);
                }
                if ($product?->track_inventory && ! $warehouseId) {
                    throw ValidationException::withMessages(['warehouse_id' => 'Choose the warehouse that will fulfil the added products.']);
                }
                if ($warehouseId) {
                    $warehouse = $this->locations->authorize($user, (int) $warehouseId);
                    if ((int) $warehouse->branch_id !== (int) $invoice->branch_id) {
                        throw ValidationException::withMessages(['warehouse_id' => 'Choose a warehouse in the invoice outlet.']);
                    }
                }

                $invoiceItem = $invoice->items()->create($line + [
                    'amendment_id' => $amendment->id,
                    'sort_order' => $nextSort + $index + 1,
                ]);
                $amendment->items()->create([
                    'invoice_item_id' => $invoiceItem->id,
                    'product_id' => $invoiceItem->product_id,
                    'warehouse_id' => $warehouseId,
                    'name_snapshot' => $invoiceItem->name,
                    'sku_snapshot' => $invoiceItem->sku_snapshot,
                    'hsn_sac_snapshot' => $invoiceItem->hsn_sac,
                    'quantity_snapshot' => $invoiceItem->quantity,
                    'unit_snapshot' => $invoiceItem->unit,
                    'unit_price_snapshot' => $invoiceItem->unit_price,
                    'discount_snapshot' => $invoiceItem->discount_amount,
                    'taxable_snapshot' => $invoiceItem->line_subtotal,
                    'tax_snapshot' => $invoiceItem->tax_amount,
                    'line_total_snapshot' => $invoiceItem->line_total,
                    'cost_status_snapshot' => $invoiceItem->cost_snapshot_status,
                    'unit_cost_snapshot' => $invoiceItem->unit_cost_snapshot,
                ]);

                if ($product?->track_inventory) {
                    $this->stock->recordSale($user, [
                        'warehouse_id' => $warehouseId,
                        'product_id' => $product->id,
                        'quantity' => $invoiceItem->quantity,
                        'unit_cost' => $invoiceItem->unit_cost_snapshot,
                        'crm_invoice_item_id' => $invoiceItem->id,
                        'reference_type' => CrmInvoice::class,
                        'reference_id' => $invoice->id,
                        'reason' => 'CRM invoice amendment',
                        'notes' => 'Invoice version '.$amendment->version_to,
                    ]);
                }
            }

            $invoice->update([
                'subtotal' => $this->add($invoice->subtotal, $calculation['subtotal']),
                'discount_total' => $this->add($invoice->discount_total, $calculation['discount_total']),
                'taxable_total' => $this->add($invoice->taxable_total, $calculation['taxable_total']),
                'tax_total' => $this->add($invoice->tax_total, $calculation['tax_total']),
                'cgst_total' => $this->add($invoice->cgst_total, $calculation['cgst_total']),
                'sgst_total' => $this->add($invoice->sgst_total, $calculation['sgst_total']),
                'igst_total' => $this->add($invoice->igst_total, $calculation['igst_total']),
                'cess_total' => $this->add($invoice->cess_total, $calculation['cess_total']),
                'grand_total' => FinanceAmount::decimal($amountBefore + $additionalMinor),
                'amendment_version' => $version + 1,
                'last_amended_at' => now(),
                'updated_by' => $user->id,
            ]);
            $this->balances->refreshInvoice($invoice, $user->id);
            $this->audit->record('crm.invoice.amended', $amendment, 'Issued invoice amended', [
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'version_from' => $version,
                'version_to' => $version + 1,
                'amount_before_minor' => $amountBefore,
                'amount_added_minor' => $additionalMinor,
                'amount_after_minor' => $amountBefore + $additionalMinor,
                'item_ids' => $amendment->items()->pluck('invoice_item_id')->all(),
                'reason' => $amendment->reason,
            ]);

            return $amendment->load(['items.invoiceItem', 'invoice']);
        }, 3);
    }

    public function assertEligible(CrmInvoice $invoice, User $user): void
    {
        if ($invoice->company_id !== $user->company_id) {
            abort(404);
        }
        if (! $invoice->branch || ! $this->outlets->canAccess($user, $invoice->branch)) {
            abort(404);
        }
        if (! $user->can('sales.invoices.amend')) {
            abort(403);
        }
        if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Sent, InvoiceStatus::Viewed, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid, InvoiceStatus::Overdue], true)) {
            throw ValidationException::withMessages(['invoice' => 'Only an active issued invoice can be amended.']);
        }
        if ($invoice->return_status === 'full') {
            throw ValidationException::withMessages(['invoice' => 'A fully credited invoice cannot be amended. Create a new invoice instead.']);
        }
    }

    /** @param array<string, mixed> $calculation @return array<string, mixed> */
    private function withTaxComponents(CrmInvoice $invoice, array $calculation): array
    {
        $totals = ['cgst' => 0, 'sgst' => 0, 'igst' => 0, 'cess' => 0];
        foreach ($calculation['items'] as &$line) {
            $taxRate = (float) $line['tax_rate'];
            if ((string) $invoice->tax_mode === DocumentTaxModeService::NO_GST || $taxRate <= 0) {
                $tax = ['cgst' => '0.00', 'sgst' => '0.00', 'igst' => '0.00', 'cess' => '0.00', 'tax_total' => '0.00', 'line_total' => $line['line_subtotal'], 'treatment' => 'not_taxable'];
            } else {
                $tax = $this->taxes->calculate(
                    (string) $line['line_subtotal'],
                    (string) $line['tax_rate'],
                    $invoice->supplier_state_code_snapshot,
                    $invoice->place_of_supply_state_code,
                );
            }
            $line['tax_amount'] = $tax['tax_total'];
            $line['cgst_amount'] = $tax['cgst'];
            $line['sgst_amount'] = $tax['sgst'];
            $line['igst_amount'] = $tax['igst'];
            $line['cess_amount'] = $tax['cess'];
            $line['tax_treatment_snapshot'] = $tax['treatment'];
            $line['line_total'] = $tax['line_total'];
            foreach ($totals as $key => $unused) {
                $totals[$key] += FinanceAmount::minor($tax[$key]);
            }
        }
        unset($line);
        $calculation['cgst_total'] = FinanceAmount::decimal($totals['cgst']);
        $calculation['sgst_total'] = FinanceAmount::decimal($totals['sgst']);
        $calculation['igst_total'] = FinanceAmount::decimal($totals['igst']);
        $calculation['cess_total'] = FinanceAmount::decimal($totals['cess']);
        $calculation['tax_total'] = FinanceAmount::decimal(array_sum($totals));
        $calculation['grand_total'] = FinanceAmount::decimal(
            FinanceAmount::minor($calculation['taxable_total']) + array_sum($totals),
        );

        return $calculation;
    }

    private function add(string|int|float|null $left, string|int|float|null $right): string
    {
        return FinanceAmount::decimal(FinanceAmount::minor($left) + FinanceAmount::minor($right));
    }
}
