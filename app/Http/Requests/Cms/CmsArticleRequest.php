<?php

namespace App\Http\Requests\Cms;

use App\Models\Cms\CmsArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsArticleRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('cms.pages.manage'); }

    public function rules(): array
    {
        $article = $this->route('article');
        $articleId = $article instanceof CmsArticle ? $article->id : $article;

        return [
            'cover_image_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cms_articles', 'slug')->where('company_id', $this->user()->company_id)->ignore($articleId)],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'json'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'schema_json' => ['nullable', 'json'],
            'status' => ['required', Rule::in([CmsArticle::STATUS_DRAFT, CmsArticle::STATUS_PUBLISHED, CmsArticle::STATUS_ARCHIVED])],
            'include_in_sitemap' => ['required', 'boolean'],
            'sitemap_priority' => ['nullable', 'numeric', 'between:0,1'],
            'sitemap_changefreq' => ['nullable', Rule::in(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])],
        ];
    }
}
