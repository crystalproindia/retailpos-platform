<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\PipelineStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\MovePipelineCardRequest;
use App\Http\Requests\Crm\TransitionLeadStatusRequest;
use App\Models\User;
use App\Repositories\Crm\LeadRepository;
use App\Services\Crm\CrmPipelineService;
use App\Services\Crm\PipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function index(Request $request, CrmPipelineService $pipeline, LeadRepository $leadRepository): View
    {
        $filters = $request->only(['search', 'stage', 'assigned_user_id', 'source_id', 'created_from', 'created_to', 'activity_from', 'activity_to', 'follow_up', 'payment_status', 'min_value', 'max_value']);
        $view = $request->string('view')->value() === 'list' ? 'list' : 'board';

        return view('command-center.crm.pipeline.index', $pipeline->forUser($request->user(), $filters) + [
            'viewMode' => $view,
            'filters' => $filters,
            'stages' => PipelineStage::cases(),
            'sources' => $leadRepository->sourcesForCompany($request->user()->company_id),
            'owners' => User::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
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

    public function move(MovePipelineCardRequest $request, LeadRepository $leadRepository, PipelineService $pipelineService, int $lead): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $lead = $pipelineService->move(
            $leadRepository->findForUser($request->user(), $lead),
            PipelineStage::from($request->string('target_stage')->value()),
            $request->user(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Pipeline updated.',
                'lead_id' => $lead->id,
                'stage' => $request->string('target_stage')->value(),
            ]);
        }

        return back()->with('status', 'CRM pipeline updated.');
    }
}
