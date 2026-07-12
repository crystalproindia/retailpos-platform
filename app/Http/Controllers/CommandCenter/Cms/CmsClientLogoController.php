<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsClientLogoRequest;
use App\Models\Cms\CmsMedia;
use App\Repositories\Cms\CmsClientLogoRepository;
use App\Services\Cms\CmsClientLogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsClientLogoController extends Controller
{
    public function index(Request $request, CmsClientLogoRepository $logos): View { return view('command-center.cms.client-logos.index', ['logos' => $logos->paginateForCompany($request->user()->company_id, $request->only(['search', 'trashed'])), 'media' => CmsMedia::query()->where('company_id', $request->user()->company_id)->orderBy('filename')->get()]); }
    public function store(CmsClientLogoRequest $request, CmsClientLogoService $service): RedirectResponse { $service->create($request->user(), $this->payload($request->validated())); return back()->with('status', 'Client logo created.'); }
    public function update(CmsClientLogoRequest $request, CmsClientLogoRepository $logos, CmsClientLogoService $service, int $logo): RedirectResponse { $service->update($logos->findForCompany($request->user()->company_id, $logo), $request->user(), $this->payload($request->validated())); return back()->with('status', 'Client logo updated.'); }
    public function destroy(Request $request, CmsClientLogoRepository $logos, CmsClientLogoService $service, int $logo): RedirectResponse { $service->delete($logos->findForCompany($request->user()->company_id, $logo)); return back()->with('status', 'Client logo moved to trash.'); }
    public function restore(Request $request, CmsClientLogoRepository $logos, CmsClientLogoService $service, int $logo): RedirectResponse { $service->restore($logos->findForCompany($request->user()->company_id, $logo, true)); return back()->with('status', 'Client logo restored.'); }
    /** @param array<string, mixed> $data @return array<string, mixed> */ private function payload(array $data): array { foreach (['is_featured', 'show_on_homepage', 'show_on_case_studies', 'is_active'] as $key) if (array_key_exists($key, $data)) $data[$key] = (bool) $data[$key]; return $data; }
}
