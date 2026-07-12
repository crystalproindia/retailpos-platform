<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsCaseStudy;
use App\Models\Cms\CmsClientLogo;
use App\Models\Cms\CmsCtaBlock;
use App\Models\Cms\CmsFaq;
use App\Models\Cms\CmsMedia;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsTestimonial;
use App\Models\Cms\CmsTrustMetric;

class CmsWebsiteControlRepository
{
    /** @return array<string, int> */
    public function counts(int $companyId): array
    {
        return [
            'pages' => CmsPage::query()->where('company_id', $companyId)->count(), 'published_pages' => CmsPage::query()->where('company_id', $companyId)->where('status', CmsPage::STATUS_PUBLISHED)->count(),
            'draft_pages' => CmsPage::query()->where('company_id', $companyId)->where('status', CmsPage::STATUS_DRAFT)->count(), 'scheduled_pages' => CmsPage::query()->where('company_id', $companyId)->where('status', CmsPage::STATUS_SCHEDULED)->count(),
            'client_logos' => CmsClientLogo::query()->where('company_id', $companyId)->count(), 'case_studies' => CmsCaseStudy::query()->where('company_id', $companyId)->count(), 'testimonials' => CmsTestimonial::query()->where('company_id', $companyId)->count(),
            'trust_metrics' => CmsTrustMetric::query()->where('company_id', $companyId)->count(), 'faqs' => CmsFaq::query()->where('company_id', $companyId)->count(), 'cta_blocks' => CmsCtaBlock::query()->where('company_id', $companyId)->count(),
            'media' => CmsMedia::query()->where('company_id', $companyId)->count(), 'redirects' => CmsRedirect::query()->where('company_id', $companyId)->count(),
        ];
    }

    /** @return array<string, int> */
    public function seoWarnings(int $companyId): array
    {
        return [
            'missing_titles' => CmsPage::query()->where('cms_pages.company_id', $companyId)->leftJoin('cms_page_seo', 'cms_page_seo.page_id', '=', 'cms_pages.id')->whereNull('cms_page_seo.meta_title')->count(),
            'missing_descriptions' => CmsPage::query()->where('cms_pages.company_id', $companyId)->leftJoin('cms_page_seo', 'cms_page_seo.page_id', '=', 'cms_pages.id')->whereNull('cms_page_seo.meta_description')->count(),
            'missing_og_images' => CmsPage::query()->where('cms_pages.company_id', $companyId)->leftJoin('cms_page_seo', 'cms_page_seo.page_id', '=', 'cms_pages.id')->whereNull('cms_page_seo.og_image_id')->count(),
        ];
    }
}
