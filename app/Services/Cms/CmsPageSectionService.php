<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPageSection;
use App\Models\User;
use App\Services\AuditLogger;

class CmsPageSectionService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly WebsiteRevalidationService $revalidation) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(CmsPage $page, User $user, array $data): CmsPageSection
    {
        $section = $page->sections()->create($data + ['company_id' => $user->company_id]);

        $this->auditLogger->record('cms.page_section.created', $section, 'CMS page section created');
        $this->revalidate($page);

        return $section;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CmsPageSection $section, array $data): CmsPageSection
    {
        $section->update($data);

        $this->auditLogger->record('cms.page_section.updated', $section, 'CMS page section updated');
        $this->revalidate($section->page);

        return $section;
    }

    public function delete(CmsPageSection $section): void
    {
        $page = $section->page;
        $section->delete();

        $this->auditLogger->record('cms.page_section.deleted', $section, 'CMS page section deleted');
        $this->revalidate($page);
    }

    public function move(CmsPageSection $section, string $direction): CmsPageSection
    {
        $comparison = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'desc' : 'asc';
        $neighbor = CmsPageSection::query()
            ->where('page_id', $section->page_id)
            ->where('sort_order', $comparison, $section->sort_order)
            ->orderBy('sort_order', $order)
            ->first();

        if (! $neighbor) {
            return $section;
        }

        [$section->sort_order, $neighbor->sort_order] = [$neighbor->sort_order, $section->sort_order];
        $section->save();
        $neighbor->save();
        $this->auditLogger->record('cms.page_section.reordered', $section, 'CMS page section reordered');
        $this->revalidate($section->page);

        return $section->refresh();
    }

    private function revalidate(CmsPage $page): void
    {
        if ($page->status !== CmsPage::STATUS_PUBLISHED) {
            return;
        }

        $this->revalidation->revalidate($page->company_id, $page->route_path ?: '/'.$page->slug, [
            'type' => 'page',
            'slug' => $page->slug,
        ]);
    }
}
