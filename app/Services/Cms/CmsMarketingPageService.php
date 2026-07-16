<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsPage;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CmsMarketingPageService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): CmsPage
    {
        $page = CmsPage::create(array_merge($this->payload($data), [
            'company_id' => $user->company_id,
            'author_user_id' => $user->id,
            'slug' => $this->slug($data['route_path']),
        ], $this->publicationState($data)));
        $page->seo()->create($this->seoPayload($data));
        $this->createRevision($page, $user);
        $this->auditLogger->record('cms.marketing_page.created', $page, 'CMS marketing page created', ['page_type' => $page->page_type]);

        return $page->load('seo');
    }

    /** @param array<string, mixed> $data */
    public function update(CmsPage $page, User $user, array $data): CmsPage
    {
        $page->update(array_merge($this->payload($data), ['slug' => $this->slug($data['route_path']), 'updated_by' => $user->id], $this->publicationState($data)));
        $page->seo()->updateOrCreate(['page_id' => $page->id], $this->seoPayload($data));
        $this->createRevision($page->refresh(), $user);
        $this->auditLogger->record('cms.marketing_page.updated', $page, 'CMS marketing page updated', ['page_type' => $page->page_type]);

        return $page->refresh()->load('seo');
    }

    public function publish(CmsPage $page, User $user): CmsPage
    {
        $page->update(['status' => CmsPage::STATUS_PUBLISHED, 'published_at' => now(), 'scheduled_for' => null, 'is_active' => true, 'updated_by' => $user->id]);
        $this->createRevision($page->refresh(), $user);
        $this->auditLogger->record('cms.marketing_page.published', $page, 'CMS marketing page published');

        return $page;
    }

    public function unpublish(CmsPage $page, User $user): CmsPage
    {
        $page->update(['status' => CmsPage::STATUS_DRAFT, 'published_at' => null, 'scheduled_for' => null, 'is_active' => false, 'updated_by' => $user->id]);
        $this->createRevision($page->refresh(), $user);
        $this->auditLogger->record('cms.marketing_page.unpublished', $page, 'CMS marketing page unpublished');

        return $page;
    }

    public function archive(CmsPage $page, User $user): CmsPage
    {
        $page->update(['status' => CmsPage::STATUS_ARCHIVED, 'published_at' => null, 'scheduled_for' => null, 'is_active' => false, 'updated_by' => $user->id]);
        $this->createRevision($page->refresh(), $user);
        $this->auditLogger->record('cms.marketing_page.archived', $page, 'CMS marketing page archived');

        return $page;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function publicationState(array $data): array
    {
        return match ($data['status']) {
            CmsPage::STATUS_PUBLISHED => ['published_at' => now(), 'scheduled_for' => null, 'is_active' => true],
            CmsPage::STATUS_SCHEDULED => ['published_at' => null, 'scheduled_for' => $data['scheduled_for'], 'is_active' => false],
            default => ['published_at' => null, 'scheduled_for' => null, 'is_active' => false],
        };
    }

    private function createRevision(CmsPage $page, User $user): void
    {
        $page->revisions()->create([
            'user_id' => $user->id,
            'revision_number' => $page->revisions()->count() + 1,
            'title' => $page->title,
            'subtitle' => $page->subtitle,
            'hero_content' => $page->hero_content,
            'body_content' => $page->body_content,
            'status' => $page->status,
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function payload(array $data): array
    {
        return Arr::only($data, ['route_path', 'title', 'h1', 'page_type', 'subtitle', 'hero_content', 'intro_content', 'body_content', 'footer_seo_content', 'primary_cta_label', 'primary_cta_url', 'secondary_cta_label', 'secondary_cta_url', 'robots_index', 'robots_follow', 'schema_json', 'include_in_sitemap', 'sitemap_priority', 'sitemap_changefreq', 'status', 'scheduled_for'])
            + ['content_sections' => $this->json($data['content_sections'] ?? null), 'faq_items' => $this->json($data['faq_items'] ?? null), 'related_product_keys' => $this->json($data['related_product_keys'] ?? null), 'related_industry_keys' => $this->json($data['related_industry_keys'] ?? null)];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function seoPayload(array $data): array { return Arr::only($data, ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image_id', 'og_type', 'twitter_title', 'twitter_description', 'twitter_image_id', 'twitter_card']); }
    private function slug(string $routePath): string { return Str::slug(str_replace('/', '-', trim($routePath, '/'))) ?: 'home'; }
    private function json(?string $value): ?array { return filled($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : null; }
}
