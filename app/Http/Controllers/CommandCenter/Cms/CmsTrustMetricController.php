<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsTrustMetricRequest;
use App\Repositories\Cms\CmsTrustMetricRepository;
use App\Services\Cms\CmsTrustMetricService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsTrustMetricController extends Controller
{
    public function index(Request $request, CmsTrustMetricRepository $metrics): View { return view('command-center.cms.trust-metrics.index', ['metrics' => $metrics->paginateForCompany($request->user()->company_id, $request->only(['trashed']))]); }
    public function store(CmsTrustMetricRequest $request, CmsTrustMetricService $service): RedirectResponse { $service->create($request->user(), $this->payload($request->validated())); return back()->with('status', 'Trust metric created.'); }
    public function update(CmsTrustMetricRequest $request, CmsTrustMetricRepository $metrics, CmsTrustMetricService $service, int $metric): RedirectResponse { $service->update($metrics->findForCompany($request->user()->company_id, $metric), $request->user(), $this->payload($request->validated())); return back()->with('status', 'Trust metric updated.'); }
    public function destroy(Request $request, CmsTrustMetricRepository $metrics, CmsTrustMetricService $service, int $metric): RedirectResponse { $service->delete($metrics->findForCompany($request->user()->company_id, $metric)); return back()->with('status', 'Trust metric moved to trash.'); }
    public function restore(Request $request, CmsTrustMetricRepository $metrics, CmsTrustMetricService $service, int $metric): RedirectResponse { $service->restore($metrics->findForCompany($request->user()->company_id, $metric, true)); return back()->with('status', 'Trust metric restored.'); }
    /** @param array<string, mixed> $data @return array<string, mixed> */ private function payload(array $data): array { foreach (['show_on_homepage', 'is_active'] as $key) if (array_key_exists($key, $data)) $data[$key] = (bool) $data[$key]; return $data; }
}
