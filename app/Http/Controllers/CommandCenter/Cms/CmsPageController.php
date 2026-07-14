<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsPageSectionRequest;
use App\Http\Requests\Cms\StoreCmsPageRequest;
use App\Http\Requests\Cms\UpdateCmsPageRequest;
use App\Models\Cms\CmsPage;
use App\Repositories\Cms\CmsPageRepository;
use App\Repositories\Cms\CmsPageSectionRepository;
use App\Services\Cms\CmsPageSectionService;
use App\Services\Cms\CmsPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    public function index(Request $request, CmsPageRepository $pageRepository): View
    {
        return view('command-center.cms.pages.index', [
            'pages' => $pageRepository->paginateForCompany($request->user()->company_id, $request->only(['search', 'status', 'page_type', 'trashed'])),
            'statuses' => [CmsPage::STATUS_DRAFT, CmsPage::STATUS_PUBLISHED, CmsPage::STATUS_SCHEDULED, CmsPage::STATUS_ARCHIVED],
            'pageTypes' => ['standard', 'landing', 'product', 'module', 'industry', 'solution', 'legal'],
        ]);
    }

    public function create(): View
    {
        return view('command-center.cms.pages.create', [
            'page' => new CmsPage(['status' => CmsPage::STATUS_DRAFT]),
        ]);
    }

    public function store(StoreCmsPageRequest $request, CmsPageService $pageService): RedirectResponse
    {
        $page = $pageService->create($request->user(), $request->validated());

        return redirect()->route('cms.pages.edit', $page)->with('status', 'CMS page created.');
    }

    public function edit(Request $request, CmsPageRepository $pageRepository, int $page): View
    {
        return view('command-center.cms.pages.edit', [
            'page' => $pageRepository->findForCompany($request->user()->company_id, $page, true),
        ]);
    }

    public function update(UpdateCmsPageRequest $request, CmsPageRepository $pageRepository, CmsPageService $pageService, int $page): RedirectResponse
    {
        $cmsPage = $pageRepository->findForCompany($request->user()->company_id, $page);
        $pageService->update($cmsPage, $request->user(), $request->validated());

        return back()->with('status', 'CMS page updated.');
    }

    public function destroy(Request $request, CmsPageRepository $pageRepository, CmsPageService $pageService, int $page): RedirectResponse
    {
        $pageService->delete($pageRepository->findForCompany($request->user()->company_id, $page));

        return redirect()->route('cms.pages.index')->with('status', 'CMS page moved to trash.');
    }

    public function restore(Request $request, CmsPageRepository $pageRepository, CmsPageService $pageService, int $page): RedirectResponse
    {
        $pageService->restore($pageRepository->findForCompany($request->user()->company_id, $page, true));

        return back()->with('status', 'CMS page restored.');
    }

    public function publish(Request $request, CmsPageRepository $pageRepository, CmsPageService $pageService, int $page): RedirectResponse
    {
        $pageService->publish($pageRepository->findForCompany($request->user()->company_id, $page), $request->user());

        return back()->with('status', 'CMS page published.');
    }

    public function unpublish(Request $request, CmsPageRepository $pageRepository, CmsPageService $pageService, int $page): RedirectResponse
    {
        $pageService->unpublish($pageRepository->findForCompany($request->user()->company_id, $page), $request->user());

        return back()->with('status', 'CMS page unpublished.');
    }

    public function bulk(Request $request, CmsPageRepository $pageRepository, CmsPageService $pageService): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:delete,publish,unpublish'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        collect($validated['ids'])->each(function (int $pageId) use ($request, $pageRepository, $pageService, $validated): void {
            $page = $pageRepository->findForCompany($request->user()->company_id, $pageId);

            match ($validated['action']) {
                'publish' => $pageService->publish($page, $request->user()),
                'unpublish' => $pageService->unpublish($page, $request->user()),
                default => $pageService->delete($page),
            };
        });

        return back()->with('status', 'Bulk action completed.');
    }

    public function storeSection(CmsPageSectionRequest $request, CmsPageRepository $pageRepository, CmsPageSectionService $sectionService, int $page): RedirectResponse
    {
        $cmsPage = $pageRepository->findForCompany($request->user()->company_id, $page);
        $sectionService->create($cmsPage, $request->user(), $request->validated());

        return back()->with('status', 'Page section added.');
    }

    public function updateSection(CmsPageSectionRequest $request, CmsPageRepository $pageRepository, CmsPageSectionRepository $sectionRepository, CmsPageSectionService $sectionService, int $page, int $section): RedirectResponse
    {
        $pageRepository->findForCompany($request->user()->company_id, $page);
        $sectionService->update($sectionRepository->findForPage($request->user()->company_id, $page, $section), $request->validated());

        return back()->with('status', 'Page section updated.');
    }

    public function destroySection(Request $request, CmsPageRepository $pageRepository, CmsPageSectionRepository $sectionRepository, CmsPageSectionService $sectionService, int $page, int $section): RedirectResponse
    {
        $pageRepository->findForCompany($request->user()->company_id, $page);
        $sectionService->delete($sectionRepository->findForPage($request->user()->company_id, $page, $section));

        return back()->with('status', 'Page section removed.');
    }
}
