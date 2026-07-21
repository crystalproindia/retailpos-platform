<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SaasSubscription;
use App\Models\SaasPlan;
use App\Services\Saas\PlanChangeService;
use App\Services\Saas\SubscriptionService;
use App\Services\Saas\UsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $query = SaasSubscription::query()->with(['company', 'plan']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return view('command-center.saas.subscriptions.index', [
            'subscriptions' => $query->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function show(Company $company, UsageService $usage): View
    {
        return view('command-center.saas.tenants.show', [
            'company' => $company,
            'subscription' => $company->saasSubscriptions()->with(['plan', 'pendingPlan', 'events.actor'])->latest()->first(),
            'usage' => $usage->summary($company),
            'plans' => SaasPlan::query()->where('status', 'active')->orderBy('sort_order')->get(),
        ]);
    }

    public function transition(Request $request, SaasSubscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'grace_period', 'past_due', 'suspended', 'cancelled', 'expired'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $subscriptions->transition($subscription, $data['status'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Subscription status updated.');
    }

    public function renew(Request $request, SaasSubscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['manual', 'complimentary', 'offline'])],
            'reference' => ['nullable', 'string', 'max:160'],
        ]);
        $subscriptions->renew($subscription, $request->user(), $data['method'], $data['reference'] ?? null, 'manual-renewal:'.$subscription->id.':'.now()->format('YmdHis'));

        return back()->with('status', 'Subscription renewed and reactivated where needed.');
    }

    public function extendTrial(Request $request, SaasSubscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365'], 'reason' => ['required', 'string', 'max:1000']]);
        $subscriptions->extendTrial($subscription, $request->user(), $data['days'], $data['reason'], 'trial-extension:'.$subscription->id.':'.now()->format('YmdHis'));

        return back()->with('status', 'Trial extended.');
    }

    public function changePlan(Request $request, SaasSubscription $subscription, PlanChangeService $plans): RedirectResponse
    {
        $data = $request->validate(['saas_plan_id' => ['required', 'exists:saas_plans,id'], 'immediate' => ['nullable', 'boolean'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $plans->schedule($subscription, SaasPlan::findOrFail($data['saas_plan_id']), $request->user(), $request->boolean('immediate'), $data['reason'] ?? null);

        return back()->with('status', $request->boolean('immediate') ? 'Plan applied immediately.' : 'Plan change scheduled for renewal.');
    }

    public function cancelPlanChange(SaasSubscription $subscription, Request $request, PlanChangeService $plans): RedirectResponse
    {
        $plans->cancelScheduledChange($subscription, $request->user());

        return back()->with('status', 'Scheduled plan change cancelled.');
    }
}
