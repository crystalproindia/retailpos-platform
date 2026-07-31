<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\LeadStageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\LeadSourceRequest;
use App\Http\Requests\Crm\LeadStatusRequest;
use App\Http\Requests\Crm\ReorderLeadMasterDataRequest;
use App\Repositories\Crm\LeadMasterDataRepository;
use App\Services\Crm\LeadMasterDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadMasterDataController extends Controller
{
    public function index(Request $request, LeadMasterDataRepository $repository): View
    {
        return view('command-center.crm.settings.index', [
            'statuses' => $repository->statusesFor($request->user()),
            'sources' => $repository->sourcesFor($request->user()),
        ]);
    }

    public function statuses(Request $request, LeadMasterDataRepository $repository): View
    {
        return view('command-center.crm.settings.statuses', [
            'statuses' => $repository->statusesFor($request->user()),
            'stageTypes' => LeadStageType::cases(),
        ]);
    }

    public function storeStatus(LeadStatusRequest $request, LeadMasterDataService $service): RedirectResponse
    {
        $service->createStatus($request->user(), $request->validated() + ['is_default' => $request->boolean('is_default')]);

        return redirect()->route('crm.settings.statuses.index')->with('status', 'Lead status created.');
    }

    public function editStatus(Request $request, LeadMasterDataRepository $repository, int $status): View
    {
        return view('command-center.crm.settings.status-edit', [
            'status' => $repository->statusFor($request->user(), $status),
            'stageTypes' => LeadStageType::cases(),
        ]);
    }

    public function updateStatus(LeadStatusRequest $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $status): RedirectResponse
    {
        $service->updateStatus($request->user(), $repository->statusFor($request->user(), $status), $request->validated() + ['is_default' => $request->boolean('is_default')]);

        return redirect()->route('crm.settings.statuses.index')->with('status', 'Lead status updated.');
    }

    public function toggleStatus(Request $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $status): RedirectResponse
    {
        $service->toggleStatus($request->user(), $repository->statusFor($request->user(), $status));

        return back()->with('status', 'Lead status availability updated.');
    }

    public function defaultStatus(Request $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $status): RedirectResponse
    {
        $service->makeStatusDefault($request->user(), $repository->statusFor($request->user(), $status));

        return back()->with('status', 'Default lead status updated.');
    }

    public function reorderStatuses(ReorderLeadMasterDataRequest $request, LeadMasterDataService $service): RedirectResponse
    {
        $service->reorderStatuses($request->user(), $request->validated('ids'));

        return back()->with('status', 'Lead status order updated.');
    }

    public function moveStatus(Request $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $status, string $direction): RedirectResponse
    {
        $service->moveStatus($request->user(), $repository->statusFor($request->user(), $status), $direction);

        return back()->with('status', 'Lead status order updated.');
    }

    public function destroyStatus(Request $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $status): RedirectResponse
    {
        $service->deleteStatus($request->user(), $repository->statusFor($request->user(), $status));

        return back()->with('status', 'Lead status deleted.');
    }

    public function sources(Request $request, LeadMasterDataRepository $repository): View
    {
        return view('command-center.crm.settings.sources', [
            'sources' => $repository->sourcesFor($request->user()),
        ]);
    }

    public function storeSource(LeadSourceRequest $request, LeadMasterDataService $service): RedirectResponse
    {
        $service->createSource($request->user(), $request->validated() + ['is_default' => $request->boolean('is_default')]);

        return redirect()->route('crm.settings.sources.index')->with('status', 'Lead source created.');
    }

    public function editSource(Request $request, LeadMasterDataRepository $repository, int $source): View
    {
        return view('command-center.crm.settings.source-edit', [
            'source' => $repository->sourceFor($request->user(), $source),
        ]);
    }

    public function updateSource(LeadSourceRequest $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $source): RedirectResponse
    {
        $service->updateSource($request->user(), $repository->sourceFor($request->user(), $source), $request->validated() + ['is_default' => $request->boolean('is_default')]);

        return redirect()->route('crm.settings.sources.index')->with('status', 'Lead source updated.');
    }

    public function toggleSource(Request $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $source): RedirectResponse
    {
        $service->toggleSource($request->user(), $repository->sourceFor($request->user(), $source));

        return back()->with('status', 'Lead source availability updated.');
    }

    public function defaultSource(Request $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $source): RedirectResponse
    {
        $service->makeSourceDefault($request->user(), $repository->sourceFor($request->user(), $source));

        return back()->with('status', 'Default lead source updated.');
    }

    public function reorderSources(ReorderLeadMasterDataRequest $request, LeadMasterDataService $service): RedirectResponse
    {
        $service->reorderSources($request->user(), $request->validated('ids'));

        return back()->with('status', 'Lead source order updated.');
    }

    public function moveSource(Request $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $source, string $direction): RedirectResponse
    {
        $service->moveSource($request->user(), $repository->sourceFor($request->user(), $source), $direction);

        return back()->with('status', 'Lead source order updated.');
    }

    public function destroySource(Request $request, LeadMasterDataRepository $repository, LeadMasterDataService $service, int $source): RedirectResponse
    {
        $service->deleteSource($request->user(), $repository->sourceFor($request->user(), $source));

        return back()->with('status', 'Lead source deleted.');
    }
}
