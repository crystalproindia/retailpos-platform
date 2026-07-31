<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\LeadStageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.settings.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'stage_type' => ['required', Rule::enum(LeadStageType::class)],
            'tone' => ['required', Rule::in(['neutral', 'info', 'success', 'warning', 'danger'])],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'probability' => ['required', 'integer', 'between:0,100'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
