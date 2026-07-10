<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.leads.convert');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('company_id', $companyId)],
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('company_id', $companyId)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
