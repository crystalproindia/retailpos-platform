<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCmsFooterRequest extends FormRequest
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
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'business_hours' => ['nullable', 'string'],
            'google_map_url' => ['nullable', 'string', 'max:255'],
            'copyright_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
