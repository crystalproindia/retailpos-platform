<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.pipeline.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status_id' => ['required', Rule::exists('crm_lead_statuses', 'id')->where('company_id', $this->user()?->company_id)->where('is_active', true)],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'lost_note' => ['nullable', 'string', 'max:2000'],
            'stage_transition_key' => ['nullable', 'uuid'],
        ];
    }
}
