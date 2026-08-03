<?php

namespace App\Services\Pos;

use App\Events\Domain\Pos\PosDomainEvent;
use App\Models\Branch;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerActivityLog;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Pos\CustomerProductSummary;
use App\Models\Pos\PosProductPairSummary;
use App\Models\Pos\PosRegister;
use App\Models\Pos\PosSale;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Customers\CustomerInsightService;
use App\Services\Events\DomainEventDispatcher;
use App\Services\Outlets\OutletAccessService;
use App\Services\Saas\UsageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosCheckoutService
{
    public function __construct(
        private readonly PosNumberService $numbers,
        private readonly PosRegisterService $registers,
        private readonly PosBillingSettingsService $settings,
        private readonly PosBillingTotalsService $totals,
        private readonly CustomerInsightService $insights,
        private readonly AuditLogger $audit,
        private readonly DomainEventDispatcher $events,
        private readonly UsageService $usage,
        private readonly OutletAccessService $outlets,
    ) {}

    /** @param array<string, mixed> $data */
    public function hold(User $user, array $data): PosSale
    {
        return DB::transaction(function () use ($user, $data): PosSale {
            $branch = $this->resolveOutlet($user, $data);
            $customer = $this->customer($user, $data);
            $calculation = $this->calculation($user, $branch, $customer, $data, false);
            $sale = $this->persistSale($user, $branch, $customer, $data, $calculation, 'held');
            $this->audit->record('pos.sale.held', $sale, 'POS bill held');
            $this->dispatch('pos.sale.held', $user, $sale);

            return $sale->load('items.product');
        });
    }

    /** @param array<string, mixed> $data */
    public function complete(User $user, array $data): PosSale
    {
        return DB::transaction(function () use ($user, $data): PosSale {
            $completionKey = $data['completion_key'] ?? (string) Str::uuid();
            $existing = PosSale::query()
                ->where('company_id', $user->company_id)
                ->where('completion_key', $completionKey)
                ->where('status', 'completed')
                ->first();
            if ($existing) {
                $this->assertSaleAccess($existing, $user);

                return $existing->load(['items.product', 'payments', 'customer.groups.group', 'customer.loyaltyAccount', 'customer.insight']);
            }

            $this->usage->assertWithinLimit($user->company, 'monthly_pos_transactions');
            $this->usage->assertWithinLimit($user->company, 'monthly_invoices');
            $branch = $this->resolveOutlet($user, $data);
            $data['branch_id'] = $branch->id;
            $this->requireRegisterSession($user, $branch, $data);
            $customer = $this->customer($user, $data);
            $calculation = $this->calculation($user, $branch, $customer, $data, true);
            $payments = $this->validatePayments($data['payments'] ?? [], $calculation['totals']['total_amount']);
            $heldSale = $this->heldSale($user, $data, $branch);
            $data['completion_key'] = $completionKey;
            $sale = $this->persistSale($user, $branch, $customer, $data, $calculation, 'completed', $payments, $heldSale);

            foreach ($calculation['lines'] as $line) {
                $this->postStock($user, $sale, $line);
            }
            foreach ($payments['records'] as $payment) {
                $sale->payments()->create([
                    'company_id' => $user->company_id,
                    'payment_method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'],
                    'metadata' => ['source' => 'manual_pos'],
                    'status' => 'recorded',
                    'paid_at' => now(),
                    'created_by' => $user->id,
                ]);
            }
            if ($sale->customer) {
                $this->recordCustomerHistory($sale->customer, $sale, $calculation['lines'], $user);
            }
            $this->audit->record('pos.sale.completed', $sale, 'POS sale completed');
            $this->dispatch('pos.sale.completed', $user, $sale, ['customer_id' => $sale->customer_id, 'total' => $sale->total_amount]);

            return $sale->load(['items.product', 'payments', 'customer.groups.group', 'customer.loyaltyAccount', 'customer.insight']);
        });
    }

    public function cancelHeld(PosSale $sale, User $user): void
    {
        DB::transaction(function () use ($sale, $user): void {
            $sale = PosSale::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($sale->id);
            $this->assertSaleAccess($sale, $user);
            if ($sale->status !== 'held' || $sale->held_by !== $user->id) {
                throw new AuthorizationException('Only your held bills can be discarded.');
            }

            $this->audit->record('pos.sale.held_cancelled', $sale, 'Held POS bill discarded');
            $this->dispatch('pos.sale.held_cancelled', $user, $sale);
            $sale->delete();
        });
    }

    public function void(PosSale $sale, User $user, string $reason): PosSale
    {
        return DB::transaction(function () use ($sale, $user, $reason): PosSale {
            $sale = PosSale::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($sale->id);
            $this->assertSaleAccess($sale, $user);
            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages(['sale' => 'Only completed POS sales can be voided.']);
            }

            // A void is an immutable administrative status. Returns/refunds own stock and payment reversals.
            $sale->update(['status' => 'voided', 'voided_by' => $user->id, 'voided_at' => now(), 'void_reason' => $reason]);
            $this->audit->record('pos.sale.voided', $sale, 'POS sale voided', ['company_id' => $user->company_id]);
            $this->dispatch('pos.sale.voided', $user, $sale, ['reason' => $reason]);

            return $sale->refresh()->load(['items.product', 'payments', 'customer']);
        });
    }

    /** @param array<string, mixed> $data */
    private function resolveOutlet(User $user, array $data): Branch
    {
        $outlet = $this->outlets->current($user);
        $branchId = (int) ($data['branch_id'] ?? $outlet->id);
        $branch = Branch::query()->where('company_id', $user->company_id)->find($branchId);
        if (! $branch || ! $this->outlets->canAccess($user, $branch)) {
            throw ValidationException::withMessages(['branch_id' => 'You are not assigned to this outlet.']);
        }

        return $branch;
    }

    /** @param array<string, mixed> $data */
    private function customer(User $user, array $data): ?Customer
    {
        return isset($data['customer_id']) && $data['customer_id'] !== null && $data['customer_id'] !== ''
            ? Customer::query()->where('company_id', $user->company_id)->findOrFail($data['customer_id'])
            : null;
    }

    /** @param array<string, mixed> $data */
    private function requireRegisterSession(User $user, Branch $branch, array &$data): void
    {
        $settings = $this->settings->settings($user->company_id);
        $hasRegisters = PosRegister::query()->where('company_id', $user->company_id)->where('branch_id', $branch->id)->where('is_active', true)->exists();
        if ($settings->require_open_session && $hasRegisters && empty($data['register_id'])) {
            throw ValidationException::withMessages(['register_id' => 'Select an open POS register before completing this sale.']);
        }
        if (! empty($data['register_id'])) {
            $session = $this->registers->activeSession($user, (int) $data['register_id'], $branch->id);
            $data['register_session_id'] = $session->id;
            $data['receipt_prefix'] = $session->register->receipt_prefix;
        }
    }

    /** @param array<string, mixed> $data */
    private function heldSale(User $user, array $data, Branch $branch): ?PosSale
    {
        if (empty($data['held_sale_id'])) return null;
        $sale = PosSale::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail((int) $data['held_sale_id']);
        $this->assertSaleAccess($sale, $user);
        if ($sale->status !== 'held' || $sale->held_by !== $user->id || $sale->branch_id !== $branch->id) {
            throw ValidationException::withMessages(['held_sale_id' => 'This held bill is no longer available to resume.']);
        }

        return $sale;
    }

    /** @param array<string, mixed> $data */
    private function calculation(User $user, Branch $branch, ?Customer $customer, array $data, bool $lockProducts): array
    {
        $items = [];
        foreach ($data['items'] as $item) {
            $product = Product::query()
                ->with(['category', 'taxRate', 'unit'])
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->where('status', Product::STATUS_ACTIVE)
                ->when($lockProducts, fn ($query) => $query->lockForUpdate())
                ->findOrFail((int) $item['product_id']);
            $items[] = $item + ['product' => $product];
        }

        return $this->totals->calculate($user, $branch, $customer, $items, $data);
    }

    /** @param array<int, array<string, mixed>> $payments @return array{records: array<int, array<string, string>>, paid: string, change: string} */
    private function validatePayments(array $payments, string $total): array
    {
        $totalMinor = $this->minor($total);
        if ($totalMinor === 0 && $payments === []) return ['records' => [], 'paid' => '0.00', 'change' => '0.00'];
        if ($payments === []) throw ValidationException::withMessages(['payments' => 'Record at least one payment before completing this sale.']);

        $paid = 0;
        $nonCash = 0;
        $records = [];
        foreach ($payments as $payment) {
            $amount = $this->minor($payment['amount']);
            $method = (string) $payment['method'];
            $reference = trim((string) ($payment['reference'] ?? ''));
            if (in_array($method, ['card', 'upi', 'bank_transfer'], true) && $reference === '') {
                throw ValidationException::withMessages(['payments' => 'Card, UPI, and bank-transfer payments require a reference.']);
            }
            $paid += $amount;
            if ($method !== 'cash') $nonCash += $amount;
            $records[] = ['method' => $method, 'amount' => $this->decimal($amount), 'reference' => $reference ?: null];
        }
        if ($paid < $totalMinor) throw ValidationException::withMessages(['payments' => 'Payment total must cover the bill total.']);
        if ($nonCash > $totalMinor) throw ValidationException::withMessages(['payments' => 'Only cash may exceed the bill total to return change.']);

        return ['records' => $records, 'paid' => $this->decimal($paid), 'change' => $this->decimal(max(0, $paid - $totalMinor))];
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $calculation @param array{records: array<int, array<string, string>>, paid: string, change: string}|null $payments */
    private function persistSale(User $user, Branch $branch, ?Customer $customer, array $data, array $calculation, string $status, ?array $payments = null, ?PosSale $existing = null): PosSale
    {
        $number = $status === 'completed'
            ? $this->numbers->next($user->company_id, $branch->id, $data['receipt_prefix'] ?? $branch->receipt_prefix)
            : ['number' => $this->numbers->heldReference(), 'financial_year' => null];
        $totals = $calculation['totals'];
        $attributes = [
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'register_id' => $data['register_id'] ?? null,
            'register_session_id' => $data['register_session_id'] ?? null,
            'customer_id' => $customer?->id,
            'customer_name_snapshot' => $customer?->display_name,
            'customer_mobile_snapshot' => $customer?->phone ?: $customer?->whatsapp,
            'sale_number' => $number['number'],
            'receipt_number' => $status === 'completed' ? $number['number'] : null,
            'financial_year' => $number['financial_year'],
            'timezone' => $branch->timezone ?: $user->company?->timezone,
            'place_of_supply_state_code' => $calculation['place_of_supply_state_code'],
            'tax_treatment_snapshot' => $calculation['tax_treatment_snapshot'],
            'offline_uuid' => $data['offline_uuid'] ?? null,
            'offline_reference' => $data['offline_reference'] ?? null,
            'completion_key' => $status === 'completed' ? $data['completion_key'] : null,
            'synced_from_offline' => (bool) ($data['synced_from_offline'] ?? false),
            'offline_created_at' => $data['offline_created_at'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'status' => $status,
            'currency' => $data['currency'] ?? $branch->currency ?? $user->company?->currency ?? 'INR',
            'sale_type' => $data['sale_type'] ?? 'retail',
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $this->sumDecimals($totals['item_discount_total'], $totals['order_discount_total']),
            'item_discount_total' => $totals['item_discount_total'],
            'bill_discount_total' => $totals['order_discount_total'],
            'taxable_amount' => $totals['taxable_amount'],
            'tax_amount' => $totals['tax_amount'],
            'cgst_total' => $totals['cgst_total'],
            'sgst_total' => $totals['sgst_total'],
            'igst_total' => $totals['igst_total'],
            'cess_total' => $totals['cess_total'],
            'rounding_adjustment' => '0.00',
            'total_amount' => $totals['total_amount'],
            'paid_amount' => $payments['paid'] ?? '0.00',
            'change_amount' => $payments['change'] ?? '0.00',
            'balance_due' => $payments ? '0.00' : $totals['total_amount'],
            'notes' => $data['notes'] ?? null,
            'device_type' => $data['device_type'] ?? 'desktop',
            'held_by' => $status === 'held' ? $user->id : null,
            'completed_by' => $status === 'completed' ? $user->id : null,
            'held_at' => $status === 'held' ? now() : null,
            'completed_at' => $status === 'completed' ? now() : null,
            'sold_at' => $status === 'completed' ? now() : null,
        ];
        $sale = $existing ?: new PosSale;
        if ($existing) {
            $sale->items()->delete();
            $sale->payments()->delete();
        }
        $sale->fill($attributes)->save();

        foreach ($calculation['lines'] as $index => $line) {
            $product = $line['product'];
            $sale->items()->create([
                'company_id' => $user->company_id,
                'product_id' => $product->id,
                'product_variant_id' => $product->is_variant ? $product->id : null,
                'category_id' => $product->category_id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'variant_label' => $product->variant_name,
                'hsn_sac' => $product->hsn_code,
                'unit' => $product->unit?->short_code,
                'quantity' => $line['quantity'],
                'unit_price' => $this->decimal($line['unit_price_minor']),
                'gross_amount' => $this->decimal($line['gross_minor']),
                'price_source' => array_key_exists('unit_price', $data['items'][$index]) ? 'manual' : 'product',
                'discount_type' => $data['items'][$index]['discount_type'] ?? 'fixed',
                'discount_value' => $data['items'][$index]['discount_value'] ?? '0',
                'discount_amount' => $this->decimal($line['discount_amount_minor']),
                'taxable_amount' => $this->decimal($line['taxable_minor']),
                'tax_profile_name' => $product->taxRate?->name,
                'tax_rate' => $line['tax_rate'],
                'tax_components' => ['cgst' => $this->decimal($line['cgst_minor']), 'sgst' => $this->decimal($line['sgst_minor']), 'igst' => $this->decimal($line['igst_minor']), 'cess' => $this->decimal($line['cess_minor'])],
                'tax_amount' => $this->decimal($line['tax_minor']),
                'cgst_amount' => $this->decimal($line['cgst_minor']),
                'sgst_amount' => $this->decimal($line['sgst_minor']),
                'igst_amount' => $this->decimal($line['igst_minor']),
                'cess_amount' => $this->decimal($line['cess_minor']),
                'tax_treatment_snapshot' => $line['tax_treatment'],
                'line_total' => $this->decimal($line['line_total_minor']),
                'sort_order' => $index + 1,
            ]);
        }

        return $sale->load('customer');
    }

    /** @param array<string, mixed> $line */
    private function postStock(User $user, PosSale $sale, array $line): void
    {
        $product = $line['product'];
        if (! $product->track_inventory) return;
        $level = StockLevel::query()->where('company_id', $user->company_id)->where('product_id', $product->id)->where('branch_id', $sale->branch_id)->lockForUpdate()->first();
        if (! $level) throw ValidationException::withMessages(['items' => "No saleable stock location is configured for {$product->name}."]);
        $quantity = (float) $line['quantity'];
        $available = (float) $level->quantity_available;
        if (! $product->allow_negative_stock && $available < $quantity) {
            throw ValidationException::withMessages(['items' => "Insufficient stock for {$product->name}."]);
        }
        $before = (float) $level->quantity_on_hand;
        $after = $before - $quantity;
        $level->update(['quantity_on_hand' => $after, 'quantity_available' => max(0, $available - $quantity), 'last_stock_movement_at' => now()]);
        StockMovement::create(['company_id' => $user->company_id, 'branch_id' => $sale->branch_id, 'warehouse_id' => $level->warehouse_id, 'stock_location_id' => $level->stock_location_id, 'product_id' => $product->id, 'movement_type' => 'sale', 'direction' => 'out', 'quantity' => $line['quantity'], 'quantity_before' => $before, 'quantity_after' => $after, 'unit_cost' => $product->cost_price, 'reference_type' => PosSale::class, 'reference_id' => $sale->id, 'reason' => 'POS sale', 'created_by' => $user->id, 'occurred_at' => now()]);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function recordCustomerHistory(Customer $customer, PosSale $sale, array $lines, User $user): void
    {
        $customer->update(['last_purchase_at' => $sale->completed_at, 'total_purchase_amount' => (float) $customer->total_purchase_amount + (float) $sale->total_amount, 'total_orders_count' => (int) $customer->total_orders_count + 1]);
        foreach ($lines as $line) {
            $summary = CustomerProductSummary::firstOrNew(['company_id' => $customer->company_id, 'customer_id' => $customer->id, 'product_id' => $line['product']->id]);
            $summary->fill(['category_id' => $line['product']->category_id, 'purchase_count' => (int) $summary->purchase_count + 1, 'quantity_purchased' => (float) $summary->quantity_purchased + (float) $line['quantity'], 'total_spent' => (float) $summary->total_spent + $line['line_total_minor'] / 100, 'first_purchased_at' => $summary->first_purchased_at ?? $sale->completed_at, 'last_purchased_at' => $sale->completed_at]);
            $summary->save();
        }
        foreach ($lines as $source) foreach ($lines as $related) if ($source['product']->id !== $related['product']->id) {
            $pair = PosProductPairSummary::firstOrNew(['company_id' => $customer->company_id, 'product_id' => $source['product']->id, 'related_product_id' => $related['product']->id]);
            $pair->co_purchase_count = (int) $pair->co_purchase_count + 1;
            $pair->last_purchased_together_at = $sale->completed_at;
            $pair->save();
        }
        CustomerActivityLog::create(['company_id' => $customer->company_id, 'customer_id' => $customer->id, 'activity_type' => 'purchase', 'title' => 'POS sale completed', 'description' => $sale->sale_number, 'reference_type' => PosSale::class, 'reference_id' => $sale->id, 'user_id' => $user->id, 'occurred_at' => $sale->completed_at]);
        $this->insights->calculate($customer->refresh());
    }

    private function dispatch(string $key, User $user, PosSale $sale, array $payload = []): void
    {
        $this->events->dispatch(new PosDomainEvent($key, $user->company_id, $user->id, PosSale::class, $sale->id, $payload + ['sale_number' => $sale->sale_number]));
    }

    private function assertSaleAccess(PosSale $sale, User $user): void
    {
        if ($sale->company_id !== $user->company_id) throw ValidationException::withMessages(['outlet' => 'This sale is not available to your company.']);
        if ($sale->branch_id === null) {
            if (! $this->outlets->hasCompanyWideAccess($user)) throw ValidationException::withMessages(['outlet' => 'Only a company administrator can change a historical sale without an outlet.']);
            return;
        }
        $branch = Branch::query()->where('company_id', $user->company_id)->find($sale->branch_id);
        if (! $branch || ! $this->outlets->canAccess($user, $branch)) throw ValidationException::withMessages(['outlet' => 'You are not assigned to this sale outlet.']);
    }

    private function minor(string $value): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) throw ValidationException::withMessages(['payments' => 'A valid payment amount is required.']);
        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function decimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function sumDecimals(string $first, string $second): string
    {
        return $this->decimal($this->minor($first) + $this->minor($second));
    }
}
