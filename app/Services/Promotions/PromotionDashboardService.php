<?php

namespace App\Services\Promotions;

use App\Models\Promotions\PromotionCampaign;
use App\Models\Promotions\PromotionCoupon;
use App\Models\Promotions\PromotionRule;
use App\Models\Promotions\PromotionRuleUsage;
use App\Models\Promotions\PromotionSimulation;

class PromotionDashboardService
{
    /** @return array<string, mixed> */
    public function metrics(int $companyId): array
    {
        $rules = PromotionRule::query()->where('company_id', $companyId);
        return [
            'cards' => [
                ['label' => 'Total Promotions', 'value' => (clone $rules)->count(), 'tone' => 'neutral'], ['label' => 'Active', 'value' => (clone $rules)->where('status', 'active')->where('is_active', true)->count(), 'tone' => 'success'],
                ['label' => 'Scheduled', 'value' => (clone $rules)->where('status', 'scheduled')->count(), 'tone' => 'info'], ['label' => 'Draft', 'value' => (clone $rules)->where('status', 'draft')->count(), 'tone' => 'warning'],
                ['label' => 'Coupon Rules', 'value' => (clone $rules)->where('requires_coupon', true)->count(), 'tone' => 'neutral'], ['label' => 'Auto Apply', 'value' => (clone $rules)->where('auto_apply', true)->count(), 'tone' => 'success'],
            ],
            'totalDiscountSimulated' => (float) PromotionSimulation::query()->where('company_id', $companyId)->sum('total_discount'),
            'couponUsage' => (int) PromotionCoupon::query()->where('company_id', $companyId)->sum('used_count'),
            'topActiveOffers' => PromotionRule::query()->where('company_id', $companyId)->where('status', 'active')->where('is_active', true)->orderByDesc('priority')->limit(6)->get(),
            'upcomingOffers' => PromotionRule::query()->where('company_id', $companyId)->where('status', 'scheduled')->orderBy('start_at')->limit(6)->get(),
            'recentOffers' => PromotionRule::query()->with('campaign')->where('company_id', $companyId)->latest()->limit(6)->get(),
            'campaignCount' => PromotionCampaign::query()->where('company_id', $companyId)->count(),
        ];
    }
}
