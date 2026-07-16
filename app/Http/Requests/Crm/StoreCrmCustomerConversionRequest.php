<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\CrmCustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmCustomerConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.customers.convert');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'designation' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:5000'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'number_of_stores' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', Rule::enum(CrmCustomerStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
