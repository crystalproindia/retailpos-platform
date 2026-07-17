<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmOnboardingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.onboarding.update');
    }

    public function rules(): array
    {
        return [
            'task_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(['business_details', 'store_setup', 'data_collection', 'user_setup', 'training', 'go_live', 'documentation', 'custom'])],
            'assigned_to' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
            'is_required' => ['nullable', 'boolean'],
        ];
    }
}
