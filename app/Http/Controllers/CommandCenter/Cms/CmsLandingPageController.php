<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsMarketingPageRequest;
use App\Repositories\Cms\CmsMarketingRepository;
use App\Services\Cms\CmsMarketingPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsLandingPageController extends Controller
{
    public function index(Request $request, CmsMarketingRepository $pages): View { return view('command-center.cms.marketing.pages.index', ['pages' => $pages->pages($request->user()->company_id, 'landing', $request->only(['search', 'status', 'page_type'])), 'kind' => 'landing']); }
    public function create(): View { return view('command-center.cms.marketing.pages.form', ['page' => null, 'kind' => 'landing']); }
    public function store(CmsMarketingPageRequest $request, CmsMarketingPageService $service): RedirectResponse { $page = $service->create($request->user(), $request->validated()); return redirect()->route('cms.landing-pages.edit', $page)->with('status', 'Landing page created.'); }
    public function edit(Request $request, CmsMarketingRepository $pages, int $page): View { return view('command-center.cms.marketing.pages.form', ['page' => $pages->page($request->user()->company_id, $page), 'kind' => 'landing']); }
    public function update(CmsMarketingPageRequest $request, CmsMarketingRepository $pages, CmsMarketingPageService $service, int $page): RedirectResponse { $service->update($pages->page($request->user()->company_id, $page), $request->user(), $request->validated()); return back()->with('status', 'Landing page updated.'); }
    public function publish(Request $request, CmsMarketingRepository $pages, CmsMarketingPageService $service, int $page): RedirectResponse { $service->publish($pages->page($request->user()->company_id, $page), $request->user()); return back()->with('status', 'Landing page published.'); }
    public function unpublish(Request $request, CmsMarketingRepository $pages, CmsMarketingPageService $service, int $page): RedirectResponse { $service->unpublish($pages->page($request->user()->company_id, $page), $request->user()); return back()->with('status', 'Landing page unpublished.'); }
    public function archive(Request $request, CmsMarketingRepository $pages, CmsMarketingPageService $service, int $page): RedirectResponse { $service->archive($pages->page($request->user()->company_id, $page), $request->user()); return back()->with('status', 'Landing page archived.'); }
}
