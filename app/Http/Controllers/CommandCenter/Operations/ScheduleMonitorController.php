<?php

namespace App\Http\Controllers\CommandCenter\Operations;

use App\Http\Controllers\Controller;
use App\Repositories\Operations\ScheduledTaskRunRepository;
use App\Services\Operations\ScheduleMonitorService;
use Illuminate\View\View;

class ScheduleMonitorController extends Controller
{
    public function __invoke(ScheduleMonitorService $scheduleMonitorService, ScheduledTaskRunRepository $scheduledTaskRuns): View
    {
        return view('command-center.operations.schedule.index', [
            'tasks' => $scheduleMonitorService->tasks(),
            'runs' => $scheduledTaskRuns->paginate(),
        ]);
    }
}
