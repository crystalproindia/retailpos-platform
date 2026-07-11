<?php

namespace App\Http\Controllers\CommandCenter\Operations;

use App\Http\Controllers\Controller;
use App\Repositories\Operations\HealthCheckRepository;
use App\Services\AuditLogger;
use App\Services\Operations\HealthCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HealthCheckController extends Controller
{
    public function index(Request $request, HealthCheckRepository $healthChecks, HealthCheckService $healthCheckService): View
    {
        $latestChecks = $healthChecks->latestByKey();

        return view('command-center.operations.health.index', [
            'checks' => $healthChecks->paginate($request->only(['status', 'category', 'search'])),
            'latestChecks' => $latestChecks,
            'overallStatus' => $latestChecks->isEmpty() ? 'unknown' : $healthCheckService->overallStatus($latestChecks),
            'categories' => $latestChecks->pluck('category')->unique()->sort()->values(),
        ]);
    }

    public function run(Request $request, HealthCheckService $healthCheckService, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('operations.settings.manage'), 403);

        $checks = $healthCheckService->runAll();

        $auditLogger->record('operations.health_check.run', null, 'Operations health check manually run', [
            'company_id' => $request->user()->company_id,
            'check_count' => $checks->count(),
            'overall_status' => $healthCheckService->overallStatus($checks),
        ]);

        return back()->with('status', 'Health checks completed.');
    }
}
