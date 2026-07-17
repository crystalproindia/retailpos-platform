<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsContentNavigationRequest;
use App\Repositories\Cms\CmsContentNavigationRepository;
use App\Services\Cms\CmsContentEditorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsContentNavigationController extends Controller
{
    public function index(Request $request, CmsContentNavigationRepository $content): View { return view('command-center.cms.content.navigation', ['items' => $content->navigation($request->user()->company_id), 'locations' => config('cms-content.navigation_locations')]); }
    public function store(CmsContentNavigationRequest $request, CmsContentEditorService $editor): RedirectResponse { $editor->saveNavigation($request->user(), $request->validated()); return back()->with('status', 'Navigation item added.'); }
    public function update(CmsContentNavigationRequest $request, CmsContentNavigationRepository $content, CmsContentEditorService $editor, int $item): RedirectResponse { $editor->saveNavigation($request->user(), $request->validated(), $content->navigationItem($request->user()->company_id, $item)); return back()->with('status', 'Navigation item saved.'); }
}
