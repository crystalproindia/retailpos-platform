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
            'default_twitter_image_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'default_meta_title' => ['nullable', 'string', 'max:255'],
            'default_meta_description' => ['nullable', 'string'],
            'default_meta_keywords' => ['nullable', 'string'],
            'default_canonical_url' => ['nullable', 'url', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_logo_url' => ['nullable', 'url', 'max:500'],
            'contact_phone_india' => ['nullable', 'string', 'max:100'],
            'contact_phone_singapore' => ['nullable', 'string', 'max:100'],
            'contact_phone_malaysia' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'same_as_social_links' => ['nullable', 'json'],
            'schema_markup' => ['nullable', 'string'],
            'default_schema_organization' => ['nullable', 'json'],
            'robots_txt' => ['nullable', 'string'],
            'robots_default_index' => ['nullable', 'boolean'],
            'robots_default_follow' => ['nullable', 'boolean'],
            'sitemap_enabled' => ['required', 'boolean'],
            'sitemap_url' => ['nullable', 'url', 'max:500'],
            'search_console_verification' => ['nullable', 'string', 'max:255'],
            'google_analytics_id' => ['nullable', 'string', 'max:255'],
            'google_tag_manager_id' => ['nullable', 'string', 'max:255'],
            'facebook_pixel_id' => ['nullable', 'string', 'max:255'],
            'linkedin_insight_tag' => ['nullable', 'string', 'max:255'],
            'microsoft_clarity_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
