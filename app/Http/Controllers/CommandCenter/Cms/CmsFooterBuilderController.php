<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\UpdateCmsFooterRequest;
use App\Repositories\Cms\CmsSettingsRepository;
use App\Services\Cms\CmsSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsFooterBuilderController extends Controller
{
    public function index(Request $request, CmsSettingsRepository $settings): View { return view('command-center.cms.footer.index', ['footer' => $settings->footerForCompany($request->user()->company_id), 'socialLinks' => $settings->socialLinksForCompany($request->user()->company_id)]); }
    public function update(UpdateCmsFooterRequest $request, CmsSettingsRepository $settings, CmsSettingsService $service): RedirectResponse { $service->updateFooter($settings->footerForCompany($request->user()->company_id), $request->user(), $request->validated()); return back()->with('status', 'Footer builder updated.'); }
}
