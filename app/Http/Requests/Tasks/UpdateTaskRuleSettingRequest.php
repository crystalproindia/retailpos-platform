<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRuleSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tasks.rules.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'threshold_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ];
    }
}
