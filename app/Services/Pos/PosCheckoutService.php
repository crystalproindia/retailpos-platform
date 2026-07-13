<?php

namespace App\Services\Pos;

use App\Events\Domain\Pos\PosDomainEvent;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerActivityLog;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Pos\CustomerProductSummary;
use App\Models\Pos\PosProductPairSummary;
use App\Models\Pos\PosSale;
use App\Models\User;
use App\Repositories\Pos\PosCatalogRepository;
use App\Services\AuditLogger;
use App\Services\Customers\CustomerInsightService;
use App\Services\Events\DomainEventDispatcher;
use App\Services\Promotions\PromotionRuleEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosCheckoutService
{
    public function __construct(
        private readonly PosCatalogRepository $catalog,
        private readonly PosNumberService $numbers,
        private readonly PromotionRuleEngine $promotions,
        private readonly CustomerInsightService $insights,
        private readonly AuditLogger $audit,
        private readonly DomainEventDispatcher $events,
    ) {}

    /** @param array<string, mixed> $data */
    public function hold(User $user, array $data): PosSale
    {
        return DB::transaction(function () use ($user, $data): PosSale {
            [$lines, $totals] = $this->linesAndTotals($user, $data);
            $sale = $this->persistSale($user, $data, $lines, $totals, 'held');
            $this->audit->record('pos.sale.held', $sale, 'POS bill held');
            $this->dispatch('pos.sale.held', $user, $sale);

            return $sale->load('items.product');
        });
    }

    /** @param array<string, mixed> $data */
    public function complete(User $user, array $data): PosSale
    {
        return DB::transaction(function () use ($user, $data): PosSale {
            [$lines, $totals] = $this->linesAndTotals($user, $data);
            $paid = round(collect($data['payments'] ?? [])->sum(fn (array $payment) => (float) $payment['amount']), 2);
            if ($paid < $totals['total']) throw ValidationException::withMessages(['payments' => 'Payment total must cover the bill total.']);

            $sale = $this->persistSale($user, $data, $lines, $totals, 'completed', $paid);
            foreach ($lines as $line) $this->postStock($user, $sale, $line);
            foreach ($data['payments'] ?? [] as $payment) $sale->payments()->create(['company_id' => $user->company_id, 'payment_method' => $payment['method'], 'amount' => $payment['amount'], 'reference' => $payment['reference'] ?? null, 'paid_at' => now(), 'created_by' => $user->id]);
            if ($sale->customer) $this->recordCustomerHistory($sale->customer, $sale, $lines, $user);
            $this->audit->record('pos.sale.completed', $sale, 'POS sale completed');
            $this->dispatch('pos.sale.completed', $user, $sale, ['customer_id' => $sale->customer_id, 'total' => $sale->total_amount]);

            return $sale->load(['items.product', 'payments', 'customer.groups.group', 'customer.loyaltyAccount', 'customer.insight']);
        });
    }

    /** @param array<string, mixed> $data @return array{0: array<int, array<string, mixed>>, 1: array<string, float>} */
    private function linesAndTotals(User $user, array $data): array
    {
        $lines = [];
        foreach ($data['items'] as $item) {
            $product = $this->catalog->findSaleable($user->company_id, (int) $item['product_id']);
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) ($item['unit_price'] ?? $product->selling_price);
            $lines[] = ['product' => $product, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'line_total' => round($quantity * $unitPrice, 2)];
        }
        $subtotal = round(collect($lines)->sum('line_total'), 2);
        $cart = ['branch_id' => $data['branch_id'] ?? $user->branch_id, 'customer_id' => $data['customer_id'] ?? null, 'coupon_code' => $data['coupon_code'] ?? null, 'bill_subtotal' => $subtotal, 'items' => collect($lines)->map(fn (array $line) => ['product_id' => $line['product']->id, 'product_name' => $line['product']->name, 'category_id' => $line['product']->category_id, 'brand_id' => $line['product']->brand_id, 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price']])->all()];
        $promotion = $this->promotions->evaluate($user->company_id, $cart);
        $manual = max(0, (float) ($data['manual_discount_amount'] ?? 0));
        $discount = min($subtotal, round($manual + $promotion['total_discount'], 2));

        return [$lines, ['subtotal' => $subtotal, 'discount' => $discount, 'tax' => 0, 'total' => round($subtotal - $discount, 2)]];
    }

    /** @param array<string, mixed> $data @param array<int, array<string, mixed>> $lines @param array<string, float> $totals */
    private function persistSale(User $user, array $data, array $lines, array $totals, string $status, float $paid = 0): PosSale
    {
        $customer = isset($data['customer_id']) ? Customer::query()->where('company_id', $user->company_id)->findOrFail($data['customer_id']) : null;
        $sale = PosSale::create(['company_id' => $user->company_id, 'branch_id' => $data['branch_id'] ?? $user->branch_id, 'customer_id' => $customer?->id, 'sale_number' => $this->numbers->next($user->company_id), 'offline_uuid' => $data['offline_uuid'] ?? null, 'offline_reference' => $data['offline_reference'] ?? null, 'synced_from_offline' => (bool) ($data['synced_from_offline'] ?? false), 'offline_created_at' => $data['offline_created_at'] ?? null, 'device_id' => $data['device_id'] ?? null, 'status' => $status, 'subtotal' => $totals['subtotal'], 'discount_amount' => $totals['discount'], 'tax_amount' => $totals['tax'], 'total_amount' => $totals['total'], 'paid_amount' => $paid, 'change_amount' => max(0, round($paid - $totals['total'], 2)), 'notes' => $data['notes'] ?? null, 'device_type' => $data['device_type'] ?? 'desktop', 'held_by' => $status === 'held' ? $user->id : null, 'completed_by' => $status === 'completed' ? $user->id : null, 'held_at' => $status === 'held' ? now() : null, 'completed_at' => $status === 'completed' ? now() : null]);
        foreach ($lines as $line) $sale->items()->create(['company_id' => $user->company_id, 'product_id' => $line['product']->id, 'category_id' => $line['product']->category_id, 'product_name' => $line['product']->name, 'sku' => $line['product']->sku, 'barcode' => $line['product']->barcode, 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'line_total' => $line['line_total']]);

        return $sale->load('customer');
    }

    /** @param array<string, mixed> $line */
    private function postStock(User $user, PosSale $sale, array $line): void
    {
        $product = $line['product'];
        if (! $product->track_inventory) return;
        $level = StockLevel::query()->where('company_id', $user->company_id)->where('product_id', $product->id)->when($sale->branch_id, fn ($query) => $query->where('branch_id', $sale->branch_id))->lockForUpdate()->first();
        $available = (float) ($level?->quantity_available ?? 0);
        if (! $product->allow_negative_stock && $available < $line['quantity']) throw ValidationException::withMessages(['items' => "Insufficient stock for {$product->name}."]);
        if (! $level) throw ValidationException::withMessages(['items' => "No saleable stock location is configured for {$product->name}."]);
        $before = (float) $level->quantity_on_hand;
        $after = $before - $line['quantity'];
        $level->update(['quantity_on_hand' => $after, 'quantity_available' => max(0, (float) $level->quantity_available - $line['quantity']), 'last_stock_movement_at' => now()]);
        StockMovement::create(['company_id' => $user->company_id, 'branch_id' => $sale->branch_id, 'warehouse_id' => $level->warehouse_id, 'stock_location_id' => $level->stock_location_id, 'product_id' => $product->id, 'movement_type' => 'sale', 'direction' => 'out', 'quantity' => $line['quantity'], 'quantity_before' => $before, 'quantity_after' => $after, 'unit_cost' => $product->cost_price, 'reference_type' => PosSale::class, 'reference_id' => $sale->id, 'reason' => 'POS sale', 'created_by' => $user->id, 'occurred_at' => now()]);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function recordCustomerHistory(Customer $customer, PosSale $sale, array $lines, User $user): void
    {
        $customer->update(['last_purchase_at' => $sale->completed_at, 'total_purchase_amount' => (float) $customer->total_purchase_amount + (float) $sale->total_amount, 'total_orders_count' => (int) $customer->total_orders_count + 1]);
        foreach ($lines as $line) {
            $summary = CustomerProductSummary::firstOrNew(['company_id' => $customer->company_id, 'customer_id' => $customer->id, 'product_id' => $line['product']->id]);
            $summary->fill(['category_id' => $line['product']->category_id, 'purchase_count' => (int) $summary->purchase_count + 1, 'quantity_purchased' => (float) $summary->quantity_purchased + $line['quantity'], 'total_spent' => (float) $summary->total_spent + $line['line_total'], 'first_purchased_at' => $summary->first_purchased_at ?? $sale->completed_at, 'last_purchased_at' => $sale->completed_at]);
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

    /** @param array<string, mixed> $payload */
    private function dispatch(string $key, User $user, PosSale $sale, array $payload = []): void
    {
        $this->events->dispatch(new PosDomainEvent($key, $user->company_id, $user->id, PosSale::class, $sale->id, $payload + ['sale_number' => $sale->sale_number]));
    }
}
