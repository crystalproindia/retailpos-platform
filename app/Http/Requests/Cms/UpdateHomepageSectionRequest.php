<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHomepageSectionRequest extends FormRequest
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
            'heading' => ['nullable', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'is_enabled' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
