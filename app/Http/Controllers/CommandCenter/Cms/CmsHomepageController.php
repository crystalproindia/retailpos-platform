<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\UpdateHomepageSectionRequest;
use App\Repositories\Cms\CmsHomepageRepository;
use App\Services\Cms\CmsHomepageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsHomepageController extends Controller
{
    public function index(Request $request, CmsHomepageService $homepageService, CmsHomepageRepository $homepageRepository): View
    {
        $homepageService->ensureDefaultSections($request->user()->company_id);

        return view('command-center.cms.homepage.index', [
            'sections' => $homepageRepository->sectionsForCompany($request->user()->company_id),
        ]);
    }

    public function update(UpdateHomepageSectionRequest $request, CmsHomepageRepository $homepageRepository, CmsHomepageService $homepageService, string $section): RedirectResponse
    {
        $homepageSection = $homepageRepository->findSection($request->user()->company_id, $section);

        abort_unless($homepageSection, 404);

        $homepageService->updateSection($homepageSection, $request->user(), $request->validated());

        return back()->with('status', 'Homepage section updated.');
    }
}
