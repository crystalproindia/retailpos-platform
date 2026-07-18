<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsCaseStudyRequest;
use App\Models\Cms\CmsMedia;
use App\Repositories\Cms\CmsCaseStudyRepository;
use App\Services\Cms\CmsCaseStudyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsCaseStudyController extends Controller
{
    public function index(Request $request, CmsCaseStudyRepository $studies): View { return view('command-center.cms.case-studies.index', ['studies' => $studies->paginateForCompany($request->user()->company_id, $request->only(['search', 'status', 'trashed'])), 'routePrefix' => $this->routePrefix($request)]); }
    public function create(Request $request): View { return view('command-center.cms.case-studies.create', ['study' => null, 'media' => CmsMedia::query()->where('company_id', $request->user()->company_id)->orderBy('file_name')->get(), 'routePrefix' => $this->routePrefix($request)]); }
    public function store(CmsCaseStudyRequest $request, CmsCaseStudyService $service): RedirectResponse { $study = $service->create($request->user(), $this->payload($request->validated())); return redirect()->route($this->routePrefix($request).'.case-studies.edit', $study)->with('status', 'Case study created as '.str($study->status)->headline().'.'); }
    public function edit(Request $request, CmsCaseStudyRepository $studies, int $caseStudy): View { return view('command-center.cms.case-studies.edit', ['study' => $studies->findForCompany($request->user()->company_id, $caseStudy), 'media' => CmsMedia::query()->where('company_id', $request->user()->company_id)->orderBy('file_name')->get(), 'routePrefix' => $this->routePrefix($request)]); }
    public function update(CmsCaseStudyRequest $request, CmsCaseStudyRepository $studies, CmsCaseStudyService $service, int $caseStudy): RedirectResponse { $study = $service->update($studies->findForCompany($request->user()->company_id, $caseStudy), $request->user(), $this->payload($request->validated())); return redirect()->route($this->routePrefix($request).'.case-studies.edit', $study)->with('status', 'Case study saved.'); }
    public function publish(Request $request, CmsCaseStudyRepository $studies, CmsCaseStudyService $service, int $caseStudy): RedirectResponse { $service->publish($studies->findForCompany($request->user()->company_id, $caseStudy), $request->user()); return back()->with('status', 'Case study published.'); }
    public function unpublish(Request $request, CmsCaseStudyRepository $studies, CmsCaseStudyService $service, int $caseStudy): RedirectResponse { $service->unpublish($studies->findForCompany($request->user()->company_id, $caseStudy), $request->user()); return back()->with('status', 'Case study unpublished.'); }
    public function destroy(Request $request, CmsCaseStudyRepository $studies, CmsCaseStudyService $service, int $caseStudy): RedirectResponse { $service->delete($studies->findForCompany($request->user()->company_id, $caseStudy)); return redirect()->route('cms.case-studies.index')->with('status', 'Case study moved to trash.'); }
    public function restore(Request $request, CmsCaseStudyRepository $studies, CmsCaseStudyService $service, int $caseStudy): RedirectResponse { $study = $service->restore($studies->findForCompany($request->user()->company_id, $caseStudy, true)); return redirect()->route('cms.case-studies.edit', $study)->with('status', 'Case study restored.'); }
    /** @param array<string, mixed> $data @return array<string, mixed> */ private function payload(array $data): array { $data['is_featured'] = (bool) ($data['is_featured'] ?? false); return $data; }
    private function routePrefix(Request $request): string { return $request->routeIs('website.*') ? 'website' : 'cms'; }
}
