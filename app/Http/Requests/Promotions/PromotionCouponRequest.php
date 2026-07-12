<?php

namespace App\Http\Requests\Promotions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionCouponRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $company = $this->user()->company_id;
        return ['promotion_rule_id' => ['required', Rule::exists('promotion_rules', 'id')->where('company_id', $company)], 'code' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string'], 'usage_limit_total' => ['nullable', 'integer', 'min:1'], 'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'], 'start_at' => ['nullable', 'date'], 'end_at' => ['nullable', 'date', 'after_or_equal:start_at'], 'is_active' => ['nullable', 'boolean']];
    }
}
