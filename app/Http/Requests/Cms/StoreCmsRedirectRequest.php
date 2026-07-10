<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsRedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_url' => ['required', 'string', 'max:255'],
            'target_url' => ['required', 'string', 'max:255'],
            'status_code' => ['required', 'integer', Rule::in([301, 302, 307, 308])],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
