<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\SaasPlan;
use App\Models\SaasTenantOnboarding;
use App\Services\Saas\TenantOnboardingService;
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
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, TenantOnboardingService $onboarding): RedirectResponse
    {
        $record = $onboarding->complete($request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'country' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:5000'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3'],
            'tax_registration_type' => ['nullable', 'string', 'max:48'],
            'gstin' => ['nullable', 'string', 'max:32'],
            'industry' => ['nullable', 'string', 'max:120'],
            'saas_plan_id' => ['required', Rule::exists('saas_plans', 'id')->where('status', 'active')],
            'billing_method' => ['required', Rule::in(['manual', 'offline', 'gateway', 'complimentary'])],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:12', 'confirmed'],
            'billing_contact_name' => ['nullable', 'string', 'max:255'],
            'billing_contact_email' => ['nullable', 'email', 'max:255'],
        ]), $request->user());

        return redirect()->route('saas.tenants.show', $record->company_id)->with('status', 'Tenant onboarding completed.');
    }
}
