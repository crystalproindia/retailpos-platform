<?php

namespace App\Http\Requests\Promotions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['allow_stacking' => ['nullable', 'boolean'], 'default_priority_strategy' => ['required', Rule::in(['priority_then_benefit'])], 'allow_coupon_with_auto_discount' => ['nullable', 'boolean'], 'max_discount_percentage_per_bill' => ['nullable', 'numeric', 'min:0', 'max:100'], 'max_discount_amount_per_bill' => ['nullable', 'numeric', 'min:0'], 'require_approval_for_promotions' => ['nullable', 'boolean'], 'require_approval_above_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'], 'require_approval_above_discount_amount' => ['nullable', 'numeric', 'min:0'], 'show_discount_breakup_on_bill_future' => ['nullable', 'boolean']];
    }
}
