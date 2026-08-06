<?php

namespace App\Services\Pos;

use App\Events\Domain\Pos\PosDomainEvent;
use App\Models\Branch;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerActivityLog;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Pos\PosRefund;
use App\Models\Pos\PosReturn;
use App\Models\Pos\PosReturnItem;
use App\Models\Pos\PosReturnSequence;
use App\Models\Pos\PosReturnSetting;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use App\Models\User;
use App\Repositories\Pos\PosSaleRepository;
use App\Services\AuditLogger;
use App\Services\Customers\WalletService;
use App\Services\Events\DomainEventDispatcher;
use App\Services\Outlets\OutletAccessService;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosReturnService
{
    public function __construct(
        private readonly PosSaleRepository $sales,
        private readonly OutletAccessService $outlets,
        private readonly WalletService $wallet,
        private readonly AuditLogger $audit,
        private readonly DomainEventDispatcher $events,
    ) {}

    public function settings(int $companyId): PosReturnSetting
    {
        return PosReturnSetting::firstOrCreate(['company_id' => $companyId])->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateSettings(User $user, array $data): PosReturnSetting
    {
        $settings = $this->settings($user->company_id);
        $settings->fill($data)->save();
        $this->audit->record('pos.returns.settings_updated', $settings, 'POS return settings updated', ['company_id' => $user->company_id]);

        return $settings->refresh();
    }

    /** @param array<string, mixed> $filters */
    public function lookup(User $user, array $filters): Collection
    {
        $query = $this->sales->queryForUser($user)
            ->with(['customer', 'payments', 'returns' => fn ($returns) => $returns->where('status', PosReturn::STATUS_COMPLETED)])
            ->where('status', 'completed');

        if (filled($filters['q'] ?? null)) {
            $search = trim((string) $filters['q']);
            $query->where(function ($sales) use ($search): void {
                $sales->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('sale_number', 'like', "%{$search}%")
                    ->orWhere('id', is_numeric($search) ? (int) $search : 0)
                    ->orWhere('customer_name_snapshot', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customers) => $customers->where('display_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return $query->when($filters['from'] ?? null, fn ($sales, $date) => $sales->whereDate('completed_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($sales, $date) => $sales->whereDate('completed_at', '<=', $date))
            ->latest('completed_at')->limit(100)->get()->filter(fn (PosSale $sale) => $this->remainingMinor($sale) > 0)->values();
    }

    public function saleForReturn(User $user, int $saleId, bool $overrideWindow = false): PosSale
    {
        $sale = $this->sales->findForUser($user, $saleId);
        if ($sale->status !== 'completed') {
            throw ValidationException::withMessages(['sale' => 'Only completed POS sales can be returned.']);
        }
        $this->assertWindow($user, $sale, $overrideWindow);
        $sale->load(['returns.items' => fn ($items) => $items->whereHas('posReturn', fn ($returns) => $returns->whereIn('status', [PosReturn::STATUS_APPROVED, PosReturn::STATUS_COMPLETED]))]);

        return $sale;
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, PosSale $sale, array $data): PosReturn
    {
        return DB::transaction(function () use ($user, $sale, $data): PosReturn {
            $sale = $this->saleForReturn($user, $sale->id, (bool) ($data['window_override'] ?? false));
            $sale = PosSale::query()->with(['items', 'payments', 'returns.items'])->lockForUpdate()->findOrFail($sale->id);
            $calculation = $this->calculate($sale, $data['items'] ?? []);
            if ($calculation['refund_total_minor'] <= 0) {
                throw ValidationException::withMessages(['items' => 'Choose at least one remaining item quantity to return.']);
            }
            if (($data['return_type'] ?? null) === 'full_return' && $calculation['refund_total_minor'] !== $this->remainingMinor($sale)) {
                throw ValidationException::withMessages(['items' => 'A full return must include every remaining sale item quantity.']);
            }
            $settings = $this->settings($user->company_id);
            if (! $settings->cashiers_may_initiate && ! $user->can('pos.returns.approve')) {
                throw ValidationException::withMessages(['return' => 'Only a manager can initiate returns for this company.']);
            }
            if ($settings->receipt_required && ! ($data['receipt_confirmed'] ?? false)) {
                throw ValidationException::withMessages(['receipt_confirmed' => 'Confirm that the original receipt or invoice was reviewed.']);
            }
            if (! $settings->anonymous_returns_allowed && ! $sale->customer_id) {
                throw ValidationException::withMessages(['sale' => 'This company requires an identified customer for a return.']);
            }
            if (($data['reason_code'] ?? null) === 'damaged' && ! $settings->damaged_may_restock && collect($calculation['items'])->contains(fn (array $item) => $item['stock_disposition'] === 'restock')) {
                throw ValidationException::withMessages(['items' => 'Damaged goods cannot be restocked under the current return controls.']);
            }
            if (($data['return_type'] ?? null) === 'exchange' && empty($data['exchange_sale_id'])) {
                throw ValidationException::withMessages(['exchange_sale_id' => 'Link the completed replacement sale for an exchange.']);
            }
            if (($data['return_type'] ?? null) === 'exchange' && ! $user->can('pos.exchanges.create')) {
                throw ValidationException::withMessages(['exchange_sale_id' => 'You are not permitted to create an exchange.']);
            }
            $this->validateSettlement($sale, $data, $calculation['refund_total_minor'], $settings);
            $exchange = $this->exchangeSale($user, $sale, $data['exchange_sale_id'] ?? null);
            $idempotencyKey = (string) ($data['idempotency_key'] ?? str()->uuid());
            $existing = PosReturn::query()->where('company_id', $user->company_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) return $existing->load(['items', 'refunds', 'originalSale.payments', 'customer']);
            $numbers = $this->nextNumbers($user->company_id, $sale->branch_id, now());

            $requiresApproval = $settings->manager_approval_required || ($settings->approval_threshold !== null && $calculation['refund_total_minor'] > $this->minor((string) $settings->approval_threshold));
            $return = PosReturn::create([
                'company_id' => $user->company_id, 'branch_id' => $sale->branch_id, 'original_sale_id' => $sale->id,
                'exchange_sale_id' => $exchange?->id, 'customer_id' => $sale->customer_id, 'return_number' => $numbers['return_number'],
                'financial_year' => $numbers['financial_year'], 'return_type' => $data['return_type'] ?? 'partial_return',
                'status' => $requiresApproval ? PosReturn::STATUS_PENDING_APPROVAL : PosReturn::STATUS_APPROVED,
                'return_date' => now()->toDateString(), 'timezone' => $user->company?->timezone ?: config('app.timezone'), 'currency' => $sale->currency,
                'subtotal' => $this->decimal($calculation['gross_minor']), 'discount_adjustment_total' => $this->decimal($calculation['discount_minor']),
                'taxable_adjustment_total' => $this->decimal($calculation['taxable_minor']), 'tax_adjustment_total' => $this->decimal($calculation['tax_minor']),
                'cgst_adjustment_total' => $this->decimal($calculation['cgst_minor']), 'sgst_adjustment_total' => $this->decimal($calculation['sgst_minor']),
                'igst_adjustment_total' => $this->decimal($calculation['igst_minor']), 'cess_adjustment_total' => $this->decimal($calculation['cess_minor']),
                'refund_total' => $this->decimal($calculation['refund_total_minor']), 'store_credit_total' => $this->decimal($this->settlementMinor($data, 'store_credit')),
                'exchange_payable_total' => $this->decimal(max(0, $this->saleMinor($exchange) - $calculation['refund_total_minor'])),
                'exchange_refund_total' => $this->decimal(max(0, $calculation['refund_total_minor'] - $this->saleMinor($exchange))),
                'reason_code' => $data['reason_code'] ?? null, 'reason_text' => $data['reason_text'] ?? null, 'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey, 'requested_by' => $user->id,
            ]);
            foreach ($calculation['items'] as $item) $return->items()->create($item);
            foreach ($data['refunds'] ?? [] as $refund) $return->refunds()->create([
                'company_id' => $user->company_id, 'original_payment_id' => $refund['original_payment_id'] ?? null, 'method' => $refund['method'],
                'amount' => $refund['amount'], 'external_reference' => $refund['external_reference'] ?? null, 'status' => 'pending',
            ]);
            if ($data['window_override'] ?? false) {
                $this->audit->record('pos.return.window_overridden', $return, 'POS return window overridden', ['company_id' => $user->company_id, 'reason' => $data['override_reason']]);
            }
            $this->audit->record('pos.return.created', $return, "POS return {$return->return_number} created", ['company_id' => $user->company_id, 'sale_id' => $sale->id]);
            $this->dispatch('pos.return.created', $user, $return);

            return $return->load(['items', 'refunds', 'originalSale.payments', 'customer']);
        });
    }

    public function approve(User $user, PosReturn $return): PosReturn
    {
        return DB::transaction(function () use ($user, $return): PosReturn {
            $return = $this->findForUser($user, $return->id, true)->lockForUpdate()->firstOrFail();
            if ($return->status === PosReturn::STATUS_APPROVED) return $return;
            if ($return->status !== PosReturn::STATUS_PENDING_APPROVAL) throw ValidationException::withMessages(['return' => 'Only pending returns can be approved.']);
            if ($return->requested_by === $user->id && ! $user->isAdministrator()) throw ValidationException::withMessages(['approval' => 'A different manager must approve a return you requested.']);
            $this->assertStillReturnable($return);
            $return->update(['status' => PosReturn::STATUS_APPROVED, 'approved_by' => $user->id, 'approved_at' => now()]);
            $this->audit->record('pos.return.approved', $return, "POS return {$return->return_number} approved", ['company_id' => $user->company_id]);
            $this->dispatch('pos.return.approved', $user, $return);

            return $return->refresh();
        });
    }

    public function reject(User $user, PosReturn $return, string $reason): PosReturn
    {
        return DB::transaction(function () use ($user, $return, $reason): PosReturn {
            $return = $this->findForUser($user, $return->id, true)->lockForUpdate()->firstOrFail();
            if (! in_array($return->status, [PosReturn::STATUS_DRAFT, PosReturn::STATUS_PENDING_APPROVAL], true)) {
                throw ValidationException::withMessages(['return' => 'Only an uncompleted return can be rejected.']);
            }
            $return->update(['status' => PosReturn::STATUS_REJECTED, 'rejected_by' => $user->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
            $this->audit->record('pos.return.rejected', $return, "POS return {$return->return_number} rejected", ['company_id' => $user->company_id]);
            $this->dispatch('pos.return.rejected', $user, $return);

            return $return->refresh();
        });
    }

    public function cancel(User $user, PosReturn $return): PosReturn
    {
        return DB::transaction(function () use ($user, $return): PosReturn {
            $return = $this->findForUser($user, $return->id, true)->lockForUpdate()->firstOrFail();
            if (! in_array($return->status, [PosReturn::STATUS_DRAFT, PosReturn::STATUS_PENDING_APPROVAL], true)) {
                throw ValidationException::withMessages(['return' => 'Only an unposted return can be cancelled.']);
            }
            if ($return->requested_by !== $user->id && ! $user->can('pos.returns.approve')) {
                throw ValidationException::withMessages(['return' => 'Only the requester or an outlet manager can cancel this return.']);
            }

            $return->update(['status' => PosReturn::STATUS_CANCELLED]);
            $this->audit->record('pos.return.cancelled', $return, "POS return {$return->return_number} cancelled", ['company_id' => $user->company_id]);
            $this->dispatch('pos.return.cancelled', $user, $return);

            return $return->refresh();
        });
    }

    public function complete(User $user, PosReturn $return): PosReturn
    {
        return DB::transaction(function () use ($user, $return): PosReturn {
            $return = $this->findForUser($user, $return->id, true)->with(['items.originalSaleItem', 'originalSale.customer', 'refunds'])->lockForUpdate()->firstOrFail();
            if ($return->status === PosReturn::STATUS_COMPLETED) return $return;
            if ($return->status !== PosReturn::STATUS_APPROVED) throw ValidationException::withMessages(['return' => 'Approve this return before completing it.']);
            $sale = PosSale::query()->lockForUpdate()->findOrFail($return->original_sale_id);
            $this->assertStillReturnable($return);
            foreach ($return->refunds as $refund) {
                if (! $user->can('pos.refunds.record')) throw ValidationException::withMessages(['refunds' => 'You are not permitted to record refunds.']);
                if ($refund->method === 'cash' && ! $user->can('pos.refunds.cash')) throw ValidationException::withMessages(['refunds' => 'You are not permitted to complete cash refunds.']);
                if ($refund->method === 'store_credit' && ! $user->can('pos.refunds.store_credit')) throw ValidationException::withMessages(['refunds' => 'You are not permitted to issue store credit.']);
            }
            foreach ($return->items as $item) $this->restoreStock($user, $return, $item);
            foreach ($return->refunds as $refund) $refund->update(['status' => 'recorded', 'processed_by' => $user->id, 'processed_at' => now()]);
            if ((float) $return->store_credit_total > 0) {
                $customer = $sale->customer;
                if (! $customer) throw ValidationException::withMessages(['refunds' => 'Store credit requires the customer recorded on the original sale.']);
                $this->wallet->creditForReturn($customer, $user, (float) $return->store_credit_total, "Store credit for {$return->return_number}", PosReturn::class, $return->id);
            }
            $creditNote = $this->nextCreditNote($return);
            $return->update(['status' => PosReturn::STATUS_COMPLETED, 'credit_note_number' => $creditNote, 'completed_by' => $user->id, 'completed_at' => now()]);
            $returnedMinor = $this->minor((string) $sale->returned_amount) + $this->minor((string) $return->refund_total);
            $saleTotal = $this->minor((string) $sale->total_amount);
            $sale->update(['returned_amount' => $this->decimal($returnedMinor), 'return_status' => $returnedMinor >= $saleTotal ? 'full' : 'partial']);
            $this->recordCustomerReturn($sale->customer, $return, $user);
            $this->audit->record('pos.return.completed', $return, "POS return {$return->return_number} completed", ['company_id' => $user->company_id, 'credit_note_number' => $creditNote]);
            $this->dispatch('pos.return.completed', $user, $return, ['credit_note_number' => $creditNote]);

            return $return->refresh()->load(['items', 'refunds', 'originalSale', 'customer', 'requester', 'approver', 'completer']);
        });
    }

    public function findForUser(User $user, int $id, bool $queryOnly = false)
    {
        $query = PosReturn::query()->where('company_id', $user->company_id)->where(function ($returns) use ($user): void {
            $outlets = $this->outlets->accessibleOutlets($user)->pluck('id');
            $returns->whereIn('branch_id', $outlets);
            if ($this->outlets->hasCompanyWideAccess($user)) $returns->orWhereNull('branch_id');
        });
        if ($queryOnly) return $query;
        return $query->with(['items', 'refunds.originalPayment', 'originalSale.items', 'originalSale.payments', 'customer', 'requester', 'approver', 'completer'])->findOrFail($id);
    }

    /** @return array<string, mixed> */
    public function preview(PosSale $sale, array $items): array { return $this->calculate($sale, $items); }

    /** @param array<int, array<string,mixed>> $requested */
    private function calculate(PosSale $sale, array $requested): array
    {
        $sale->loadMissing(['items', 'returns.items']);
        $requested = collect($requested)->keyBy(fn ($item) => (int) ($item['original_sale_item_id'] ?? 0));
        $results = [];
        $totals = array_fill_keys(['gross_minor', 'discount_minor', 'taxable_minor', 'tax_minor', 'cgst_minor', 'sgst_minor', 'igst_minor', 'cess_minor', 'refund_total_minor'], 0);
        foreach ($sale->items as $saleItem) {
            $input = $requested->get($saleItem->id);
            if (! $input || $this->quantityThousandths((string) ($input['return_quantity'] ?? '0')) <= 0) continue;
            $previous = $sale->returns->whereIn('status', [PosReturn::STATUS_APPROVED, PosReturn::STATUS_COMPLETED])->flatMap->items->where('original_sale_item_id', $saleItem->id)->sum(fn (PosReturnItem $item) => $this->quantityThousandths((string) $item->return_quantity));
            $original = $this->quantityThousandths((string) $saleItem->quantity);
            $returning = $this->quantityThousandths((string) $input['return_quantity']);
            if ($original <= 0 || $returning + $previous > $original) throw ValidationException::withMessages(['items' => "{$saleItem->product_name} exceeds its remaining returnable quantity."]);
            $proRate = fn (string $field): int => $this->proRatedMinor($this->minor((string) ($saleItem->{$field} ?? 0)), $original, $previous, $returning);
            $gross = $proRate('gross_amount');
            if ($gross === 0) $gross = $this->proRatedMinor($this->minor((string) $saleItem->unit_price) * intdiv($original, 1000), $original, $previous, $returning);
            $line = [
                'original_sale_item_id' => $saleItem->id, 'product_id' => $saleItem->product_id, 'product_variant_id' => $saleItem->product_variant_id,
                'product_name' => $saleItem->product_name, 'sku' => $saleItem->sku, 'barcode' => $saleItem->barcode, 'variant_label' => $saleItem->variant_label,
                'hsn_sac' => $saleItem->hsn_sac, 'unit' => $saleItem->unit, 'original_quantity' => $this->quantity($original), 'previously_returned_quantity' => $this->quantity($previous),
                'return_quantity' => $this->quantity($returning), 'unit_price_snapshot' => $saleItem->unit_price, 'gross_adjustment' => $this->decimal($gross),
                'discount_adjustment' => $this->decimal($proRate('discount_amount')), 'taxable_adjustment' => $this->decimal($proRate('taxable_amount')),
                'tax_adjustment' => $this->decimal($proRate('tax_amount')), 'cgst_adjustment' => $this->decimal($proRate('cgst_amount')),
                'sgst_adjustment' => $this->decimal($proRate('sgst_amount')), 'igst_adjustment' => $this->decimal($proRate('igst_amount')),
                'cess_adjustment' => $this->decimal($proRate('cess_amount')), 'line_refund_total' => $this->decimal($proRate('line_total')),
                'stock_disposition' => $input['stock_disposition'] ?? 'restock', 'condition_note' => $input['condition_note'] ?? null,
            ];
            foreach (['gross_minor' => 'gross_adjustment', 'discount_minor' => 'discount_adjustment', 'taxable_minor' => 'taxable_adjustment', 'tax_minor' => 'tax_adjustment', 'cgst_minor' => 'cgst_adjustment', 'sgst_minor' => 'sgst_adjustment', 'igst_minor' => 'igst_adjustment', 'cess_minor' => 'cess_adjustment', 'refund_total_minor' => 'line_refund_total'] as $total => $field) $totals[$total] += $this->minor((string) $line[$field]);
            $results[] = $line;
        }
        return $totals + ['items' => $results];
    }

    /** @param array<string, mixed> $data */
    private function validateSettlement(PosSale $sale, array $data, int $total, PosReturnSetting $settings): void
    {
        $settled = collect($data['refunds'] ?? [])->sum(fn ($refund) => $this->minor((string) ($refund['amount'] ?? '0')));
        if ($settled !== $total) throw ValidationException::withMessages(['refunds' => 'Refund and store-credit allocations must equal the server-calculated return total.']);
        foreach ($data['refunds'] ?? [] as $refund) {
            if (! in_array($refund['method'] ?? '', ['cash', 'card', 'upi', 'bank_transfer', 'store_credit', 'other'], true)) throw ValidationException::withMessages(['refunds' => 'Choose a supported refund method.']);
            if (($refund['method'] ?? null) === 'store_credit' && (! $settings->store_credit_allowed || ! $sale->customer_id)) throw ValidationException::withMessages(['refunds' => 'Store credit is unavailable for this return.']);
            if (filled($refund['external_reference'] ?? null) && PosRefund::query()->where('company_id', $sale->company_id)->where('external_reference', $refund['external_reference'])->whereNotIn('status', ['failed', 'cancelled'])->exists()) throw ValidationException::withMessages(['refunds' => 'This external refund reference is already in use.']);
            if ($settings->refund_original_method_only && ($refund['method'] ?? null) !== 'store_credit' && ! $sale->payments->contains(fn ($payment) => $payment->id === (int) ($refund['original_payment_id'] ?? 0) && $payment->payment_method === $refund['method'])) throw ValidationException::withMessages(['refunds' => 'Refunds must use a recorded original payment method.']);
        }
    }

    private function restoreStock(User $user, PosReturn $return, PosReturnItem $item): void
    {
        if (! $item->product_id) return;
        $existing = StockMovement::query()->where('reference_type', PosReturn::class)->where('reference_id', $return->id)->where('product_id', $item->product_id)->where('movement_type', 'sale_return')->exists();
        if ($existing) return;
        $saleMovement = StockMovement::query()->where('reference_type', PosSale::class)->where('reference_id', $return->original_sale_id)->where('product_id', $item->product_id)->where('movement_type', 'sale')->latest('id')->first();
        if (! $saleMovement) return;
        $disposition = $item->stock_disposition;
        $restock = $disposition === 'restock';
        if ($restock && $disposition !== 'restock') return;
        $level = StockLevel::query()->where('company_id', $user->company_id)->where('warehouse_id', $saleMovement->warehouse_id)->where('stock_location_id', $saleMovement->stock_location_id)->where('product_id', $item->product_id)->lockForUpdate()->first();
        if (! $level) throw ValidationException::withMessages(['stock' => "A stock location is no longer available for {$item->product_name}."]);
        $before = (float) $level->quantity_on_hand;
        $quantity = (float) $item->return_quantity;
        $after = $restock ? $before + $quantity : $before;
        if ($restock) $level->update(['quantity_on_hand' => $after, 'quantity_available' => (float) $level->quantity_available + $quantity, 'last_stock_movement_at' => now()]);
        StockMovement::create(['company_id' => $user->company_id, 'branch_id' => $return->branch_id, 'warehouse_id' => $level->warehouse_id, 'stock_location_id' => $level->stock_location_id, 'product_id' => $item->product_id, 'movement_type' => 'sale_return', 'direction' => $restock ? 'in' : 'neutral', 'quantity' => $item->return_quantity, 'quantity_before' => $before, 'quantity_after' => $after, 'unit_cost' => $saleMovement->unit_cost, 'reference_type' => PosReturn::class, 'reference_id' => $return->id, 'reason' => $disposition, 'notes' => $item->condition_note, 'created_by' => $user->id, 'occurred_at' => now()]);
    }

    private function recordCustomerReturn(?Customer $customer, PosReturn $return, User $user): void
    {
        if (! $customer) return;
        $customer->increment('total_return_amount', (float) $return->refund_total);
        $customer->increment('total_returns_count');
        $customer->update(['last_return_at' => now()]);
        CustomerActivityLog::create(['company_id' => $customer->company_id, 'customer_id' => $customer->id, 'activity_type' => 'return', 'title' => 'POS return completed', 'description' => $return->return_number, 'reference_type' => PosReturn::class, 'reference_id' => $return->id, 'user_id' => $user->id, 'occurred_at' => now()]);
    }

    private function assertStillReturnable(PosReturn $return): void
    {
        $return->loadMissing('items.originalSaleItem');
        foreach ($return->items as $item) {
            $other = PosReturnItem::query()->where('original_sale_item_id', $item->original_sale_item_id)->whereHas('posReturn', fn ($returns) => $returns->whereIn('status', [PosReturn::STATUS_APPROVED, PosReturn::STATUS_COMPLETED])->whereKeyNot($return->id))->sum('return_quantity');
            if (((float) $other + (float) $item->return_quantity) > (float) $item->original_quantity + 0.0001) {
                throw ValidationException::withMessages(['items' => "{$item->product_name} no longer has enough remaining quantity to approve this return."]);
            }
        }
    }

    private function assertWindow(User $user, PosSale $sale, bool $override): void
    {
        $settings = $this->settings($user->company_id);
        if (! $sale->completed_at || now()->greaterThan($sale->completed_at->copy()->addDays($settings->return_window_days)) && ! ($override && $user->can('pos.returns.override_window'))) throw ValidationException::withMessages(['sale' => 'This sale is outside the configured return window.']);
    }

    private function exchangeSale(User $user, PosSale $original, mixed $id): ?PosSale
    {
        if (! $id) return null;
        $exchange = $this->sales->findForUser($user, (int) $id);
        if ($exchange->status !== 'completed' || $exchange->id === $original->id || $exchange->branch_id !== $original->branch_id) throw ValidationException::withMessages(['exchange_sale_id' => 'Choose a completed replacement sale from the same outlet.']);
        return $exchange;
    }

    private function nextNumbers(int $companyId, ?int $branchId, CarbonInterface $at): array
    {
        if (! $branchId) throw ValidationException::withMessages(['sale' => 'A return cannot be created for a legacy sale without an outlet.']);
        $year = $this->financialYear($at);
        $sequence = PosReturnSequence::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('financial_year', $year)->lockForUpdate()->first();
        if (! $sequence) {
            try { $sequence = PosReturnSequence::create(['company_id' => $companyId, 'branch_id' => $branchId, 'financial_year' => $year]); }
            catch (QueryException) { $sequence = PosReturnSequence::query()->where('company_id', $companyId)->where('branch_id', $branchId)->where('financial_year', $year)->lockForUpdate()->firstOrFail(); }
        }
        $sequence->increment('last_return_sequence');
        return ['financial_year' => $year, 'return_number' => sprintf('RET-%s-%06d', $year, $sequence->last_return_sequence)];
    }

    private function nextCreditNote(PosReturn $return): string
    {
        $sequence = PosReturnSequence::query()->where('company_id', $return->company_id)->where('branch_id', $return->branch_id)->where('financial_year', $return->financial_year)->lockForUpdate()->firstOrFail();
        $sequence->increment('last_credit_note_sequence');
        return sprintf('CN-%s-%06d', $return->financial_year, $sequence->last_credit_note_sequence);
    }

    private function remainingMinor(PosSale $sale): int { return max(0, $this->minor((string) $sale->total_amount) - $this->minor((string) $sale->returned_amount)); }
    private function saleMinor(?PosSale $sale): int { return $sale ? $this->minor((string) $sale->total_amount) : 0; }
    private function settlementMinor(array $data, string $method): int { return (int) collect($data['refunds'] ?? [])->where('method', $method)->sum(fn ($refund) => $this->minor((string) ($refund['amount'] ?? '0'))); }
    private function proRatedMinor(int $amount, int $original, int $previous, int $returning): int { return intdiv($amount * ($previous + $returning) + intdiv($original, 2), $original) - intdiv($amount * $previous + intdiv($original, 2), $original); }
    private function quantityThousandths(string $value): int { if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $value, $m)) throw ValidationException::withMessages(['items' => 'Return quantities must be valid.']); return ((int) $m[1] * 1000) + (int) str_pad($m[2] ?? '', 3, '0'); }
    private function quantity(int $value): string { return intdiv($value, 1000).'.'.str_pad((string) ($value % 1000), 3, '0', STR_PAD_LEFT); }
    private function minor(string $value): int { if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) throw ValidationException::withMessages(['refunds' => 'Amounts must have up to two decimal places.']); return ((int) $m[1] * 100) + (int) str_pad($m[2] ?? '', 2, '0'); }
    private function decimal(int $minor): string { return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT); }
    private function financialYear(CarbonInterface $at): string { $year = $at->month >= 4 ? $at->year : $at->year - 1; return sprintf('%d-%02d', $year, ($year + 1) % 100); }
    private function dispatch(string $key, User $user, PosReturn $return, array $payload = []): void { $this->events->dispatch(new PosDomainEvent($key, $user->company_id, $user->id, PosReturn::class, $return->id, $payload + ['return_number' => $return->return_number, 'sale_id' => $return->original_sale_id])); }
}
