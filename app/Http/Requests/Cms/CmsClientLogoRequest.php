<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsClientLogoRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['name' => ['required', 'string', 'max:255'], 'logo_media_id' => ['nullable', 'integer', 'exists:cms_media,id'], 'website_url' => ['nullable', 'url', 'max:255'], 'industry' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'], 'short_description' => ['nullable', 'string'], 'display_style' => ['required', Rule::in(['color', 'grayscale', 'monochrome'])], 'is_featured' => ['nullable', 'boolean'], 'show_on_homepage' => ['nullable', 'boolean'], 'show_on_case_studies' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000']]; }
}
