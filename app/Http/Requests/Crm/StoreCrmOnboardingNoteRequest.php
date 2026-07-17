<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrmOnboardingNoteRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('crm.onboarding.update'); }
    public function rules(): array { return ['note' => ['required', 'string', 'max:5000'], 'visibility' => ['required', 'in:internal,customer_safe']]; }
}
