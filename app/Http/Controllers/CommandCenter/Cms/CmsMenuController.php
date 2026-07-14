<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreCmsMenuItemRequest;
use App\Http\Requests\Cms\StoreCmsMenuRequest;
use App\Repositories\Cms\CmsMenuRepository;
use App\Services\Cms\CmsMenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsMenuController extends Controller
{
    public function index(Request $request, CmsMenuRepository $menuRepository): View
    {
        return view('command-center.cms.menus.index', [
            'menus' => $menuRepository->paginateForCompany($request->user()->company_id, $request->only(['location', 'trashed'])),
            'locations' => config('cms.menu_locations'),
        ]);
    }

    public function store(StoreCmsMenuRequest $request, CmsMenuService $menuService): RedirectResponse
    {
        $menuService->createMenu($request->user(), $request->validated());

        return back()->with('status', 'CMS menu created.');
    }

    public function update(StoreCmsMenuRequest $request, CmsMenuRepository $menuRepository, CmsMenuService $menuService, int $menu): RedirectResponse
    {
        $menuService->updateMenu($menuRepository->findForCompany($request->user()->company_id, $menu), $request->validated());

        return back()->with('status', 'CMS menu updated.');
    }

    public function destroy(Request $request, CmsMenuRepository $menuRepository, int $menu): RedirectResponse
    {
        $menuRepository->findForCompany($request->user()->company_id, $menu)->delete();

        return back()->with('status', 'CMS menu moved to trash.');
    }

    public function restore(Request $request, CmsMenuRepository $menuRepository, CmsMenuService $menuService, int $menu): RedirectResponse
    {
        $menuService->restoreMenu($menuRepository->findForCompany($request->user()->company_id, $menu, true));

        return back()->with('status', 'CMS menu restored.');
    }

    public function storeItem(StoreCmsMenuItemRequest $request, CmsMenuRepository $menuRepository, CmsMenuService $menuService, int $menu): RedirectResponse
    {
        $menuService->addItem($menuRepository->findForCompany($request->user()->company_id, $menu), $request->validated());

        return back()->with('status', 'CMS menu item added.');
    }

    public function updateItem(StoreCmsMenuItemRequest $request, CmsMenuRepository $menuRepository, CmsMenuService $menuService, int $menu, int $item): RedirectResponse
    {
        $cmsMenu = $menuRepository->findForCompany($request->user()->company_id, $menu);
        $menuService->updateItem($cmsMenu, $menuRepository->findItemForMenu($cmsMenu, $item), $request->validated());

        return back()->with('status', 'CMS menu item updated.');
    }
}
