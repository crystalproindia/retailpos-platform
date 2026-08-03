<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'register_id' => ['nullable', Rule::exists('pos_registers', 'id')->where('company_id', $companyId)],
            'currency' => ['nullable', 'string', 'size:3'],
            'sale_type' => ['nullable', Rule::in(['retail', 'wholesale'])],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'held_sale_id' => ['nullable', Rule::exists('pos_sales', 'id')->where('company_id', $companyId)],
            'completion_key' => ['nullable', 'uuid'],
            'coupon_code' => ['nullable', 'string', 'max:80'],
            'manual_discount_amount' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'manual_discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'place_of_supply_state_code' => ['nullable', 'regex:/^[0-9]{2}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'device_type' => ['nullable', Rule::in(['desktop', 'mobile', 'tablet'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/', 'not_in:0,0.0,0.00,0.000'],
            'items.*.unit_price' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'items.*.discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_value' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', Rule::in(['cash', 'card', 'upi', 'bank_transfer', 'other'])],
            'payments.*.amount' => ['required_with:payments', 'regex:/^\d+(?:\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'payments.*.metadata' => ['nullable', 'array'],
        ];
    }
}
