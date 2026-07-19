<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->isMethod('PUT') ? 'sales.invoices.update' : 'sales.invoices.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_company' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:5000'],
            'billing_country' => ['nullable', 'string', 'max:100'],
            'customer_tax_number' => ['nullable', 'string', 'max:100'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'tax_classification' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'terms_conditions' => ['nullable', 'string', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'adjustment_total' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:32'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
