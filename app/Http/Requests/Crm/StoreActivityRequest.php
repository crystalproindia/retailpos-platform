<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.activities.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'crm_lead_id' => ['nullable', Rule::exists('crm_leads', 'id')->where('company_id', $companyId)],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('company_id', $companyId)],
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('company_id', $companyId)],
            'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'type' => ['required', Rule::enum(ActivityType::class)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'priority' => ['required', Rule::enum(LeadPriority::class)],
        ];
    }
}
