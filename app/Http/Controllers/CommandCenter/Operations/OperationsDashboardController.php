<?php

namespace App\Http\Controllers\CommandCenter\Operations;

use App\Http\Controllers\Controller;
use App\Models\DomainEventLog;
use App\Models\NotificationDelivery;
use App\Models\WebhookDelivery;
use App\Repositories\Operations\FailedJobRepository;
use App\Repositories\Operations\HealthCheckRepository;
use App\Repositories\Operations\QueueSnapshotRepository;
use App\Services\Operations\ApplicationInfoService;
use App\Services\Operations\HealthCheckService;
use App\Services\Operations\QueueMonitorService;
use App\Services\Operations\ScheduleMonitorService;
use Illuminate\View\View;

class OperationsDashboardController extends Controller
{
    public function __invoke(
        HealthCheckRepository $healthChecks,
        HealthCheckService $healthCheckService,
        QueueMonitorService $queueMonitorService,
        QueueSnapshotRepository $queueSnapshots,
        FailedJobRepository $failedJobs,
        ScheduleMonitorService $scheduleMonitorService,
        ApplicationInfoService $applicationInfoService,
    ): View {
        $latestChecks = $healthChecks->latestByKey();

        return view('command-center.operations.dashboard', [
            'latestChecks' => $latestChecks,
            'overallStatus' => $latestChecks->isEmpty() ? 'unknown' : $healthCheckService->overallStatus($latestChecks),
            'queueSummary' => $queueMonitorService->summary(),
            'latestSnapshot' => $queueSnapshots->latest(),
            'failedJobsCount' => $failedJobs->count(),
            'notificationFailures' => NotificationDelivery::query()->where('status', 'failed')->count(),
            'webhookFailures' => WebhookDelivery::query()->where('status', 'failed')->count(),
            'eventFailures' => DomainEventLog::query()->where('status', 'failed')->count(),
            'lastScheduledRun' => $scheduleMonitorService->tasks()->pluck('last_run')->filter()->sortByDesc('started_at')->first(),
            'appInfo' => $applicationInfoService->info(),
        ]);
    }
}
