<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmOnboardingRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('crm.onboarding.update'); }
    public function rules(): array { return ['title' => ['required', 'string', 'max:255'], 'status' => ['required', Rule::in(['not_started','in_progress','waiting_for_customer','waiting_for_team','training_pending','go_live_ready','live','on_hold','cancelled'])], 'priority' => ['required', Rule::in(['low','normal','high','urgent'])], 'assigned_to' => ['nullable', 'integer'], 'implementation_owner_id' => ['nullable', 'integer'], 'start_date' => ['nullable', 'date'], 'target_go_live_date' => ['nullable', 'date'], 'customer_contact_name' => ['nullable', 'string', 'max:255'], 'customer_contact_phone' => ['nullable', 'string', 'max:100'], 'customer_contact_email' => ['nullable', 'email', 'max:255'], 'business_name' => ['nullable', 'string', 'max:255'], 'store_count' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string'], 'internal_remarks' => ['nullable', 'string']]; }
}
