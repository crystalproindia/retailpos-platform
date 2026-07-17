<?php

namespace App\Services\Cms;

use App\Models\Company;
use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsContentPage;
use App\Models\Cms\CmsContentSection;
use App\Models\Cms\CmsFooterBlock;
use App\Models\Cms\CmsMedia;
use App\Models\Cms\CmsNavigationItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsSeoSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PublicCmsService
{
    public function seoPage(string $path): ?array
    {
        return $this->remember("seo-page:{$path}", function (int $companyId) use ($path): ?array {
            $page = CmsPage::query()->with(['seo.ogImage', 'seo.twitterImage'])->where('company_id', $companyId)->where('route_path', $this->path($path))->where('status', CmsPage::STATUS_PUBLISHED)->first();
            return $page ? $this->page($page) : null;
        });
    }

    public function landing(string $slug): ?array
    {
        return $this->remember("landing:{$slug}", function (int $companyId) use ($slug): ?array {
            $page = CmsPage::query()->with(['seo.ogImage', 'seo.twitterImage'])->where('company_id', $companyId)->where('slug', $slug)->whereIn('page_type', ['landing', 'product', 'industry', 'module', 'solution', 'location', 'comparison'])->where('status', CmsPage::STATUS_PUBLISHED)->first();
            return $page ? $this->page($page) : null;
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function articles(): array
    {
        return $this->remember('articles', fn (int $companyId) => CmsArticle::query()->with('coverImage')->where('company_id', $companyId)->where('status', CmsArticle::STATUS_PUBLISHED)->whereNotNull('published_at')->latest('published_at')->get()->map(fn (CmsArticle $article) => $this->articlePayload($article))->all());
    }

    public function article(string $slug): ?array
    {
        return $this->remember("article:{$slug}", function (int $companyId) use ($slug): ?array {
            $article = CmsArticle::query()->with('coverImage')->where('company_id', $companyId)->where('slug', $slug)->where('status', CmsArticle::STATUS_PUBLISHED)->whereNotNull('published_at')->first();
            return $article ? $this->articlePayload($article, true) : null;
        });
    }

    public function settings(): array
    {
        return $this->remember('settings', function (int $companyId): array {
            $settings = CmsSeoSetting::query()->with(['defaultOgImage', 'defaultTwitterImage'])->where('company_id', $companyId)->first();
            if (! $settings) return [];
            return [
                'default_site_title' => $settings->default_meta_title, 'default_meta_description' => $settings->default_meta_description,
                'default_canonical_url' => $settings->default_canonical_url, 'default_og_image_url' => $this->mediaUrl($settings->defaultOgImage), 'default_twitter_image_url' => $this->mediaUrl($settings->defaultTwitterImage), 'company_name' => $settings->company_name,
                'company_logo_url' => $settings->company_logo_url, 'contact_phone_india' => $settings->contact_phone_india,
                'contact_phone_singapore' => $settings->contact_phone_singapore, 'contact_phone_malaysia' => $settings->contact_phone_malaysia,
                'contact_email' => $settings->contact_email, 'address' => $settings->address, 'same_as_social_links' => $settings->same_as_social_links,
            ];
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function sitemap(): array
    {
        return $this->remember('sitemap', function (int $companyId): array {
            $pages = CmsPage::query()->where('company_id', $companyId)->where('status', CmsPage::STATUS_PUBLISHED)->where('include_in_sitemap', true)->get()
                ->map(fn (CmsPage $page) => ['type' => 'page', 'path' => $page->route_path, 'priority' => (float) $page->sitemap_priority, 'changefreq' => $page->sitemap_changefreq, 'lastmod' => ($page->updated_at ?? $page->published_at)?->toDateString()]);
            $articles = CmsArticle::query()->where('company_id', $companyId)->where('status', CmsArticle::STATUS_PUBLISHED)->where('include_in_sitemap', true)->get()
                ->map(fn (CmsArticle $article) => ['type' => 'article', 'path' => '/blog/'.$article->slug, 'priority' => (float) $article->sitemap_priority, 'changefreq' => $article->sitemap_changefreq, 'lastmod' => ($article->updated_at ?? $article->published_at)?->toDateString()]);
            return $pages->concat($articles)->values()->all();
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function redirects(): array
    {
        return $this->remember('redirects', fn (int $companyId) => CmsRedirect::query()->where('company_id', $companyId)->where('is_enabled', true)->get(['source_url', 'target_url', 'status_code'])->map(fn (CmsRedirect $redirect) => ['from_path' => $redirect->source_url, 'to_url' => $redirect->target_url, 'status_code' => $redirect->status_code])->all());
    }

    public function robots(): array
    {
        return $this->remember('robots', function (int $companyId): array {
            $settings = CmsSeoSetting::query()->where('company_id', $companyId)->first();
            return ['content' => $settings?->robots_txt, 'default_index' => $settings?->robots_default_index ?? true, 'default_follow' => $settings?->robots_default_follow ?? true, 'sitemap_url' => $settings?->sitemap_url];
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function contentPages(): array
    {
        return $this->remember('content-pages', fn (int $companyId) => CmsContentPage::query()
            ->where('company_id', $companyId)
            ->where('status', CmsContentPage::STATUS_PUBLISHED)
            ->orderBy('route_path')
            ->get()
            ->map(fn (CmsContentPage $page) => ['page_key' => $page->page_key, 'route_path' => $page->route_path, 'page_type' => $page->page_type, 'title' => $page->title])
            ->all()) ?? [];
    }

    public function contentPageByPath(string $path): ?array
    {
        return $this->remember('content-path:'.$this->path($path), function (int $companyId) use ($path): ?array {
            $page = CmsContentPage::query()
                ->with(['sections' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')])
                ->where('company_id', $companyId)
                ->where('route_path', $this->path($path))
                ->where('status', CmsContentPage::STATUS_PUBLISHED)
                ->first();

            return $page ? $this->contentPagePayload($page) : null;
        });
    }

    public function contentPage(string $pageKey): ?array
    {
        return $this->remember("content-key:{$pageKey}", function (int $companyId) use ($pageKey): ?array {
            $page = CmsContentPage::query()
                ->with(['sections' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')])
                ->where('company_id', $companyId)
                ->where('page_key', $pageKey)
                ->where('status', CmsContentPage::STATUS_PUBLISHED)
                ->first();

            return $page ? $this->contentPagePayload($page) : null;
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function contentNavigation(): array
    {
        return $this->remember('content-navigation', fn (int $companyId) => CmsNavigationItem::query()
            ->with('parent:id,label')
            ->where('company_id', $companyId)
            ->where('is_enabled', true)
            ->orderBy('location')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CmsNavigationItem $item) => [
                'label' => $item->label,
                'url' => $item->url,
                'location' => $item->location,
                'parent_label' => $item->parent?->label,
                'opens_new_tab' => $item->opens_new_tab,
            ])
            ->all()) ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function contentFooter(): array
    {
        return $this->remember('content-footer', fn (int $companyId) => CmsFooterBlock::query()
            ->where('company_id', $companyId)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CmsFooterBlock $block) => [
                'block_key' => $block->block_key,
                'title' => $block->title,
                'content' => $block->content,
                'links' => $block->links ?? [],
            ])
            ->all()) ?? [];
    }

    /** @return array<string, mixed> */
    public function contentPreview(CmsContentPage $page): array
    {
        return $this->contentPagePayload($page->load(['sections' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')]));
    }

    private function remember(string $key, callable $callback): mixed
    {
        $companyId = (int) (config('services.retailpos.public_lead_company_id') ?: Company::query()->where('is_active', true)->oldest('id')->value('id'));

        if (! $companyId) {
            return null;
        }

        if (app()->environment('testing')) {
            return $callback($companyId);
        }

        return Cache::remember("public-cms:{$companyId}:{$key}", now()->addMinutes(10), fn () => $callback($companyId));
    }

    /** @return array<string, mixed> */
    private function page(CmsPage $page): array
    {
        return ['route_path' => $page->route_path, 'title' => $page->title, 'h1' => $page->h1, 'subtitle' => $page->subtitle, 'hero_content' => $page->hero_content, 'intro_content' => $page->intro_content, 'body_content' => $page->body_content, 'footer_seo_content' => $page->footer_seo_content, 'primary_cta' => ['label' => $page->primary_cta_label, 'url' => $page->primary_cta_url], 'secondary_cta' => ['label' => $page->secondary_cta_label, 'url' => $page->secondary_cta_url], 'content_sections' => $page->content_sections, 'faq_items' => $page->faq_items, 'seo' => ['title' => $page->seo?->meta_title, 'description' => $page->seo?->meta_description, 'canonical_url' => $page->seo?->canonical_url, 'robots_index' => $page->robots_index, 'robots_follow' => $page->robots_follow, 'schema_json' => $page->schema_json, 'open_graph' => ['title' => $page->seo?->og_title, 'description' => $page->seo?->og_description, 'image_url' => $this->mediaUrl($page->seo?->ogImage), 'type' => $page->seo?->og_type], 'twitter' => ['title' => $page->seo?->twitter_title, 'description' => $page->seo?->twitter_description, 'image_url' => $this->mediaUrl($page->seo?->twitterImage), 'card' => $page->seo?->twitter_card]]];
    }

    /** @return array<string, mixed> */
    private function contentPagePayload(CmsContentPage $page): array
    {
        return [
            'page_key' => $page->page_key,
            'route_path' => $page->route_path,
            'page_type' => $page->page_type,
            'title' => $page->title,
            'sections' => $page->sections->map(fn (CmsContentSection $section) => array_filter([
                'section_key' => $section->section_key,
                'section_type' => $section->section_type,
                'eyebrow' => $section->eyebrow,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'body' => $section->body,
                'image_url' => $section->image_url,
                'primary_cta' => $this->button($section->primary_cta_label, $section->primary_cta_url),
                'secondary_cta' => $this->button($section->secondary_cta_label, $section->secondary_cta_url),
                'items' => $section->items ?? [],
            ], fn ($value) => $value !== null && $value !== []))->values()->all(),
        ];
    }

    /** @return array<string, string>|null */
    private function button(?string $label, ?string $url): ?array
    {
        return filled($label) || filled($url) ? ['label' => $label ?? '', 'url' => $url ?? ''] : null;
    }

    /** @return array<string, mixed> */
    private function articlePayload(CmsArticle $article, bool $withContent = false): array
    {
        return array_filter(['title' => $article->title, 'slug' => $article->slug, 'excerpt' => $article->excerpt, 'content' => $withContent ? $article->content : null, 'cover_image_url' => $this->mediaUrl($article->coverImage), 'author_name' => $article->author_name, 'category' => $article->category, 'tags' => $article->tags, 'published_at' => $article->published_at?->toIso8601String(), 'seo' => ['title' => $article->meta_title, 'description' => $article->meta_description, 'canonical_url' => $article->canonical_url, 'schema_json' => $article->schema_json]], fn ($value) => $value !== null);
    }

    private function mediaUrl(?CmsMedia $media): ?string
    {
        return $media ? Storage::disk($media->disk ?: config('filesystems.default'))->url($media->path) : null;
    }

    private function path(string $path): string { return '/'.ltrim($path, '/'); }
}
