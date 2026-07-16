<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsArticleRequest;
use App\Models\Cms\CmsArticle;
use App\Repositories\Cms\CmsMarketingRepository;
use App\Services\Cms\CmsArticleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsArticleController extends Controller
{
    public function index(Request $request, CmsMarketingRepository $cms): View { return view('command-center.cms.marketing.articles.index', ['articles' => $cms->articles($request->user()->company_id, $request->only(['search', 'status']))]); }
    public function create(): View { return view('command-center.cms.marketing.articles.form', ['article' => new CmsArticle(['status' => CmsArticle::STATUS_DRAFT])]); }
    public function store(CmsArticleRequest $request, CmsArticleService $service): RedirectResponse { $article = $service->create($request->user(), $request->validated()); return redirect()->route('cms.articles.edit', $article)->with('status', 'Article created.'); }
    public function edit(Request $request, CmsMarketingRepository $cms, int $article): View { return view('command-center.cms.marketing.articles.form', ['article' => $cms->article($request->user()->company_id, $article)]); }
    public function update(CmsArticleRequest $request, CmsMarketingRepository $cms, CmsArticleService $service, int $article): RedirectResponse { $service->update($cms->article($request->user()->company_id, $article), $request->user(), $request->validated()); return back()->with('status', 'Article updated.'); }
    public function publish(Request $request, CmsMarketingRepository $cms, CmsArticleService $service, int $article): RedirectResponse { $service->publish($cms->article($request->user()->company_id, $article), $request->user()); return back()->with('status', 'Article published.'); }
    public function unpublish(Request $request, CmsMarketingRepository $cms, CmsArticleService $service, int $article): RedirectResponse { $service->unpublish($cms->article($request->user()->company_id, $article), $request->user()); return back()->with('status', 'Article unpublished.'); }
    public function archive(Request $request, CmsMarketingRepository $cms, CmsArticleService $service, int $article): RedirectResponse { $service->archive($cms->article($request->user()->company_id, $article), $request->user()); return back()->with('status', 'Article archived.'); }
}
