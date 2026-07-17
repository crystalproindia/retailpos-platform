<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsContentPage;
use App\Models\Cms\CmsContentSection;
use App\Models\Cms\CmsFooterBlock;
use App\Models\Cms\CmsNavigationItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CmsContentEditorService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly PublicCmsService $publicCms) {}

    /** @param array<string, mixed> $data */
    public function createPage(User $user, array $data): CmsContentPage
    {
        $page = CmsContentPage::create(Arr::only($data, ['page_key', 'route_path', 'page_type', 'title']) + ['company_id' => $user->company_id, 'status' => CmsContentPage::STATUS_DRAFT, 'created_by' => $user->id, 'updated_by' => $user->id]);
        $this->auditLogger->record('cms.content_page.created', $page, 'Website content page created');
        $this->forgetPublicContent($page->company_id);
        return $page;
    }

    /** @param array<string, mixed> $data */
    public function updatePage(CmsContentPage $page, User $user, array $data): CmsContentPage
    {
        $page->update(Arr::only($data, ['page_key', 'route_path', 'page_type', 'title']) + ['updated_by' => $user->id]);
        $this->auditLogger->record('cms.content_page.updated', $page, 'Website content page updated');
        $this->forgetPublicContent($page->company_id);
        return $page->refresh();
    }

    public function publish(CmsContentPage $page, User $user): CmsContentPage
    {
        $page->update(['status' => CmsContentPage::STATUS_PUBLISHED, 'published_at' => now(), 'updated_by' => $user->id]);
        $this->auditLogger->record('cms.content_page.published', $page, 'Website content page published');
        $this->forgetPublicContent($page->company_id);
        return $page->refresh();
    }

    public function unpublish(CmsContentPage $page, User $user): CmsContentPage
    {
        $page->update(['status' => CmsContentPage::STATUS_DRAFT, 'published_at' => null, 'updated_by' => $user->id]);
        $this->auditLogger->record('cms.content_page.unpublished', $page, 'Website content page moved to draft');
        $this->forgetPublicContent($page->company_id);
        return $page->refresh();
    }

    public function archive(CmsContentPage $page, User $user): CmsContentPage
    {
        $page->update(['status' => CmsContentPage::STATUS_ARCHIVED, 'published_at' => null, 'updated_by' => $user->id]);
        $this->auditLogger->record('cms.content_page.archived', $page, 'Website content page archived');
        $this->forgetPublicContent($page->company_id);
        return $page->refresh();
    }

    /** @param array<string, mixed> $data */
    public function createSection(CmsContentPage $page, User $user, array $data): CmsContentSection
    {
        $this->ensureUniqueSectionKey($page, $data['section_key']);
        $section = $page->sections()->create($this->sectionPayload($data) + ['sort_order' => ((int) $page->sections()->max('sort_order')) + 10]);
        $this->auditLogger->record('cms.content_section.created', $section, 'Website content section added');
        $this->forgetPublicContent($page->company_id);
        return $section;
    }

    /** @param array<string, mixed> $data */
    public function updateSection(CmsContentSection $section, array $data): CmsContentSection
    {
        $this->ensureUniqueSectionKey($section->page, $data['section_key'], $section->id);
        $section->update($this->sectionPayload($data));
        $this->auditLogger->record('cms.content_section.updated', $section, 'Website content section updated');
        $this->forgetPublicContent($section->page->company_id);
        return $section->refresh();
    }

    public function toggleSection(CmsContentSection $section, bool $enabled): CmsContentSection
    {
        $section->update(['is_enabled' => $enabled]);
        $this->auditLogger->record($enabled ? 'cms.content_section.enabled' : 'cms.content_section.disabled', $section, $enabled ? 'Website content section enabled' : 'Website content section disabled');
        $this->forgetPublicContent($section->page->company_id);
        return $section->refresh();
    }

    public function moveSection(CmsContentSection $section, string $direction): CmsContentSection
    {
        $query = $section->page->sections();
        $neighbour = $direction === 'up'
            ? $query->where('sort_order', '<', $section->sort_order)->orderByDesc('sort_order')->first()
            : $query->where('sort_order', '>', $section->sort_order)->orderBy('sort_order')->first();
        if ($neighbour) {
            [$sectionOrder, $neighbourOrder] = [$section->sort_order, $neighbour->sort_order];
            $section->update(['sort_order' => $neighbourOrder]);
            $neighbour->update(['sort_order' => $sectionOrder]);
            $this->auditLogger->record('cms.content_section.reordered', $section, 'Website content section reordered');
            $this->forgetPublicContent($section->page->company_id);
        }
        return $section->refresh();
    }

    public function deleteSection(CmsContentSection $section): void
    {
        $this->auditLogger->record('cms.content_section.deleted', $section, 'Website content section deleted');
        $this->forgetPublicContent($section->page->company_id);
        $section->delete();
    }

    /** @param array<string, mixed> $data */
    public function saveNavigation(User $user, array $data, ?CmsNavigationItem $item = null): CmsNavigationItem
    {
        if (filled($data['parent_id'] ?? null)) {
            $parent = CmsNavigationItem::query()->where('company_id', $user->company_id)->find($data['parent_id']);
            if (! $parent || ($item && $parent->id === $item->id)) throw ValidationException::withMessages(['parent_id' => 'Choose a navigation item from this website.']);
        }
        $payload = Arr::only($data, ['label', 'url', 'parent_id', 'location', 'sort_order', 'is_enabled', 'opens_new_tab']);
        $payload['sort_order'] = $payload['sort_order'] ?? ((int) CmsNavigationItem::query()->where('company_id', $user->company_id)->where('location', $data['location'])->max('sort_order')) + 10;
        if ($item) { $item->update($payload); $this->auditLogger->record('cms.navigation.updated', $item, 'Website navigation item updated'); $this->forgetPublicContent($user->company_id); return $item->refresh(); }
        $item = CmsNavigationItem::create($payload + ['company_id' => $user->company_id]);
        $this->auditLogger->record('cms.navigation.created', $item, 'Website navigation item created');
        $this->forgetPublicContent($user->company_id);
        return $item;
    }

    /** @param array<string, mixed> $data */
    public function saveFooter(User $user, array $data, ?CmsFooterBlock $block = null): CmsFooterBlock
    {
        $duplicate = CmsFooterBlock::query()
            ->where('company_id', $user->company_id)
            ->where('block_key', $data['block_key'])
            ->when($block, fn ($query) => $query->where('id', '!=', $block->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['block_key' => 'This footer area already exists. Edit the existing block instead.']);
        }

        $payload = Arr::only($data, ['block_key', 'title', 'content', 'links', 'sort_order', 'is_enabled']);
        $payload['sort_order'] = $payload['sort_order'] ?? ((int) CmsFooterBlock::query()->where('company_id', $user->company_id)->max('sort_order')) + 10;
        $payload['links'] = $this->cleanItems($payload['links'] ?? []);
        if ($block) { $block->update($payload); $this->auditLogger->record('cms.footer.updated', $block, 'Website footer block updated'); $this->forgetPublicContent($user->company_id); return $block->refresh(); }
        $block = CmsFooterBlock::create($payload + ['company_id' => $user->company_id]);
        $this->auditLogger->record('cms.footer.created', $block, 'Website footer block created');
        $this->forgetPublicContent($user->company_id);
        return $block;
    }

    public function ensureHomepage(User $user): CmsContentPage
    {
        $page = CmsContentPage::query()->firstOrCreate(['company_id' => $user->company_id, 'page_key' => 'home'], ['route_path' => '/', 'page_type' => 'home', 'title' => 'RetailPOS Home', 'status' => CmsContentPage::STATUS_DRAFT, 'created_by' => $user->id, 'updated_by' => $user->id]);
        if ($page->sections()->exists()) return $page;
        foreach ([['home_hero', 'hero', 'Run retail operations with confidence'], ['product_highlights', 'product_highlights', 'Built for modern retail teams'], ['features', 'feature_grid', 'Everything your team needs'], ['industries', 'industry_use_cases', 'Made for your retail business'], ['ai_powered', 'benefits', 'Work smarter with RetailPOS'], ['testimonials', 'testimonials', 'Trusted by growing teams'], ['faq', 'faq', 'Frequently asked questions'], ['home_cta', 'cta', 'Ready to simplify your retail operations?'], ['footer_seo', 'footer_seo', 'RetailPOS business software']] as $index => [$key, $type, $title]) {
            $page->sections()->create(['section_key' => $key, 'section_type' => $type, 'title' => $title, 'sort_order' => ($index + 1) * 10, 'is_enabled' => true]);
        }
        return $page->refresh();
    }

    /** @return array<int, string> */
    public function health(CmsContentPage $page): array
    {
        $sections = $page->sections;
        $warnings = [];
        $hero = $sections->firstWhere('section_type', 'hero');
        if (! $hero || blank($hero->title)) $warnings[] = 'Hero title is missing';
        if ($hero && blank($hero->image_url)) $warnings[] = 'Hero image link is missing';
        if (! $sections->contains(fn (CmsContentSection $section) => filled($section->primary_cta_label) && filled($section->primary_cta_url))) $warnings[] = 'CTA button is missing';
        if (! $sections->contains('section_type', 'faq')) $warnings[] = 'FAQ section is missing';
        if (! $sections->contains('section_type', 'footer_seo')) $warnings[] = 'Footer SEO content is missing';
        if ($page->status === CmsContentPage::STATUS_PUBLISHED && $sections->where('is_enabled', true)->isEmpty()) $warnings[] = 'No published sections are available';
        if ($page->status !== CmsContentPage::STATUS_PUBLISHED) $warnings[] = 'This page is still draft';
        if ($sections->contains('is_enabled', false)) $warnings[] = 'This page has a disabled section';
        return $warnings;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sectionPayload(array $data): array
    {
        $payload = Arr::only($data, ['section_key', 'section_type', 'title', 'subtitle', 'eyebrow', 'body', 'image_url', 'primary_cta_label', 'primary_cta_url', 'secondary_cta_label', 'secondary_cta_url', 'is_enabled']);
        $payload['items'] = $this->cleanItems($data['items'] ?? []);
        return $payload;
    }

    /** @param array<int, mixed> $items @return array<int, array<string, mixed>> */
    private function cleanItems(array $items): array
    {
        return collect($items)->map(fn ($item) => array_filter(is_array($item) ? Arr::only($item, ['title', 'description', 'url', 'icon_key', 'question', 'answer', 'name', 'role_company', 'quote', 'rating', 'label', 'value']) : [], fn ($value) => $value !== null && $value !== ''))->filter()->values()->all();
    }

    private function ensureUniqueSectionKey(CmsContentPage $page, string $key, ?int $ignoreId = null): void
    {
        $exists = $page->sections()->where('section_key', $key)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists();
        if ($exists) throw ValidationException::withMessages(['section_key' => 'Use a unique section name on this page.']);
    }

    private function forgetPublicContent(int $companyId): void
    {
        $this->publicCms->forgetContentForCompany($companyId);
    }
}
