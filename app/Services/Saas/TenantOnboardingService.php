<?php

namespace App\Services\Saas;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasTenantOnboarding;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantOnboardingService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function complete(array $data, User $actor): SaasTenantOnboarding
    {
        return DB::transaction(function () use ($data, $actor): SaasTenantOnboarding {
            $onboarding = SaasTenantOnboarding::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($onboarding?->status === 'completed') {
                return $onboarding;
            }

            $plan = SaasPlan::query()->where('status', 'active')->findOrFail($data['saas_plan_id']);

            if (User::query()->where('email', $data['admin_email'])->exists()) {
                throw ValidationException::withMessages(['admin_email' => 'This primary administrator email is already in use.']);
            }

            $onboarding ??= SaasTenantOnboarding::create([
                'idempotency_key' => $data['idempotency_key'],
                'saas_plan_id' => $plan->id,
                'status' => 'in_progress',
                'current_stage' => 'business_details',
                'payload' => $this->safePayload($data),
            ]);

            $company = Company::create([
                'name' => ($data['trade_name'] ?? null) ?: $data['legal_name'],
                'legal_name' => $data['legal_name'],
                'trade_name' => ($data['trade_name'] ?? null) ?: null,
                'tax_id' => ($data['gstin'] ?? null) ?: null,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => ($data['address'] ?? null) ?: null,
                'city' => ($data['city'] ?? null) ?: null,
                'state' => ($data['state'] ?? null) ?: null,
                'country' => $data['country'],
                'postal_code' => ($data['postal_code'] ?? null) ?: null,
                'timezone' => $data['timezone'],
                'currency' => $data['currency'],
                'tax_registration_type' => ($data['tax_registration_type'] ?? null) ?: null,
                'industry' => ($data['industry'] ?? null) ?: null,
                'billing_contact_name' => ($data['billing_contact_name'] ?? null) ?: $data['admin_name'],
                'billing_contact_email' => ($data['billing_contact_email'] ?? null) ?: $data['admin_email'],
                'is_active' => true,
            ]);

            $branch = Branch::create([
                'company_id' => $company->id,
                'name' => ($data['branch_name'] ?? null) ?: 'Main branch',
                'code' => 'MAIN',
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => ($data['address'] ?? null) ?: null,
                'city' => ($data['city'] ?? null) ?: null,
                'state' => ($data['state'] ?? null) ?: null,
                'country' => $data['country'],
                'is_primary' => true,
                'is_active' => true,
            ]);

            $administrator = User::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'role' => UserRole::Administrator,
                'is_active' => true,
                'password' => Hash::make($data['admin_password']),
            ]);

            $subscription = $this->subscriptions->create($company, $plan, $actor, $data['billing_method']);

            $onboarding->update([
                'company_id' => $company->id,
                'saas_plan_id' => $plan->id,
                'status' => 'completed',
                'current_stage' => 'complete',
                'completed_at' => now(),
            ]);

            $this->audit->record('saas.onboarding.completed', $company, 'Tenant onboarding completed.', [
                'subscription_id' => $subscription->id,
                'onboarding_id' => $onboarding->id,
            ]);

            return $onboarding->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function safePayload(array $data): array
    {
        unset($data['admin_password']);

        return $data;
    }
}
