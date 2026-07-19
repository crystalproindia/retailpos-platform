<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicQuotationDecisionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'name' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'string', 'max:1000', 'required_if:decision,rejected'],
            'confirm' => ['accepted'],
        ];
    }
}
