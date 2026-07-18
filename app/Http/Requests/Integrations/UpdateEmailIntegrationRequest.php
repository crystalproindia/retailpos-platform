<?php

namespace App\Http\Requests\Integrations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('integrations.email.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['nullable', 'boolean'],
            'host' => ['nullable', 'string', 'max:255', 'required_if:is_enabled,1'],
            'port' => ['nullable', 'integer', 'between:1,65535', 'required_if:is_enabled,1'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:512'],
            'from_name' => ['nullable', 'string', 'max:120', 'required_if:is_enabled,1'],
            'from_address' => ['nullable', 'email:rfc', 'max:255', 'required_if:is_enabled,1'],
            'reply_to_address' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }
}
