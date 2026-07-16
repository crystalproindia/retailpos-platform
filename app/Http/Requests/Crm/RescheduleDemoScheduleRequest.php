<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\DemoMeetingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RescheduleDemoScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.demos.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'demo_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'timezone' => ['required', 'timezone'],
            'meeting_mode' => ['required', Rule::enum(DemoMeetingMode::class)],
            'meeting_link' => ['nullable', 'url', 'max:2048'],
            'assigned_to' => ['required', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'create_google_meet' => ['nullable', 'boolean'],
        ];
    }
}
