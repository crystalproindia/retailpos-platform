<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsThemeRequest;
use App\Repositories\Cms\CmsThemeRepository;
use App\Services\Cms\CmsThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsThemeController extends Controller
{
    public function index(Request $request, CmsThemeRepository $themes): View { return view('command-center.cms.theme.index', ['theme' => $themes->forCompany($request->user()->company_id)]); }
    public function update(CmsThemeRequest $request, CmsThemeRepository $themes, CmsThemeService $service): RedirectResponse { $service->update($themes->forCompany($request->user()->company_id), $request->user(), $request->validated()); return back()->with('status', 'Website theme updated.'); }
}
