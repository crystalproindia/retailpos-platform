<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsPage;
use App\Repositories\Cms\CmsWebsiteControlRepository;

class CmsWebsiteControlService
{
    public function __construct(private readonly CmsWebsiteControlRepository $repository) {}
    /** @return array<string, mixed> */ public function dashboard(int $companyId): array { $counts = $this->repository->counts($companyId); $warnings = $this->repository->seoWarnings($companyId); return ['counts' => $counts, 'warnings' => $warnings, 'recentPages' => CmsPage::query()->where('company_id', $companyId)->latest()->limit(6)->get(), 'readiness' => [['label' => 'Branding', 'ready' => true], ['label' => 'Theme', 'ready' => true], ['label' => 'Homepage sections', 'ready' => true], ['label' => 'SEO metadata', 'ready' => array_sum($warnings) === 0]]]; }
}
