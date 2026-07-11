<?php

namespace App\Http\Controllers\CommandCenter\Operations;

use App\Http\Controllers\Controller;
use App\Repositories\Operations\QueueSnapshotRepository;
use App\Services\AuditLogger;
use App\Services\Operations\QueueMonitorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueMonitorController extends Controller
{
    public function index(QueueMonitorService $queueMonitorService, QueueSnapshotRepository $queueSnapshots): View
    {
        return view('command-center.operations.queue.index', [
            'summary' => $queueMonitorService->summary(),
            'breakdown' => $queueMonitorService->queueBreakdown(),
            'snapshots' => $queueSnapshots->paginate(),
        ]);
    }

    public function capture(Request $request, QueueMonitorService $queueMonitorService, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('operations.settings.manage'), 403);

        $snapshot = $queueMonitorService->captureSnapshot();

        $auditLogger->record('operations.queue_snapshot.captured', null, 'Queue snapshot captured manually', [
            'company_id' => $request->user()->company_id,
            'queue' => $snapshot->queue,
            'pending_count' => $snapshot->pending_count,
            'failed_count' => $snapshot->failed_count,
        ]);

        return back()->with('status', 'Queue snapshot captured.');
    }
}
