<?php

namespace App\Services\Promotions;

use App\Models\Promotions\PromotionAction;
use App\Models\Promotions\PromotionRule;

class PromotionCalculatorService
{
    /** @param array<int, array<string, mixed>> $items @return array{discount: float, item_discounts: array<int, array<string, mixed>>, free_items: array<int, array<string, mixed>>, quantity_affected: float} */
    public function calculate(PromotionRule $rule, array $items): array
    {
        $result = ['discount' => 0.0, 'item_discounts' => [], 'free_items' => [], 'quantity_affected' => 0.0];
        foreach ($rule->actions as $action) {
            $calculation = match ($action->action_type->value) {
                'percentage_off' => $this->percentage($action, $items),
                'amount_off' => $this->amount($action, $items),
                'free_quantity' => $this->buyGet($action, $items),
                'free_product' => $this->buyGet($action, $items),
                'set_fixed_price', 'bundle_price' => $this->bundle($action, $items),
                default => ['discount' => 0.0, 'item_discounts' => [], 'free_items' => [], 'quantity_affected' => 0.0],
            };
            $result['discount'] += $calculation['discount'];
            $result['item_discounts'] = [...$result['item_discounts'], ...$calculation['item_discounts']];
            $result['free_items'] = [...$result['free_items'], ...$calculation['free_items']];
            $result['quantity_affected'] += $calculation['quantity_affected'];
        }
        $limit = $rule->maximum_discount_amount;
        if ($limit !== null && $result['discount'] > (float) $limit) $result['discount'] = (float) $limit;
        return $result;
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    private function percentage(PromotionAction $action, array $items): array
    {
        $percentage = (float) ($action->discount_percentage ?? 0); $discount = 0.0; $rows = [];
        foreach ($items as $item) { $amount = ((float) $item['quantity'] * (float) $item['unit_price']) * ($percentage / 100); $discount += $amount; $rows[] = $this->row($item, $amount); }
        return $this->capped($discount, $action, $rows, [], $this->quantity($items));
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    private function amount(PromotionAction $action, array $items): array
    {
        $amount = (float) ($action->discount_value ?? 0); $remaining = $amount; $rows = [];
        foreach ($items as $item) { if ($remaining <= 0) break; $line = (float) $item['quantity'] * (float) $item['unit_price']; $discount = min($line, $remaining); $remaining -= $discount; $rows[] = $this->row($item, $discount); }
        return $this->capped($amount - $remaining, $action, $rows, [], $this->quantity($items));
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    private function buyGet(PromotionAction $action, array $items): array
    {
        $buy = max(1, (float) ($action->buy_quantity ?? 1)); $get = max(1, (float) ($action->get_quantity ?? 1)); $discount = 0.0; $rows = []; $free = []; $affected = 0.0;
        $totalQuantity = $this->quantity($items); $freeQuantity = floor($totalQuantity / ($buy + $get)) * $get;
        if ($action->maximum_free_quantity !== null) $freeQuantity = min($freeQuantity, (float) $action->maximum_free_quantity);
        if ($freeQuantity <= 0) return $this->capped(0, $action, [], [], 0);
        if (! $action->applies_to_same_product && $action->freeProduct) {
            $free[] = ['product_id' => $action->free_product_id, 'product_name' => $action->freeProduct->name, 'quantity' => $freeQuantity, 'reason' => 'Free item from promotion'];
            return $this->capped(0, $action, [], $free, $freeQuantity);
        }
        foreach (collect($items)->sortBy('unit_price') as $item) {
            if ($freeQuantity <= 0) break;
            $quantity = min((float) $item['quantity'], $freeQuantity); $amount = $quantity * (float) $item['unit_price']; $discount += $amount; $freeQuantity -= $quantity; $affected += $quantity; $rows[] = $this->row($item, $amount, $quantity);
        }
        return $this->capped($discount, $action, $rows, $free, $affected);
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    private function bundle(PromotionAction $action, array $items): array
    {
        $bundleQuantity = max(1, (int) ($action->buy_quantity ?? 1)); $fixedPrice = (float) ($action->fixed_price ?? 0); $units = [];
        foreach ($items as $item) for ($i = 0; $i < (int) floor((float) $item['quantity']); $i++) $units[] = $item;
        usort($units, fn (array $a, array $b): int => (float) $b['unit_price'] <=> (float) $a['unit_price']);
        $discount = 0.0; $rows = []; $groups = intdiv(count($units), $bundleQuantity);
        for ($group = 0; $group < $groups; $group++) {
            $slice = array_slice($units, $group * $bundleQuantity, $bundleQuantity); $subtotal = array_sum(array_map(fn (array $item): float => (float) $item['unit_price'], $slice));
            $saving = max(0, $subtotal - $fixedPrice); foreach ($slice as $item) $rows[] = $this->row($item, $saving / $bundleQuantity, 1); $discount += $saving;
        }
        return $this->capped($discount, $action, $rows, [], $groups * $bundleQuantity);
    }

    /** @param array<int, array<string, mixed>> $rows @param array<int, array<string, mixed>> $free @return array<string, mixed> */
    private function capped(float $discount, PromotionAction $action, array $rows, array $free, float $quantity): array
    {
        $cap = $action->maximum_discount_amount; return ['discount' => $cap !== null ? min($discount, (float) $cap) : $discount, 'item_discounts' => $rows, 'free_items' => $free, 'quantity_affected' => $quantity];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function row(array $item, float $discount, ?float $quantity = null): array { return ['product_id' => $item['product_id'], 'product_name' => $item['product_name'] ?? ('Product #'.$item['product_id']), 'quantity' => $quantity ?? (float) $item['quantity'], 'discount' => round($discount, 2)]; }
    /** @param array<int, array<string, mixed>> $items */
    private function quantity(array $items): float { return array_sum(array_map(fn (array $item): float => (float) $item['quantity'], $items)); }
}
