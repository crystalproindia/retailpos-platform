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

        if ($page->status === CmsPage::STATUS_PUBLISHED) $this->revalidation->trigger($this->path($page));

        return $page->load('seo');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CmsPage $page, User $user, array $data): CmsPage
    {
        $page->update($this->pagePayload($data) + [
            'slug' => $this->slug($data['slug'] ?? $page->slug),
        ]);

        $page->seo()->updateOrCreate(['page_id' => $page->id], $this->seoPayload($data));
        $this->createRevision($page->refresh(), $user);

        if ($page->status === CmsPage::STATUS_PUBLISHED) $this->revalidation->trigger($this->path($page));

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
        $this->auditLogger->record('cms.page.published', $page, 'CMS page published');
        $this->domainEvents->dispatch(new CmsPagePublished(
            companyId: $page->company_id,
            actorId: $user->id,
            aggregateType: CmsPage::class,
            aggregateId: $page->id,
            payload: $this->eventPayload($page),
        ));
        $this->revalidation->trigger($this->path($page));

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
        $this->auditLogger->record('cms.page.unpublished', $page, 'CMS page unpublished');
        $this->domainEvents->dispatch(new CmsPageUnpublished(
            companyId: $page->company_id,
            actorId: $user->id,
            aggregateType: CmsPage::class,
            aggregateId: $page->id,
            payload: $this->eventPayload($page),
        ));
        $this->revalidation->trigger($this->path($page));

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
}
