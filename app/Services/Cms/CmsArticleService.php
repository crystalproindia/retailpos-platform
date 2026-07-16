<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsArticle;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;

class CmsArticleService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): CmsArticle
    {
        $article = CmsArticle::create($this->payload($data) + ['company_id' => $user->company_id, 'created_by' => $user->id, 'published_at' => $data['status'] === CmsArticle::STATUS_PUBLISHED ? now() : null]);
        $this->auditLogger->record('cms.article.created', $article, 'CMS article created');
        return $article;
    }

    /** @param array<string, mixed> $data */
    public function update(CmsArticle $article, User $user, array $data): CmsArticle
    {
        $article->update($this->payload($data) + ['updated_by' => $user->id]);
        $this->auditLogger->record('cms.article.updated', $article, 'CMS article updated');
        return $article->refresh();
    }

    public function publish(CmsArticle $article, User $user): CmsArticle { $article->update(['status' => CmsArticle::STATUS_PUBLISHED, 'published_at' => now(), 'updated_by' => $user->id]); $this->auditLogger->record('cms.article.published', $article, 'CMS article published'); return $article; }
    public function unpublish(CmsArticle $article, User $user): CmsArticle { $article->update(['status' => CmsArticle::STATUS_DRAFT, 'published_at' => null, 'updated_by' => $user->id]); $this->auditLogger->record('cms.article.unpublished', $article, 'CMS article unpublished'); return $article; }
    public function archive(CmsArticle $article, User $user): CmsArticle { $article->update(['status' => CmsArticle::STATUS_ARCHIVED, 'published_at' => null, 'updated_by' => $user->id]); $this->auditLogger->record('cms.article.archived', $article, 'CMS article archived'); return $article; }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function payload(array $data): array { return Arr::only($data, ['cover_image_id', 'title', 'slug', 'excerpt', 'content', 'author_name', 'category', 'meta_title', 'meta_description', 'canonical_url', 'schema_json', 'status', 'include_in_sitemap', 'sitemap_priority', 'sitemap_changefreq']) + ['tags' => filled($data['tags'] ?? null) ? json_decode($data['tags'], true, flags: JSON_THROW_ON_ERROR) : null]; }
}
