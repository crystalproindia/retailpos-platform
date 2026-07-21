<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\SaasPlan;
use App\Services\Saas\EntitlementService;
use App\Services\Saas\SubscriptionService;
use App\Services\Saas\UsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantSubscriptionController extends Controller
{
    public function index(Request $request, EntitlementService $entitlements, UsageService $usage): View
    {
        $company = $request->user()->company;

        return view('command-center.saas.subscription.index', [
            'subscription' => $entitlements->subscription($company)?->load(['plan', 'pendingPlan', 'events' => fn ($query) => $query->latest()->limit(15)]),
            'usage' => $usage->summary($company),
            'plans' => SaasPlan::query()->where('status', 'active')->where('is_public', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function requestChange(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $company = $request->user()->company;
        $subscription = $company->saasSubscriptions()->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due'])->latest()->firstOrFail();
        $data = $request->validate([
            'type' => ['required', Rule::in(['upgrade', 'downgrade', 'cancellation'])],
            'saas_plan_id' => ['nullable', 'integer', 'exists:saas_plans,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['type'] !== 'cancellation' && empty($data['saas_plan_id'])) {
            return back()->withErrors(['saas_plan_id' => 'Please choose a plan.']);
        }

        $event = match ($data['type']) {
            'upgrade' => 'PlanUpgradeRequested',
            'downgrade' => 'PlanDowngradeRequested',
            default => 'SubscriptionCancellationRequested',
        };

        $subscriptions->requestChange($subscription, $request->user(), $event, [
            'requested_plan_id' => $data['saas_plan_id'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        return back()->with('status', 'Your request has been recorded. The billing team will review it before anything changes.');
    }
}
