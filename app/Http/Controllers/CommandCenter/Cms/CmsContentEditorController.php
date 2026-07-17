<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsContentSectionRequest;
use App\Http\Requests\Cms\StoreCmsContentPageRequest;
use App\Http\Requests\Cms\UpdateCmsContentPageRequest;
use App\Models\Cms\CmsContentPage;
use App\Repositories\Cms\CmsContentPageRepository;
use App\Services\Cms\CmsContentEditorService;
use App\Services\Cms\PublicCmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsContentEditorController extends Controller
{
    public function index(Request $request, CmsContentPageRepository $pages, CmsContentEditorService $editor): View
    {
        $editor->ensureHomepage($request->user());
        return view('command-center.cms.content.pages.index', ['pages' => $pages->paginate($request->user()->company_id, $request->only(['search', 'status', 'page_type'])), 'pageTypes' => config('cms-content.page_types')]);
    }

    public function store(StoreCmsContentPageRequest $request, CmsContentEditorService $editor): RedirectResponse
    {
        $page = $editor->createPage($request->user(), $request->validated());
        return redirect()->route('cms.content.pages.show', $page)->with('status', 'Content page created. Add the sections your visitors should see.');
    }

    public function show(Request $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page): View
    {
        $contentPage = $pages->find($request->user()->company_id, $page);
        return view('command-center.cms.content.pages.show', ['page' => $contentPage, 'health' => $editor->health($contentPage), 'sectionTypes' => config('cms-content.section_types')]);
    }

    public function update(UpdateCmsContentPageRequest $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page): RedirectResponse
    {
        $editor->updatePage($pages->find($request->user()->company_id, $page), $request->user(), $request->validated());
        return back()->with('status', 'Page details saved.');
    }

    public function publish(Request $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page): RedirectResponse
    {
        abort_unless($request->user()->can('cms.content.publish'), 403);
        $editor->publish($pages->find($request->user()->company_id, $page), $request->user());
        return back()->with('status', 'Page published. Only enabled sections are available in the public API.');
    }

    public function unpublish(Request $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page): RedirectResponse
    {
        abort_unless($request->user()->can('cms.content.publish'), 403);
        $editor->unpublish($pages->find($request->user()->company_id, $page), $request->user());
        return back()->with('status', 'Page moved back to draft.');
    }

    public function archive(Request $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page): RedirectResponse
    {
        abort_unless($request->user()->can('cms.content.delete'), 403);
        $editor->archive($pages->find($request->user()->company_id, $page), $request->user());
        return redirect()->route('cms.content.pages.index')->with('status', 'Page archived. It is no longer available publicly.');
    }

    public function storeSection(CmsContentSectionRequest $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page): RedirectResponse
    {
        $editor->createSection($pages->find($request->user()->company_id, $page), $request->user(), $request->validated());
        return back()->with('status', 'Section added.');
    }

    public function updateSection(CmsContentSectionRequest $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page, int $section): RedirectResponse
    {
        $contentPage = $pages->find($request->user()->company_id, $page);
        $editor->updateSection($pages->findSection($contentPage, $section), $request->validated());
        return back()->with('status', 'Section saved.');
    }

    public function toggleSection(Request $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page, int $section): RedirectResponse
    {
        $request->validate(['is_enabled' => ['required', 'boolean']]);
        $contentPage = $pages->find($request->user()->company_id, $page);
        $editor->toggleSection($pages->findSection($contentPage, $section), (bool) $request->boolean('is_enabled'));
        return back()->with('status', $request->boolean('is_enabled') ? 'Section enabled.' : 'Section disabled.');
    }

    public function moveSection(Request $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page, int $section): RedirectResponse
    {
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        $contentPage = $pages->find($request->user()->company_id, $page);
        $editor->moveSection($pages->findSection($contentPage, $section), $data['direction']);
        return back()->with('status', 'Section order updated.');
    }

    public function destroySection(Request $request, CmsContentPageRepository $pages, CmsContentEditorService $editor, int $page, int $section): RedirectResponse
    {
        abort_unless($request->user()->can('cms.content.delete'), 403);
        $contentPage = $pages->find($request->user()->company_id, $page);
        $editor->deleteSection($pages->findSection($contentPage, $section));
        return back()->with('status', 'Section removed.');
    }

    public function preview(Request $request, CmsContentPageRepository $pages, PublicCmsService $public, int $page): JsonResponse
    {
        $contentPage = $pages->find($request->user()->company_id, $page);
        return response()->json(['data' => $public->contentPreview($contentPage)]);
    }
}
