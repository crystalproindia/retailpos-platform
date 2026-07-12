<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Repositories\Cms\CmsHomepageRepository;
use App\Services\Cms\CmsHomepageService;
use App\Services\Cms\CmsWebsiteControlService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsDashboardController extends Controller
{
    public function __invoke(Request $request, CmsHomepageService $homepageService, CmsHomepageRepository $homepageRepository, CmsWebsiteControlService $websiteControl): View
    {
        $companyId = $request->user()->company_id;

        $homepageService->ensureDefaultSections($companyId);

        return view('command-center.cms.dashboard', [
            'dashboard' => $websiteControl->dashboard($companyId),
            'homepageSections' => $homepageRepository->sectionsForCompany($companyId),
        ]);
    }
}
