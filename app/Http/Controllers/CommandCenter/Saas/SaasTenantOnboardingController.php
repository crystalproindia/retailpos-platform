<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\SaasPlan;
use App\Models\SaasTenantOnboarding;
use App\Services\Saas\IndustryRegistry;
use App\Services\Saas\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasTenantOnboardingController extends Controller
{
    public function index(): View
    {
        return view('command-center.saas.onboarding.index', [
            'onboardings' => SaasTenantOnboarding::query()->with(['company', 'plan'])->latest()->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('command-center.saas.onboarding.create', [
            'plans' => SaasPlan::query()->where('status', 'active')->orderBy('sort_order')->get(),
            'industries' => app(IndustryRegistry::class)->enabled(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, TenantProvisioningService $provisioning): RedirectResponse
    {
        $record = $provisioning->provision($request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'owner_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'industry' => ['required', 'string', 'max:80'],
            'saas_plan_id' => ['required', Rule::exists('saas_plans', 'id')->where('status', 'active')],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone'],
            'currency' => ['nullable', 'string', 'size:3'],
            'country' => ['nullable', 'string', 'max:120'],
            'subscription_starts_at' => ['nullable', 'date', 'before_or_equal:today'],
            'require_password_change' => ['nullable', 'boolean'],
        ]), $request->user());

        return redirect()->route('saas.tenants.show', $record->company_id)->with('status', 'Tenant onboarding completed.');
    }
}
