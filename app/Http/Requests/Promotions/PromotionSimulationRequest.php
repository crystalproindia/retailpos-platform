<?php

namespace App\Http\Requests\Promotions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionSimulationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(['items' => collect($this->input('items', []))->filter(fn (array $item): bool => filled($item['product_id'] ?? null))->values()->all()]);
    }
    public function rules(): array
    {
        $company = $this->user()->company_id;
        return ['title' => ['nullable', 'string', 'max:255'], 'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $company)], 'sales_channel_id' => ['nullable', Rule::exists('sales_channels', 'id')->where('company_id', $company)], 'coupon_code' => ['nullable', 'string', 'max:80'], 'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $company)], 'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['required', 'numeric', 'min:0']];
    }
}
