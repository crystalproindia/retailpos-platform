<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmOnboardingTaskRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('crm.onboarding.complete_task'); }
    public function rules(): array { return ['status' => ['required', Rule::in(['pending','in_progress','blocked','completed','skipped'])], 'reason' => ['nullable', 'string', 'max:2000'], 'assigned_to' => ['nullable', 'integer'], 'due_date' => ['nullable', 'date']]; }
}
