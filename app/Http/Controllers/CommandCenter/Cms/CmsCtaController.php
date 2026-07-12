<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsCtaRequest;
use App\Repositories\Cms\CmsCtaRepository;
use App\Services\Cms\CmsCtaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsCtaController extends Controller
{
    public function index(Request $request, CmsCtaRepository $ctas): View { return view('command-center.cms.ctas.index', ['ctas' => $ctas->paginateForCompany($request->user()->company_id, $request->only(['trashed']))]); }
    public function store(CmsCtaRequest $request, CmsCtaService $service): RedirectResponse { $service->create($request->user(), $this->payload($request->validated())); return back()->with('status', 'CTA block created.'); }
    public function update(CmsCtaRequest $request, CmsCtaRepository $ctas, CmsCtaService $service, int $cta): RedirectResponse { $service->update($ctas->findForCompany($request->user()->company_id, $cta), $request->user(), $this->payload($request->validated())); return back()->with('status', 'CTA block updated.'); }
    public function destroy(Request $request, CmsCtaRepository $ctas, CmsCtaService $service, int $cta): RedirectResponse { $service->delete($ctas->findForCompany($request->user()->company_id, $cta)); return back()->with('status', 'CTA block moved to trash.'); }
    public function restore(Request $request, CmsCtaRepository $ctas, CmsCtaService $service, int $cta): RedirectResponse { $service->restore($ctas->findForCompany($request->user()->company_id, $cta, true)); return back()->with('status', 'CTA block restored.'); }
    /** @param array<string, mixed> $data @return array<string, mixed> */ private function payload(array $data): array { if (array_key_exists('is_active', $data)) $data['is_active'] = (bool) $data['is_active']; return $data; }
}
