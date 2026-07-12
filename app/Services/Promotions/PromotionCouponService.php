<?php

namespace App\Services\Promotions;

use App\Events\Domain\Promotions\PromotionDomainEvent;
use App\Models\Promotions\PromotionCoupon;
use App\Models\Promotions\PromotionCouponRedemption;
use App\Models\User;
use App\Repositories\Promotions\PromotionCouponRepository;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;

class PromotionCouponService
{
    public function __construct(private readonly PromotionCouponRepository $coupons, private readonly AuditLogger $auditLogger, private readonly DomainEventDispatcher $events) {}

    /** @return array{coupon: ?PromotionCoupon, reason: ?string} */
    public function validate(int $companyId, ?string $code): array
    {
        if (! $code) { return ['coupon' => null, 'reason' => null]; }
        $coupon = $this->coupons->byCode($companyId, $code);
        if (! $coupon) { return ['coupon' => null, 'reason' => 'Coupon code was not found.']; }
        if (! $coupon->is_active) { return ['coupon' => null, 'reason' => 'Coupon code is inactive.']; }
        if ($coupon->start_at?->isFuture() || $coupon->end_at?->isPast()) { return ['coupon' => null, 'reason' => 'Coupon code is outside its valid date range.']; }
        if ($coupon->usage_limit_total !== null && $coupon->used_count >= $coupon->usage_limit_total) { return ['coupon' => null, 'reason' => 'Coupon usage limit has been reached.']; }
        if (! $coupon->rule->is_active || $coupon->rule->status->value !== 'active') { return ['coupon' => null, 'reason' => 'The linked promotion is not active.']; }
        return ['coupon' => $coupon, 'reason' => null];
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): PromotionCoupon
    {
        $coupon = PromotionCoupon::create($data + ['company_id' => $user->company_id, 'code' => strtoupper(trim($data['code']))]);
        $this->auditLogger->record('promotion.coupon.created', $coupon, 'Promotion coupon created');
        $this->dispatch('promotion.coupon.created', $coupon, $user);
        return $coupon;
    }

    /** @param array<string, mixed> $data */
    public function update(PromotionCoupon $coupon, User $user, array $data): PromotionCoupon
    {
        $coupon->update($data + ['code' => strtoupper(trim($data['code']))]);
        $this->auditLogger->record('promotion.coupon.updated', $coupon, 'Promotion coupon updated');
        $this->dispatch('promotion.coupon.updated', $coupon, $user);
        return $coupon->refresh();
    }

    public function toggle(PromotionCoupon $coupon, User $user): PromotionCoupon
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);
        $this->auditLogger->record('promotion.coupon.'.($coupon->is_active ? 'enabled' : 'disabled'), $coupon, 'Promotion coupon status changed');
        return $coupon->refresh();
    }

    public function redeem(PromotionCoupon $coupon, User $user, string $cartReference, float $discountAmount): PromotionCouponRedemption
    {
        $redemption = PromotionCouponRedemption::create(['company_id' => $coupon->company_id, 'promotion_coupon_id' => $coupon->id, 'promotion_rule_id' => $coupon->promotion_rule_id, 'cart_reference' => $cartReference, 'redeemed_by' => $user->id, 'discount_amount' => $discountAmount, 'redeemed_at' => now()]);
        $coupon->increment('used_count');
        $this->dispatch('promotion.coupon.used', $coupon, $user, ['discount_amount' => $discountAmount]);
        return $redemption;
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(string $key, PromotionCoupon $coupon, User $user, array $payload = []): void
    {
        $this->events->dispatch(new PromotionDomainEvent($key, $coupon->company_id, $user->id, PromotionCoupon::class, $coupon->id, $payload + ['coupon_code' => $coupon->code]));
    }
}
