<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsCtaRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'button_text' => ['required', 'string', 'max:255'], 'button_link' => ['required', 'string', 'max:255'], 'secondary_button_text' => ['nullable', 'string', 'max:255'], 'secondary_button_link' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'], 'style' => ['nullable', 'string', 'max:100'], 'is_active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000']]; }
}
