<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveOpportunityRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('sales.opportunities.update'); }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::in(['qualified', 'demo_completed', 'proposal_required', 'quotation_sent', 'negotiation', 'won', 'lost'])],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:stage,lost'],
        ];
    }
}
