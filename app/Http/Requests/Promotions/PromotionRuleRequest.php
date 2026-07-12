<?php

namespace App\Http\Requests\Promotions;

use App\Enums\Promotions\DiscountType;
use App\Enums\Promotions\PromotionActionType;
use App\Enums\Promotions\PromotionConditionType;
use App\Enums\Promotions\PromotionOperator;
use App\Enums\Promotions\PromotionStatus;
use App\Enums\Promotions\PromotionTargetMode;
use App\Enums\Promotions\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $company = $this->user()->company_id;
        $target = ['nullable', 'array'];
        return [
            'campaign_id' => ['nullable', Rule::exists('promotion_campaigns', 'id')->where('company_id', $company)], 'name' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'promotion_type' => ['required', Rule::in(array_column(PromotionType::cases(), 'value'))], 'discount_type' => ['nullable', Rule::in(array_column(DiscountType::cases(), 'value'))], 'status' => ['required', Rule::in(array_column(PromotionStatus::cases(), 'value'))],
            'priority' => ['required', 'integer', 'min:0', 'max:10000'], 'stackable' => ['nullable', 'boolean'], 'exclusive' => ['nullable', 'boolean'], 'requires_coupon' => ['nullable', 'boolean'], 'auto_apply' => ['nullable', 'boolean'],
            'start_at' => ['nullable', 'date'], 'end_at' => ['nullable', 'date', 'after_or_equal:start_at'], 'usage_limit_total' => ['nullable', 'integer', 'min:1'], 'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'], 'usage_limit_per_day' => ['nullable', 'integer', 'min:1'],
            'minimum_bill_amount' => ['nullable', 'numeric', 'min:0'], 'minimum_quantity' => ['nullable', 'numeric', 'gt:0'], 'maximum_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'actions' => ['required', 'array', 'min:1'], 'actions.*.action_type' => ['required', Rule::in(array_column(PromotionActionType::cases(), 'value'))], 'actions.*.discount_value' => ['nullable', 'numeric', 'min:0'], 'actions.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'], 'actions.*.fixed_price' => ['nullable', 'numeric', 'min:0'], 'actions.*.buy_quantity' => ['nullable', 'numeric', 'gt:0'], 'actions.*.get_quantity' => ['nullable', 'numeric', 'gt:0'], 'actions.*.free_product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $company)], 'actions.*.applies_to_same_product' => ['nullable', 'boolean'], 'actions.*.maximum_free_quantity' => ['nullable', 'numeric', 'gt:0'], 'actions.*.maximum_discount_amount' => ['nullable', 'numeric', 'min:0'], 'actions.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'conditions' => $target, 'conditions.*.condition_type' => [Rule::in(array_column(PromotionConditionType::cases(), 'value'))], 'conditions.*.operator' => [Rule::in(array_column(PromotionOperator::cases(), 'value'))], 'conditions.*.value' => ['nullable', 'string'], 'conditions.*.value_json' => ['nullable', 'array'], 'conditions.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'product_targets' => $target, 'product_targets.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $company)], 'product_targets.*.include_or_exclude' => [Rule::in(array_column(PromotionTargetMode::cases(), 'value'))],
            'variant_targets' => $target, 'variant_targets.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $company)], 'variant_targets.*.include_or_exclude' => [Rule::in(array_column(PromotionTargetMode::cases(), 'value'))],
            'category_targets' => $target, 'category_targets.*.category_id' => ['nullable', Rule::exists('inventory_categories', 'id')->where('company_id', $company)], 'category_targets.*.include_or_exclude' => [Rule::in(array_column(PromotionTargetMode::cases(), 'value'))],
            'brand_targets' => $target, 'brand_targets.*.brand_id' => ['nullable', Rule::exists('inventory_brands', 'id')->where('company_id', $company)], 'brand_targets.*.include_or_exclude' => [Rule::in(array_column(PromotionTargetMode::cases(), 'value'))],
            'branch_targets' => $target, 'branch_targets.*.branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $company)], 'branch_targets.*.include_or_exclude' => [Rule::in(array_column(PromotionTargetMode::cases(), 'value'))],
            'channel_targets' => $target, 'channel_targets.*.sales_channel_id' => ['nullable', Rule::exists('sales_channels', 'id')->where('company_id', $company)], 'channel_targets.*.include_or_exclude' => [Rule::in(array_column(PromotionTargetMode::cases(), 'value'))],
        ];
    }
}
