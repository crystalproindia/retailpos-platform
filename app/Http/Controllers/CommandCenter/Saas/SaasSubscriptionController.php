<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SaasSubscription;
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
}
