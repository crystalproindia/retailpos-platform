<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StartCrmOnboardingRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('crm.onboarding.create'); }
    public function rules(): array { return ['title' => ['nullable', 'string', 'max:255'], 'priority' => ['nullable', 'in:low,normal,high,urgent'], 'implementation_owner_id' => ['nullable', 'integer'], 'target_go_live_date' => ['nullable', 'date', 'after_or_equal:today']]; }
}
