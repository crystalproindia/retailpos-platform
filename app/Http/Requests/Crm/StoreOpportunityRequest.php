<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('sales.opportunities.create'); }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'stage' => ['required', Rule::in(['qualified', 'demo_completed', 'proposal_required', 'quotation_sent', 'negotiation'])],
            'expected_value' => ['required', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'probability_percentage' => ['required', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
        ];
    }
}
