<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsPage;
use App\Repositories\Cms\CmsRevalidationLogRepository;
use App\Repositories\Cms\CmsWebsiteControlRepository;

class CmsWebsiteControlService
{
    public function __construct(private readonly CmsWebsiteControlRepository $repository, private readonly CmsRevalidationLogRepository $revalidations) {}
    /** @return array<string, mixed> */ public function dashboard(int $companyId): array { $counts = $this->repository->counts($companyId); $warnings = $this->repository->seoWarnings($companyId); return ['counts' => $counts, 'warnings' => $warnings, 'recentPages' => CmsPage::query()->where('company_id', $companyId)->latest()->limit(6)->get(), 'lastContentUpdate' => CmsPage::query()->where('company_id', $companyId)->latest('updated_at')->first()?->updated_at, 'revalidation' => ['configured' => filled(config('services.retailpos.website_revalidate_url')) && filled(config('services.retailpos.website_revalidate_token')), 'latest' => $this->revalidations->latestForCompany($companyId), 'last_success' => $this->revalidations->latestByStatus($companyId, 'success'), 'last_failure' => $this->revalidations->latestByStatus($companyId, 'failed')], 'readiness' => [['label' => 'Branding', 'ready' => true], ['label' => 'Theme', 'ready' => true], ['label' => 'Homepage sections', 'ready' => true], ['label' => 'SEO metadata', 'ready' => array_sum($warnings) === 0]]]; }
}
