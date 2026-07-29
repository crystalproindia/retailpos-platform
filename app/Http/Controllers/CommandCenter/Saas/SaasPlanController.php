<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\SaasPlan;
use App\Services\Saas\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasPlanController extends Controller
{
    public function index(): View
    {
        return view('command-center.saas.plans.index', [
            'plans' => SaasPlan::query()->withCount('versions')->orderBy('sort_order')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('command-center.saas.plans.form', ['plan' => new SaasPlan()]);
    }

    public function store(Request $request, PlanService $plans): RedirectResponse
    {
        $plan = $plans->save(null, $this->validated($request), $request->user());

        return redirect()->route('saas.plans.show', $plan)->with('status', 'Plan created.');
    }

    public function show(SaasPlan $plan): View
    {
        $plan->load(['features', 'limits', 'versions' => fn ($query) => $query->latest('version')]);

        return view('command-center.saas.plans.show', compact('plan'));
    }

    public function edit(SaasPlan $plan): View
    {
        $plan->load(['features', 'limits']);

        return view('command-center.saas.plans.form', compact('plan'));
    }

    public function update(Request $request, SaasPlan $plan, PlanService $plans): RedirectResponse
    {
        $plans->save($plan, $this->validated($request, $plan), $request->user());

        return redirect()->route('saas.plans.show', $plan)->with('status', 'Plan updated. Existing subscriptions retain their snapshots.');
    }

    public function duplicate(SaasPlan $plan, PlanService $plans, Request $request): RedirectResponse
    {
        $copy = $plans->duplicate($plan, $request->user());

        return redirect()->route('saas.plans.edit', $copy)->with('status', 'Draft plan copy created.');
    }

    public function archive(SaasPlan $plan): RedirectResponse
    {
        $plan->update(['status' => 'archived', 'is_public' => false]);

        return redirect()->route('saas.plans.index')->with('status', 'Plan archived. Historical subscriptions are unchanged.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SaasPlan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'alpha_dash', 'max:80', Rule::unique('saas_plans', 'code')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'billing_interval' => ['required', Rule::in(['monthly', 'quarterly', 'half-yearly', 'yearly', 'custom', 'manual'])],
            'currency' => ['required', 'string', 'size:3'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'setup_fee' => ['nullable', 'numeric', 'min:0'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
            'is_recommended' => ['nullable', 'boolean'],
            'is_custom' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'features' => ['array'],
            'features.*' => ['boolean'],
            'limits' => ['array'],
            'limits.*' => ['nullable', 'integer', 'min:1'],
        ]);

        foreach (['is_public', 'is_recommended', 'is_custom'] as $key) {
            $data[$key] = (bool) ($data[$key] ?? false);
        }

        return $data;
    }
}
