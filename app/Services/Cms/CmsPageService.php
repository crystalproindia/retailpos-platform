<?php

namespace App\Services\Cms;

use App\Events\Domain\Cms\CmsPagePublished;
use App\Events\Domain\Cms\CmsPageUnpublished;
use App\Models\Cms\CmsPage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CmsPageService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
        private readonly WebsiteRevalidationService $revalidation,
        private readonly CmsRevisionService $revisions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): CmsPage
    {
        $page = CmsPage::create($this->pagePayload($data) + [
            'company_id' => $user->company_id,
            'author_user_id' => $user->id,
            'slug' => $this->slug($data['slug'] ?? $data['title']),
        ]);

        $page->seo()->create($this->seoPayload($data));
        $this->createRevision($page, $user);
        $this->revisions->record($page, $user, 'created', $this->snapshot($page), null, 'Page created');

        if ($page->status === CmsPage::STATUS_PUBLISHED) $this->revalidate($page);

        return $page->load('seo');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CmsPage $page, User $user, array $data): CmsPage
    {
        $before = $this->snapshot($page->load('seo'));
        $page->update($this->pagePayload($data) + [
            'slug' => $this->slug($data['slug'] ?? $page->slug),
        ]);

        $page->seo()->updateOrCreate(['page_id' => $page->id], $this->seoPayload($data));
        $this->createRevision($page->refresh(), $user);
        $this->revisions->record($page, $user, 'updated', $this->snapshot($page->load('seo')), $before, 'Page draft saved');

        if ($page->status === CmsPage::STATUS_PUBLISHED) $this->revalidate($page);

        return $page->load('seo');
    }

    public function publish(CmsPage $page, User $user): CmsPage
    {
        $page->update([
            'status' => CmsPage::STATUS_PUBLISHED,
            'published_at' => now(),
            'scheduled_for' => null,
        ]);

        $this->createRevision($page->refresh(), $user);
        $this->revisions->record($page, $user, 'published', $this->snapshot($page->load('seo')), null, 'Page published');
        $this->auditLogger->record('cms.page.published', $page, 'CMS page published');
        $this->domainEvents->dispatch(new CmsPagePublished(
            companyId: $page->company_id,
            actorId: $user->id,
            aggregateType: CmsPage::class,
            aggregateId: $page->id,
            payload: $this->eventPayload($page),
        ));
        $this->revalidate($page);

        return $page;
    }

    public function unpublish(CmsPage $page, User $user): CmsPage
    {
        $page->update([
            'status' => CmsPage::STATUS_DRAFT,
            'published_at' => null,
            'scheduled_for' => null,
        ]);

        $this->createRevision($page->refresh(), $user);
        $this->revisions->record($page, $user, 'unpublished', $this->snapshot($page->load('seo')), null, 'Page unpublished');
        $this->auditLogger->record('cms.page.unpublished', $page, 'CMS page unpublished');
        $this->domainEvents->dispatch(new CmsPageUnpublished(
            companyId: $page->company_id,
            actorId: $user->id,
            aggregateType: CmsPage::class,
            aggregateId: $page->id,
            payload: $this->eventPayload($page),
        ));
        $this->revalidate($page);

        return $page;
    }

    public function delete(CmsPage $page): void
    {
        $page->delete();
    }

    public function restore(CmsPage $page): CmsPage
    {
        $page->restore();

        $this->auditLogger->record('cms.page.restored', $page, 'CMS page restored');

        return $page;
    }

    public function restoreRevision(CmsPage $page, \App\Models\Cms\CmsRevision $revision, User $user): CmsPage
    {
        $snapshot = $revision->snapshot['page'] ?? [];
        $page->update(Arr::only($snapshot, ['slug', 'route_path', 'title', 'h1', 'page_type', 'subtitle', 'hero_content', 'intro_content', 'body_content', 'footer_seo_content', 'schema_json']) + ['status' => CmsPage::STATUS_DRAFT, 'published_at' => null, 'scheduled_for' => null]);
        $this->revisions->record($page->refresh(), $user, 'restored', $this->snapshot($page->load('seo')), null, 'Revision restored as draft');
        $this->auditLogger->record('cms.page.revision_restored', $page, 'CMS page revision restored as draft', ['revision_id' => $revision->id]);
        return $page;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function pagePayload(array $data): array
    {
        $payload = Arr::only($data, [
            'featured_image_id',
            'title',
            'subtitle',
            'hero_content',
            'body_content',
            'page_type',
            'cta_label',
            'cta_url',
            'is_active',
            'sort_order',
            'status',
            'scheduled_for',
        ]);

        if (($payload['status'] ?? CmsPage::STATUS_DRAFT) === CmsPage::STATUS_PUBLISHED) {
            $payload['published_at'] = now();
            $payload['scheduled_for'] = null;
            $payload['is_active'] = true;
        }

        if (($payload['status'] ?? null) === CmsPage::STATUS_SCHEDULED) {
            $payload['published_at'] = null;
        }

        if (($payload['status'] ?? null) === CmsPage::STATUS_DRAFT) {
            $payload['published_at'] = null;
            $payload['scheduled_for'] = null;
        }

        if (($payload['status'] ?? null) === CmsPage::STATUS_ARCHIVED) {
            $payload['published_at'] = null;
            $payload['scheduled_for'] = null;
            $payload['is_active'] = false;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function seoPayload(array $data): array
    {
        return Arr::only($data, [
            'meta_title',
            'meta_description',
            'meta_keywords',
            'canonical_url',
            'og_title',
            'og_description',
            'og_image_id',
            'og_type',
            'twitter_title',
            'twitter_description',
            'twitter_image_id',
            'twitter_card',
        ]);
    }

    private function slug(string $value): string
    {
        return Str::slug($value);
    }

    private function path(CmsPage $page): string
    {
        return $page->route_path ?: '/'.$page->slug;
    }

    private function revalidate(CmsPage $page): void
    {
        $this->revalidation->revalidate($page->company_id, $this->path($page), [
            'type' => 'page',
            'slug' => $page->slug,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(CmsPage $page): array
    {
        return [
            'page_id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status,
            'published_at' => $page->published_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(CmsPage $page): array
    {
        return ['page' => $page->only(['slug', 'route_path', 'title', 'h1', 'page_type', 'subtitle', 'hero_content', 'intro_content', 'body_content', 'footer_seo_content', 'status', 'is_active', 'schema_json', 'published_at']), 'seo' => $page->seo?->toArray(), 'sections' => $page->sections()->orderBy('sort_order')->get()->map->only(['section_key', 'section_type', 'title', 'subtitle', 'content', 'settings', 'sort_order', 'is_active'])->all()];
    }
}
