<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsMedia;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsPage;
use App\Repositories\Cms\CmsHomepageRepository;
use App\Services\Cms\CmsHomepageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsDashboardController extends Controller
{
    public function __invoke(Request $request, CmsHomepageService $homepageService, CmsHomepageRepository $homepageRepository): View
    {
        $companyId = $request->user()->company_id;

        $homepageService->ensureDefaultSections($companyId);

        return view('command-center.cms.dashboard', [
            'pageCount' => CmsPage::query()->where('company_id', $companyId)->count(),
            'publishedPageCount' => CmsPage::query()->where('company_id', $companyId)->where('status', CmsPage::STATUS_PUBLISHED)->count(),
            'mediaCount' => CmsMedia::query()->where('company_id', $companyId)->count(),
            'menuCount' => CmsMenu::query()->where('company_id', $companyId)->count(),
            'homepageSections' => $homepageRepository->sectionsForCompany($companyId),
        ]);
    }
}
