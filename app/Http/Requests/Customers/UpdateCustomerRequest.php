<?php
namespace App\Http\Requests\Customers;

use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends StoreCustomerRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['email'] = ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->where('company_id', $this->user()?->company_id)->ignore($this->route('customer'))];

        return $rules;
    }
}
