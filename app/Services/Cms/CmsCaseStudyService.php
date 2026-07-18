<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsCaseStudy;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsCaseStudyService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly CmsProEventService $events, private readonly WebsiteRevalidationService $revalidation) {}
    /** @param array<string, mixed> $data */ public function create(User $user, array $data): CmsCaseStudy { return DB::transaction(function () use ($user, $data) { $study = CmsCaseStudy::create($this->payload($data) + ['company_id' => $user->company_id, 'slug' => Str::slug($data['slug'] ?: $data['title'])]); $this->syncSections($study, $data['sections'] ?? []); $this->auditLogger->record('cms.case_study.created', $study, 'Case study created'); $this->events->dispatch('cms.case_study.created', $user, $study, ['title' => $study->title]); $study = $study->refresh(); if ($study->status === 'published') $this->revalidate($study); return $study; }); }
    /** @param array<string, mixed> */ public function update(CmsCaseStudy $study, User $user, array $data): CmsCaseStudy { return DB::transaction(function () use ($study, $user, $data) { $study->update($this->payload($data) + ['slug' => Str::slug($data['slug'] ?: $study->slug)]); $this->syncSections($study, $data['sections'] ?? []); $this->auditLogger->record('cms.case_study.updated', $study, 'Case study updated'); $study = $study->refresh(); if ($study->status === 'published') $this->revalidate($study); return $study; }); }
    public function publish(CmsCaseStudy $study, User $user): CmsCaseStudy { $study->update(['status' => 'published', 'published_at' => now()]); $this->auditLogger->record('cms.case_study.published', $study, 'Case study published'); $this->events->dispatch('cms.case_study.published', $user, $study, ['title' => $study->title]); $study = $study->refresh(); $this->revalidate($study); return $study; }
    public function unpublish(CmsCaseStudy $study, User $user): CmsCaseStudy { $study->update(['status' => 'draft', 'published_at' => null]); $this->auditLogger->record('cms.case_study.unpublished', $study, 'Case study unpublished'); $this->events->dispatch('cms.case_study.unpublished', $user, $study, ['title' => $study->title]); $study = $study->refresh(); $this->revalidate($study); return $study; }
    public function delete(CmsCaseStudy $study): void { $study->delete(); $this->auditLogger->record('cms.case_study.deleted', $study, 'Case study deleted'); }
    public function restore(CmsCaseStudy $study): CmsCaseStudy { $study->restore(); $this->auditLogger->record('cms.case_study.restored', $study, 'Case study restored'); return $study->refresh(); }
    private function revalidate(CmsCaseStudy $study): void { $context = ['type' => 'case_study', 'slug' => $study->slug]; $this->revalidation->revalidate($study->company_id, '/case-studies', $context); $this->revalidation->revalidate($study->company_id, '/case-studies/'.$study->slug, $context); }
    /** @param array<string, mixed> $data @return array<string, mixed> */ private function payload(array $data): array { $payload = collect($data)->except(['slug', 'sections', 'metrics_json'])->all(); $payload['client_name'] ??= ''; $payload['metrics'] = array_filter($data['metrics'] ?? []); $payload['gallery_media_ids'] = array_values(array_filter($data['gallery_media_ids'] ?? [])); if (($payload['status'] ?? 'draft') === 'published') $payload['published_at'] ??= now(); if (($payload['status'] ?? null) === 'archived') $payload['published_at'] = null; return $payload; }
    /** @param array<int, array<string, mixed>> $sections */ private function syncSections(CmsCaseStudy $study, array $sections): void { $study->sections()->delete(); foreach ($sections as $index => $section) { if (! filled($section['section_type'] ?? null)) continue; $study->sections()->create(['company_id' => $study->company_id] + $section + ['sort_order' => $section['sort_order'] ?? $index + 1]); } }
}
