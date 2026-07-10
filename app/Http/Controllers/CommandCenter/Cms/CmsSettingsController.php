<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\UpdateCmsFooterRequest;
use App\Http\Requests\Cms\UpdateCmsSettingsRequest;
use App\Repositories\Cms\CmsSettingsRepository;
use App\Services\Cms\CmsSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsSettingsController extends Controller
{
    public function index(Request $request, CmsSettingsRepository $settingsRepository, CmsSettingsService $settingsService): View
    {
        $settingsService->ensureDefaultSettings($request->user()->company_id);

        return view('command-center.cms.settings.index', [
            'definitions' => config('cms.settings'),
            'settings' => $settingsRepository->settingsForCompany($request->user()->company_id),
            'footer' => $settingsRepository->footerForCompany($request->user()->company_id),
            'socialLinks' => $settingsRepository->socialLinksForCompany($request->user()->company_id),
        ]);
    }

    public function update(UpdateCmsSettingsRequest $request, CmsSettingsService $settingsService): RedirectResponse
    {
        $settingsService->updateSettings($request->user(), $request->validated());

        return back()->with('status', 'CMS settings updated.');
    }

    public function updateFooter(UpdateCmsFooterRequest $request, CmsSettingsRepository $settingsRepository, CmsSettingsService $settingsService): RedirectResponse
    {
        $settingsService->updateFooter(
            $settingsRepository->footerForCompany($request->user()->company_id),
            $request->user(),
            $request->validated(),
        );

        return back()->with('status', 'Footer settings updated.');
    }
}
