<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\PreferredContactMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.contacts.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('company_id', $companyId)],
            'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'preferred_contact_method' => ['required', Rule::enum(PreferredContactMethod::class)],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('crm_tags', 'id')->where('company_id', $companyId)],
        ];
    }
}
