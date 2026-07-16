<?php

namespace App\Http\Requests\Cms;

use App\Models\Cms\CmsPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsMarketingPageRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('cms.pages.manage'); }

    public function rules(): array
    {
        $pageId = $this->route('page');
        $pageId = $pageId instanceof CmsPage ? $pageId->id : $pageId;

        return [
            'route_path' => ['required', 'string', 'max:500', 'regex:/^\//', Rule::unique('cms_pages', 'route_path')->where('company_id', $this->user()->company_id)->ignore($pageId)],
            'title' => ['required', 'string', 'max:255'],
            'h1' => ['nullable', 'string', 'max:255'],
            'page_type' => ['required', Rule::in(['seo', 'landing', 'product', 'industry', 'module', 'solution', 'location', 'comparison'])],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'hero_content' => ['nullable', 'string'],
            'intro_content' => ['nullable', 'string'],
            'body_content' => ['nullable', 'string'],
            'footer_seo_content' => ['nullable', 'string'],
            'primary_cta_label' => ['nullable', 'string', 'max:255'],
            'primary_cta_url' => ['nullable', 'string', 'max:500'],
            'secondary_cta_label' => ['nullable', 'string', 'max:255'],
            'secondary_cta_url' => ['nullable', 'string', 'max:500'],
            'content_sections' => ['nullable', 'json'],
            'faq_items' => ['nullable', 'json'],
            'related_product_keys' => ['nullable', 'json'],
            'related_industry_keys' => ['nullable', 'json'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'og_image_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'og_type' => ['nullable', 'string', 'max:100'],
            'twitter_title' => ['nullable', 'string', 'max:255'],
            'twitter_description' => ['nullable', 'string'],
            'twitter_image_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'twitter_card' => ['nullable', 'string', 'max:100'],
            'schema_json' => ['nullable', 'json'],
            'robots_index' => ['required', 'boolean'],
            'robots_follow' => ['required', 'boolean'],
            'include_in_sitemap' => ['required', 'boolean'],
            'sitemap_priority' => ['nullable', 'numeric', 'between:0,1'],
            'sitemap_changefreq' => ['nullable', Rule::in(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])],
            'status' => ['required', Rule::in([CmsPage::STATUS_DRAFT, CmsPage::STATUS_PUBLISHED, CmsPage::STATUS_SCHEDULED, CmsPage::STATUS_ARCHIVED])],
            'scheduled_for' => ['nullable', 'required_if:status,'.CmsPage::STATUS_SCHEDULED, 'date', 'after:now'],
        ];
    }
}
