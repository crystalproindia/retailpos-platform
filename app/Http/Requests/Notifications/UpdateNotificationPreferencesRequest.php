<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('notifications.preferences.manage_own');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['nullable', 'array'],
            'preferences.*.database_enabled' => ['nullable', 'boolean'],
            'preferences.*.email_enabled' => ['nullable', 'boolean'],
            'preferences.*.whatsapp_enabled' => ['nullable', 'boolean'],
            'preferences.*.sms_enabled' => ['nullable', 'boolean'],
            'preferences.*.push_enabled' => ['nullable', 'boolean'],
            'preferences.*.quiet_hours_enabled' => ['nullable', 'boolean'],
            'preferences.*.quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'preferences.*.quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'preferences.*.timezone' => ['nullable', 'string', 'max:80'],
        ];
    }
}
