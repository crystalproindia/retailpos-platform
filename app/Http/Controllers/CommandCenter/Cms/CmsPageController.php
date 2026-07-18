<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsPageSectionRequest;
use App\Http\Requests\Cms\StoreCmsPageRequest;
use App\Http\Requests\Cms\UpdateCmsPageRequest;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsRevision;
use App\Repositories\Cms\CmsPageRepository;
use App\Repositories\Cms\CmsPageSectionRepository;
use App\Services\Cms\CmsPageSectionService;
use App\Services\Cms\CmsPageService;
use App\Services\Cms\CmsPreviewService;
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
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function create(Request $request): View
    {
        return view('command-center.cms.pages.create', [
            'page' => new CmsPage(['status' => CmsPage::STATUS_DRAFT]),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(StoreCmsPageRequest $request, CmsPageService $pageService): RedirectResponse
    {
        $page = $pageService->create($request->user(), $request->validated());

        return redirect()->route($this->routePrefix($request).'.pages.edit', $page)->with('status', 'CMS page created.');
    }

    public function edit(Request $request, CmsPageRepository $pageRepository, int $page): View
    {
        return view('command-center.cms.pages.edit', [
            'page' => $pageRepository->findForCompany($request->user()->company_id, $page, true),
            'routePrefix' => $this->routePrefix($request),
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

    public function revisions(Request $request, CmsPageRepository $pageRepository, int $page): View
    {
        $cmsPage = $pageRepository->findForCompany($request->user()->company_id, $page);
        return view('command-center.cms.revisions.index', ['entity' => $cmsPage, 'revisions' => CmsRevision::query()->with('creator')->where('company_id', $request->user()->company_id)->where('revisionable_type', CmsPage::class)->where('revisionable_id', $cmsPage->id)->latest('revision_number')->get(), 'routePrefix' => $this->routePrefix($request), 'entityType' => 'pages']);
    }

    public function restoreRevision(Request $request, CmsPageRepository $pageRepository, CmsPageService $pageService, int $page, int $revision): RedirectResponse
    {
        $cmsPage = $pageRepository->findForCompany($request->user()->company_id, $page);
        $item = CmsRevision::query()->where('company_id', $request->user()->company_id)->where('revisionable_type', CmsPage::class)->where('revisionable_id', $cmsPage->id)->findOrFail($revision);
        $pageService->restoreRevision($cmsPage, $item, $request->user());
        return back()->with('status', 'Revision restored as a draft. Review and publish when ready.');
    }

    public function preview(Request $request, CmsPageRepository $pageRepository, CmsPreviewService $previews, int $page): RedirectResponse
    {
        $link = $previews->create($pageRepository->findForCompany($request->user()->company_id, $page), $request->user());
        return back()->with('preview_url', $link['url'])->with('status', 'Draft preview link created. It expires in 30 minutes.');
    }

    public function revokePreview(Request $request, CmsPageRepository $pageRepository, CmsPreviewService $previews, int $page): RedirectResponse
    {
        $previews->revoke($pageRepository->findForCompany($request->user()->company_id, $page), $request->user());
        return back()->with('status', 'Draft preview links revoked.');
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

    public function moveSection(Request $request, CmsPageRepository $pageRepository, CmsPageSectionRepository $sectionRepository, CmsPageSectionService $sectionService, int $page, int $section): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $pageRepository->findForCompany($request->user()->company_id, $page);
        $sectionService->move($sectionRepository->findForPage($request->user()->company_id, $page, $section), $direction);

        return back()->with('status', 'Page section reordered.');
    }

    public function destroySection(Request $request, CmsPageRepository $pageRepository, CmsPageSectionRepository $sectionRepository, CmsPageSectionService $sectionService, int $page, int $section): RedirectResponse
    {
        $pageRepository->findForCompany($request->user()->company_id, $page);
        $sectionService->delete($sectionRepository->findForPage($request->user()->company_id, $page, $section));

        return back()->with('status', 'Page section removed.');
    }

    private function routePrefix(Request $request): string
    {
        return $request->routeIs('website.*') ? 'website' : 'cms';
    }
}
