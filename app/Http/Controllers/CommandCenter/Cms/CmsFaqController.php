<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsFaqRequest;
use App\Repositories\Cms\CmsFaqRepository;
use App\Services\Cms\CmsFaqService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsFaqController extends Controller
{
    public function index(Request $request, CmsFaqRepository $faqs): View { return view('command-center.cms.faqs.index', ['faqs' => $faqs->paginateForCompany($request->user()->company_id, $request->only(['search', 'trashed']))]); }
    public function store(CmsFaqRequest $request, CmsFaqService $service): RedirectResponse { $service->create($request->user(), $this->payload($request->validated())); return back()->with('status', 'FAQ created.'); }
    public function update(CmsFaqRequest $request, CmsFaqRepository $faqs, CmsFaqService $service, int $faq): RedirectResponse { $service->update($faqs->findForCompany($request->user()->company_id, $faq), $this->payload($request->validated())); return back()->with('status', 'FAQ updated.'); }
    public function destroy(Request $request, CmsFaqRepository $faqs, CmsFaqService $service, int $faq): RedirectResponse { $service->delete($faqs->findForCompany($request->user()->company_id, $faq)); return back()->with('status', 'FAQ moved to trash.'); }
    public function restore(Request $request, CmsFaqRepository $faqs, CmsFaqService $service, int $faq): RedirectResponse { $service->restore($faqs->findForCompany($request->user()->company_id, $faq, true)); return back()->with('status', 'FAQ restored.'); }
    /** @param array<string, mixed> $data @return array<string, mixed> */ private function payload(array $data): array { if (array_key_exists('is_active', $data)) $data['is_active'] = (bool) $data['is_active']; return $data; }
}
