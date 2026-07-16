<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.quotations.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->quotationRules();
    }

    /** @return array<string, mixed> */
    protected function quotationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:5000'],
            'currency' => ['required', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'terms_conditions' => ['nullable', 'string', 'max:15000'],
            'internal_remarks' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
