<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsRedirectRequest;
use App\Repositories\Cms\CmsMarketingRepository;
use App\Services\Cms\CmsRedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsRedirectController extends Controller
{
    public function index(Request $request, CmsMarketingRepository $cms): View { return view('command-center.cms.marketing.redirects.index', ['redirects' => $cms->redirects($request->user()->company_id)]); }
    public function store(CmsRedirectRequest $request, CmsRedirectService $service): RedirectResponse { $service->create($request->user(), $request->validated()); return back()->with('status', 'Redirect created.'); }
    public function update(CmsRedirectRequest $request, CmsMarketingRepository $cms, CmsRedirectService $service, int $redirect): RedirectResponse { $service->update($cms->redirect($request->user()->company_id, $redirect), $request->user(), $request->validated()); return back()->with('status', 'Redirect updated.'); }
    public function destroy(Request $request, CmsMarketingRepository $cms, CmsRedirectService $service, int $redirect): RedirectResponse { $service->delete($cms->redirect($request->user()->company_id, $redirect)); return back()->with('status', 'Redirect deleted.'); }
}
