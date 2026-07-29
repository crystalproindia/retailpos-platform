<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\MoveOpportunityRequest;
use App\Http\Requests\Crm\StoreOpportunityRequest;
use App\Models\Crm\CrmOpportunity;
use App\Repositories\Crm\LeadRepository;
use App\Services\Crm\OpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public function index(Request $request): View
    {
        $opportunities = CrmOpportunity::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['lead', 'assignedUser'])
            ->when($request->query('stage'), fn ($query, string $stage) => $query->where('stage', $stage))
            ->when($request->query('mine') && $request->user()->can('sales.opportunities.view'), fn ($query) => $query->where('assigned_user_id', $request->user()->id))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('command-center.crm.opportunities.index', compact('opportunities'));
    }

    public function create(Request $request, LeadRepository $leads, int $lead): View
    {
        return view('command-center.crm.opportunities.form', ['lead' => $leads->findForUser($request->user(), $lead)]);
    }

    public function store(StoreOpportunityRequest $request, LeadRepository $leads, OpportunityService $opportunities, int $lead): RedirectResponse
    {
        $opportunity = $opportunities->create($leads->findForUser($request->user(), $lead), $request->user(), $request->validated());

        return redirect()->route('sales.opportunities.index')->with('status', 'Sales opportunity created: '.$opportunity->title);
    }

    public function move(MoveOpportunityRequest $request, OpportunityService $opportunities, int $opportunity): RedirectResponse
    {
        $record = CrmOpportunity::query()->where('company_id', $request->user()->company_id)->findOrFail($opportunity);
        if ($request->user()->role->value === 'sales' && $record->assigned_user_id !== $request->user()->id) {
            abort(403);
        }
        $opportunities->move($record, $request->validated('stage'), $request->user(), $request->validated('note'));

        return back()->with('status', 'Opportunity stage updated.');
    }
}
