<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SaasSubscription;
use App\Models\SaasTenantOnboarding;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaasDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $subscriptions = SaasSubscription::query();

        return view('command-center.saas.dashboard', [
            'metrics' => [
                'tenants' => Company::count(),
                'active' => (clone $subscriptions)->where('status', 'active')->count(),
                'trials' => (clone $subscriptions)->where('status', 'trialing')->count(),
                'expiring_trials' => (clone $subscriptions)->where('status', 'trialing')->whereDate('trial_ends_at', '<=', today()->addDays(7))->count(),
                'past_due' => (clone $subscriptions)->where('status', 'past_due')->count(),
                'suspended' => (clone $subscriptions)->where('status', 'suspended')->count(),
                'renewals_due' => (clone $subscriptions)->whereDate('renewal_date', '<=', today()->addDays(30))->whereIn('status', ['active', 'grace_period', 'past_due'])->count(),
            ],
            'recentOnboarding' => SaasTenantOnboarding::query()->with(['company', 'plan'])->latest()->limit(6)->get(),
            'recentSubscriptions' => SaasSubscription::query()->with(['company', 'plan'])->latest()->limit(8)->get(),
        ]);
    }
}
