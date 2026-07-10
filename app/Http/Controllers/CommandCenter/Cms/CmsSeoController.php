<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreCmsRedirectRequest;
use App\Http\Requests\Cms\UpdateCmsSeoRequest;
use App\Repositories\Cms\CmsSeoRepository;
use App\Services\Cms\CmsSeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsSeoController extends Controller
{
    public function index(Request $request, CmsSeoRepository $seoRepository): View
    {
        return view('command-center.cms.seo.index', [
            'seoSettings' => $seoRepository->settingsForCompany($request->user()->company_id),
            'redirects' => $seoRepository->redirectsForCompany($request->user()->company_id),
            'brokenLinks' => $seoRepository->brokenLinksForCompany($request->user()->company_id),
        ]);
    }

    public function update(UpdateCmsSeoRequest $request, CmsSeoRepository $seoRepository, CmsSeoService $seoService): RedirectResponse
    {
        $seoService->updateSettings(
            $seoRepository->settingsForCompany($request->user()->company_id),
            $request->user(),
            $request->validated(),
        );

        return back()->with('status', 'SEO settings updated.');
    }

    public function storeRedirect(StoreCmsRedirectRequest $request, CmsSeoService $seoService): RedirectResponse
    {
        $seoService->createRedirect($request->user(), $request->validated());

        return back()->with('status', 'Redirect created.');
    }
}
