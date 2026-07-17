<?php

namespace App\Http\Requests\Cms;

use App\Rules\SafeCmsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsContentSectionRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('cms.content.update'); }
    public function rules(): array
    {
        return [
            'section_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/'],
            'section_type' => ['required', Rule::in(array_keys(config('cms-content.section_types')))],
            'title' => ['nullable', 'string', 'max:255'], 'subtitle' => ['nullable', 'string', 'max:500'], 'eyebrow' => ['nullable', 'string', 'max:120'], 'body' => ['nullable', 'string', 'max:10000'],
            'image_url' => ['nullable', 'max:1000', new SafeCmsUrl], 'primary_cta_label' => ['nullable', 'string', 'max:120'], 'primary_cta_url' => ['nullable', 'max:1000', new SafeCmsUrl], 'secondary_cta_label' => ['nullable', 'string', 'max:120'], 'secondary_cta_url' => ['nullable', 'max:1000', new SafeCmsUrl],
            'items' => ['nullable', 'array', 'max:20'], 'items.*.title' => ['nullable', 'string', 'max:255'], 'items.*.description' => ['nullable', 'string', 'max:2000'], 'items.*.url' => ['nullable', 'max:1000', new SafeCmsUrl], 'items.*.icon_key' => ['nullable', 'string', 'max:80'], 'items.*.question' => ['nullable', 'string', 'max:500'], 'items.*.answer' => ['nullable', 'string', 'max:3000'], 'items.*.name' => ['nullable', 'string', 'max:255'], 'items.*.role_company' => ['nullable', 'string', 'max:255'], 'items.*.quote' => ['nullable', 'string', 'max:3000'], 'items.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'], 'items.*.label' => ['nullable', 'string', 'max:255'], 'items.*.value' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
