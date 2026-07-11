<?php

namespace App\Http\Controllers\CommandCenter\Operations;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Operations\FailedJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FailedJobController extends Controller
{
    public function index(Request $request, FailedJobService $failedJobService): View
    {
        return view('command-center.operations.failed-jobs.index', [
            'jobs' => $failedJobService->paginate($request->only(['search', 'queue', 'connection'])),
            'queues' => $failedJobService->queues(),
        ]);
    }

    public function retry(Request $request, FailedJobService $failedJobService, AuditLogger $auditLogger, int $failedJob): RedirectResponse
    {
        abort_unless($request->user()->can('operations.failed_jobs.retry'), 403);

        $job = $failedJobService->retry($failedJob);

        $auditLogger->record('operations.failed_job.retried', null, 'Failed job retried', [
            'company_id' => $request->user()->company_id,
            'failed_job_id' => $failedJob,
            'uuid' => $job['uuid'],
        ]);

        return back()->with('status', 'Failed job queued for retry.');
    }

    public function destroy(Request $request, FailedJobService $failedJobService, AuditLogger $auditLogger, int $failedJob): RedirectResponse
    {
        abort_unless($request->user()->can('operations.failed_jobs.delete'), 403);

        $failedJobService->delete($failedJob);

        $auditLogger->record('operations.failed_job.deleted', null, 'Failed job deleted', [
            'company_id' => $request->user()->company_id,
            'failed_job_id' => $failedJob,
        ]);

        return back()->with('status', 'Failed job deleted.');
    }

    public function bulkRetry(Request $request, FailedJobService $failedJobService, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('operations.failed_jobs.retry'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $jobs = $failedJobService->bulkRetry($validated['ids']);

        $auditLogger->record('operations.failed_jobs.bulk_retried', null, 'Failed jobs bulk retried', [
            'company_id' => $request->user()->company_id,
            'failed_job_ids' => array_values($validated['ids']),
            'retried_count' => $jobs->count(),
        ]);

        return back()->with('status', $jobs->count().' failed jobs queued for retry.');
    }

    public function bulkDestroy(Request $request, FailedJobService $failedJobService, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('operations.failed_jobs.delete'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $deleted = $failedJobService->bulkDelete($validated['ids']);

        $auditLogger->record('operations.failed_jobs.bulk_deleted', null, 'Failed jobs bulk deleted', [
            'company_id' => $request->user()->company_id,
            'failed_job_ids' => array_values($validated['ids']),
            'deleted_count' => $deleted,
        ]);

        return back()->with('status', $deleted.' failed jobs deleted.');
    }
}
