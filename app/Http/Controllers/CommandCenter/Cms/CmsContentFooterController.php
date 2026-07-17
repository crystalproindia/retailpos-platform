<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsContentFooterRequest;
use App\Repositories\Cms\CmsContentNavigationRepository;
use App\Services\Cms\CmsContentEditorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsContentFooterController extends Controller
{
    public function index(Request $request, CmsContentNavigationRepository $content): View { return view('command-center.cms.content.footer', ['blocks' => $content->footerBlocks($request->user()->company_id), 'blockKeys' => config('cms-content.footer_blocks')]); }
    public function store(CmsContentFooterRequest $request, CmsContentEditorService $editor): RedirectResponse { $editor->saveFooter($request->user(), $request->validated()); return back()->with('status', 'Footer block added.'); }
    public function update(CmsContentFooterRequest $request, CmsContentNavigationRepository $content, CmsContentEditorService $editor, int $block): RedirectResponse { $editor->saveFooter($request->user(), $request->validated(), $content->footerBlock($request->user()->company_id, $block)); return back()->with('status', 'Footer block saved.'); }
}
