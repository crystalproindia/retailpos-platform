<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreCmsMediaFolderRequest;
use App\Http\Requests\Cms\StoreCmsMediaRequest;
use App\Models\Cms\CmsMedia;
use App\Repositories\Cms\CmsMediaRepository;
use App\Services\Cms\CmsMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsMediaController extends Controller
{
    public function index(Request $request, CmsMediaRepository $mediaRepository): View
    {
        return view('command-center.cms.media.index', [
            'media' => $mediaRepository->paginateForCompany($request->user()->company_id, $request->only(['search', 'type', 'folder_id', 'trashed'])),
            'folders' => $mediaRepository->foldersForCompany($request->user()->company_id),
        ]);
    }

    public function storeFolder(StoreCmsMediaFolderRequest $request, CmsMediaService $mediaService): RedirectResponse
    {
        $mediaService->createFolder($request->user(), $request->validated());

        return back()->with('status', 'Media folder created.');
    }

    public function store(StoreCmsMediaRequest $request, CmsMediaService $mediaService): RedirectResponse
    {
        $mediaService->upload($request->user(), $request->file('file'), $request->validated());

        return back()->with('status', 'Media uploaded.');
    }

    public function destroy(Request $request, CmsMediaService $mediaService, int $media): RedirectResponse
    {
        $item = CmsMedia::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($media);

        $mediaService->delete($item);

        return back()->with('status', 'Media moved to trash.');
    }
}
