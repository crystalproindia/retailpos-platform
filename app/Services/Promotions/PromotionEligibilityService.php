<?php

namespace App\Services\Promotions;

use App\Models\Promotions\PromotionRule;

class PromotionEligibilityService
{
    /** @param array<string, mixed> $cart @return array{eligible: bool, reason: ?string, items: array<int, array<string, mixed>>} */
    public function evaluate(PromotionRule $rule, array $cart): array
    {
        if (! $this->matchesScopedTarget($rule->branchTargets, (int) ($cart['branch_id'] ?? 0), 'branch_id')) return $this->rejected('Promotion does not apply to this branch.');
        if (! $this->matchesScopedTarget($rule->channelTargets, (int) ($cart['sales_channel_id'] ?? 0), 'sales_channel_id')) return $this->rejected('Promotion does not apply to this sales channel.');
        if ($rule->minimum_bill_amount !== null && (float) ($cart['bill_subtotal'] ?? 0) < (float) $rule->minimum_bill_amount) return $this->rejected('Cart does not meet the minimum bill amount.');
        if (! $this->matchesConditions($rule, $cart)) return $this->rejected('Cart does not satisfy the configured promotion conditions.');

        $items = array_values(array_filter($cart['items'] ?? [], fn (array $item): bool => $this->matchesItem($rule, $item)));
        if ($this->hasItemTargets($rule) && $items === []) return $this->rejected('Cart has no products that match this promotion target.');
        if ($rule->minimum_quantity !== null && array_sum(array_map(fn (array $item): float => (float) $item['quantity'], $items)) < (float) $rule->minimum_quantity) return $this->rejected('Cart does not meet the minimum quantity.');
        return ['eligible' => true, 'reason' => null, 'items' => $items ?: ($cart['items'] ?? [])];
    }

    /** @param iterable<int, mixed> $targets */
    private function matchesScopedTarget(iterable $targets, int $value, string $field): bool
    {
        $targets = collect($targets); $included = $targets->where('include_or_exclude', 'include');
        if ($targets->where('include_or_exclude', 'exclude')->contains($field, $value)) return false;
        return $included->isEmpty() || $included->contains($field, $value);
    }

    /** @param array<string, mixed> $item */
    private function matchesItem(PromotionRule $rule, array $item): bool
    {
        $checks = [
            [$rule->productTargets, 'product_id', (int) ($item['product_id'] ?? 0)], [$rule->variantTargets, 'product_id', (int) ($item['product_id'] ?? 0)],
            [$rule->categoryTargets, 'category_id', (int) ($item['category_id'] ?? 0)], [$rule->brandTargets, 'brand_id', (int) ($item['brand_id'] ?? 0)],
        ];
        $hasIncluded = false;
        foreach ($checks as [$targets, $field, $value]) {
            $targets = collect($targets);
            if ($targets->where('include_or_exclude', 'exclude')->contains($field, $value)) return false;
            if ($targets->where('include_or_exclude', 'include')->isNotEmpty()) $hasIncluded = $hasIncluded || $targets->where('include_or_exclude', 'include')->contains($field, $value);
        }
        return ! $this->hasItemTargets($rule) || $hasIncluded;
    }

    /** @param array<string, mixed> $cart */
    private function matchesConditions(PromotionRule $rule, array $cart): bool
    {
        foreach ($rule->conditions as $condition) {
            $actual = match ($condition->condition_type->value) {
                'bill_amount' => (float) ($cart['bill_subtotal'] ?? 0), 'quantity' => array_sum(array_map(fn (array $item): float => (float) $item['quantity'], $cart['items'] ?? [])),
                'branch' => (int) ($cart['branch_id'] ?? 0), 'sales_channel' => (int) ($cart['sales_channel_id'] ?? 0), default => null,
            };
            if ($actual === null) continue;
            $value = is_numeric($condition->value) ? (float) $condition->value : $condition->value;
            if (! $this->compare($actual, $condition->operator->value, $value, $condition->value_json)) return false;
        }
        return true;
    }

    /** @param mixed $actual @param mixed $value @param array<int, mixed>|null $range */
    private function compare(mixed $actual, string $operator, mixed $value, ?array $range): bool
    {
        return match ($operator) {
            'equals' => $actual == $value, 'not_equals' => $actual != $value, 'greater_than' => $actual > $value, 'greater_than_or_equal' => $actual >= $value,
            'less_than' => $actual < $value, 'less_than_or_equal' => $actual <= $value, 'in' => in_array($actual, $range ?? [], true), 'not_in' => ! in_array($actual, $range ?? [], true),
            'between' => $actual >= ($range[0] ?? $value) && $actual <= ($range[1] ?? $value), default => false,
        };
    }

    private function hasItemTargets(PromotionRule $rule): bool { return $rule->productTargets->isNotEmpty() || $rule->variantTargets->isNotEmpty() || $rule->categoryTargets->isNotEmpty() || $rule->brandTargets->isNotEmpty(); }
    /** @return array{eligible: false, reason: string, items: array<int, array<string, mixed>>} */
    private function rejected(string $reason): array { return ['eligible' => false, 'reason' => $reason, 'items' => []]; }
}
