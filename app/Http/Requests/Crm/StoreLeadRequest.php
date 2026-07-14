<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\LeadPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.leads.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->leadRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function leadRules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('company_id', $companyId)],
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('company_id', $companyId)],
            'source_id' => ['nullable', Rule::exists('crm_lead_sources', 'id')->where('company_id', $companyId)],
            'status_id' => ['required', Rule::exists('crm_lead_statuses', 'id')->where('company_id', $companyId)],
            'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'title' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'interested_modules' => ['nullable', 'array'],
            'interested_modules.*' => ['string', 'max:80'],
            'expected_value' => ['nullable', 'numeric', 'min:0'],
            'expected_timeline' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'priority' => ['required', Rule::enum(LeadPriority::class)],
            'lead_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'next_follow_up_at' => ['nullable', 'date'],
            'last_contacted_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('crm_tags', 'id')->where('company_id', $companyId)],
        ];
    }
}
