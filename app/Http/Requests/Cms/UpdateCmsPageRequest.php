<?php

namespace App\Http\Requests\Cms;

use App\Models\Cms\CmsPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsPageRequest extends FormRequest
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
        $page = $this->route('page');
        $pageId = $page instanceof CmsPage ? $page->id : $page;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cms_pages', 'slug')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($pageId),
            ],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'hero_content' => ['nullable', 'string'],
            'body_content' => ['nullable', 'string'],
            'featured_image_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'status' => ['required', Rule::in([CmsPage::STATUS_DRAFT, CmsPage::STATUS_PUBLISHED, CmsPage::STATUS_SCHEDULED])],
            'scheduled_for' => ['nullable', 'date', 'required_if:status,scheduled'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'og_image_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'og_type' => ['nullable', 'string', 'max:100'],
            'twitter_title' => ['nullable', 'string', 'max:255'],
            'twitter_description' => ['nullable', 'string'],
            'twitter_image_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'twitter_card' => ['nullable', 'string', 'max:100'],
        ];
    }
}
