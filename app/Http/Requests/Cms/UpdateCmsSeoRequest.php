<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCmsSeoRequest extends FormRequest
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
            'default_og_image_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'default_meta_title' => ['nullable', 'string', 'max:255'],
            'default_meta_description' => ['nullable', 'string'],
            'default_meta_keywords' => ['nullable', 'string'],
            'default_canonical_url' => ['nullable', 'url', 'max:255'],
            'schema_markup' => ['nullable', 'string'],
            'robots_txt' => ['nullable', 'string'],
            'sitemap_enabled' => ['required', 'boolean'],
            'search_console_verification' => ['nullable', 'string', 'max:255'],
            'google_analytics_id' => ['nullable', 'string', 'max:255'],
            'google_tag_manager_id' => ['nullable', 'string', 'max:255'],
            'facebook_pixel_id' => ['nullable', 'string', 'max:255'],
            'linkedin_insight_tag' => ['nullable', 'string', 'max:255'],
            'microsoft_clarity_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
