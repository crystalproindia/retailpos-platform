<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkLeadActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.leads.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'action' => ['required', Rule::in(['status', 'assign'])],
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer', Rule::exists('crm_leads', 'id')->where('company_id', $companyId)],
            'status_id' => ['required_if:action,status', 'nullable', Rule::exists('crm_lead_statuses', 'id')->where('company_id', $companyId)],
            'assigned_user_id' => ['required_if:action,assign', 'nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
        ];
    }
}
