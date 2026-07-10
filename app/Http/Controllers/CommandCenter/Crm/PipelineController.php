<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\TransitionLeadStatusRequest;
use App\Repositories\Crm\LeadRepository;
use App\Repositories\Crm\PipelineRepository;
use App\Services\Crm\PipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function index(Request $request, PipelineRepository $pipelineRepository): View
    {
        return view('command-center.crm.pipeline.index', [
            'columns' => $pipelineRepository->groupedForUser($request->user()),
        ]);
    }

    public function transition(TransitionLeadStatusRequest $request, LeadRepository $leadRepository, PipelineService $pipelineService, int $lead): RedirectResponse
    {
        $pipelineService->transition(
            $leadRepository->findForUser($request->user(), $lead),
            (int) $request->validated('status_id'),
            $request->user(),
        );

        return back()->with('status', 'CRM pipeline updated.');
    }
}
