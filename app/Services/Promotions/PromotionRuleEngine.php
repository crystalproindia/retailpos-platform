<?php

namespace App\Services\Promotions;

use App\Models\Promotions\PromotionRule;
use App\Repositories\Promotions\PromotionRuleRepository;

class PromotionRuleEngine
{
    public function __construct(private readonly PromotionRuleRepository $rules, private readonly PromotionEligibilityService $eligibility, private readonly PromotionCalculatorService $calculator, private readonly PromotionCouponService $coupons, private readonly PromotionSettingsService $settings) {}

    /** @param array<string, mixed> $cart @return array<string, mixed> */
    public function evaluate(int $companyId, array $cart): array
    {
        $items = $cart['items'] ?? []; $subtotal = (float) ($cart['bill_subtotal'] ?? array_sum(array_map(fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price'], $items)));
        $settings = $this->settings->settings($companyId); $couponCheck = $this->coupons->validate($companyId, $cart['coupon_code'] ?? null); $coupon = $couponCheck['coupon'];
        $eligible = []; $applied = []; $rejected = []; $itemDiscounts = []; $billDiscounts = []; $freeItems = []; $warnings = [];
        if ($couponCheck['reason']) $rejected[] = ['name' => strtoupper((string) ($cart['coupon_code'] ?? 'Coupon')), 'reason' => $couponCheck['reason']];
        $locked = false; $hasCoupon = $coupon !== null;
        foreach ($this->rules->activeForCart($companyId) as $rule) {
            if ($rule->requires_coupon && (! $coupon || $coupon->promotion_rule_id !== $rule->id)) { $rejected[] = $this->rejected($rule, 'A valid coupon is required.'); continue; }
            if ($hasCoupon && ! $settings->allow_coupon_with_auto_discount && $rule->auto_apply && ! $rule->requires_coupon) { $rejected[] = $this->rejected($rule, 'Company settings prevent coupon and auto discounts from stacking.'); continue; }
            if ($locked) { $rejected[] = $this->rejected($rule, 'A higher-priority exclusive or non-stackable offer was applied.'); continue; }
            $check = $this->eligibility->evaluate($rule, $cart);
            if (! $check['eligible']) { $rejected[] = $this->rejected($rule, $check['reason']); continue; }
            $eligible[] = ['id' => $rule->id, 'name' => $rule->name, 'type' => $rule->promotion_type->value];
            $calculation = $this->calculator->calculate($rule, $check['items']);
            if ($calculation['discount'] <= 0 && $calculation['free_items'] === []) { $rejected[] = $this->rejected($rule, 'Promotion conditions are met but cart quantity is insufficient for a benefit.'); continue; }
            $applied[] = ['id' => $rule->id, 'name' => $rule->name, 'type' => $rule->promotion_type->value, 'discount' => round($calculation['discount'], 2)];
            $itemDiscounts = [...$itemDiscounts, ...$calculation['item_discounts']]; $freeItems = [...$freeItems, ...$calculation['free_items']];
            if ($this->isBillRule($rule)) $billDiscounts[] = ['promotion_id' => $rule->id, 'name' => $rule->name, 'discount' => round($calculation['discount'], 2)];
            if (! $settings->allow_stacking || ! $rule->stackable || $rule->exclusive) $locked = true;
        }
        $totalDiscount = array_sum(array_column($applied, 'discount'));
        $maximum = $this->billCap($settings, $subtotal);
        if ($maximum !== null && $totalDiscount > $maximum) { $warnings[] = 'Company maximum discount cap was applied.'; $totalDiscount = $maximum; }
        return ['eligible_promotions' => $eligible, 'applied_promotions' => $applied, 'rejected_promotions' => $rejected, 'item_discounts' => $itemDiscounts, 'bill_discounts' => $billDiscounts, 'free_items' => $freeItems, 'total_before_discount' => round($subtotal, 2), 'total_discount' => round(min($subtotal, $totalDiscount), 2), 'total_after_discount' => round(max(0, $subtotal - $totalDiscount), 2), 'warnings' => $warnings];
    }

    /** @return array<string, mixed> */
    private function rejected(PromotionRule $rule, ?string $reason): array { return ['id' => $rule->id, 'name' => $rule->name, 'reason' => $reason]; }
    private function isBillRule(PromotionRule $rule): bool { return in_array($rule->promotion_type->value, ['minimum_bill_discount', 'fixed_bundle_price', 'bundle_discount'], true); }
    private function billCap(object $settings, float $subtotal): ?float
    {
        $amount = $settings->max_discount_amount_per_bill !== null ? (float) $settings->max_discount_amount_per_bill : null;
        $percentage = $settings->max_discount_percentage_per_bill !== null ? $subtotal * ((float) $settings->max_discount_percentage_per_bill / 100) : null;
        return $amount !== null && $percentage !== null ? min($amount, $percentage) : ($amount ?? $percentage);
    }
}
