<?php

namespace App\Services\Saas;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasTenantOnboarding;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantProvisioningService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly IndustryRegistry $industries,
        private readonly AccountVerificationService $verification,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function provision(array $data, User $actor): SaasTenantOnboarding
    {
        return DB::transaction(function () use ($data, $actor): SaasTenantOnboarding {
            $onboarding = SaasTenantOnboarding::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($onboarding?->status === 'completed') return $onboarding;

            $plan = SaasPlan::query()->where('status', 'active')->findOrFail($data['saas_plan_id']);
            $industry = $this->industries->selectable($data['industry']);
            $mobile = $this->normalizeMobile($data['mobile']);
            $email = filled($data['email'] ?? null) ? mb_strtolower((string) $data['email']) : $this->pendingEmail($mobile);

            if (User::query()->where('email', $email)->orWhere('mobile', $mobile)->exists()) {
                throw ValidationException::withMessages(['owner' => 'An account with this mobile number or email already exists.']);
            }

            $onboarding ??= SaasTenantOnboarding::create([
                'idempotency_key' => $data['idempotency_key'],
                'saas_plan_id' => $plan->id,
                'status' => 'in_progress',
                'current_stage' => 'provisioning',
                'payload' => $this->safePayload($data),
            ]);

            $companyName = trim((string) ($data['company_name'] ?? '')) ?: 'Your Store Name';
            $company = Company::create([
                'name' => $companyName,
                'legal_name' => $data['legal_name'] ?? null,
                'trade_name' => $companyName === 'Your Store Name' ? null : $companyName,
                'email' => $data['email'] ?? null,
                'phone' => $mobile,
                'industry' => $industry->key,
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'currency' => $data['currency'] ?? 'INR',
                'is_active' => true,
            ]);

            $branch = Branch::create([
                'company_id' => $company->id,
                'name' => $data['branch_name'] ?? 'Main outlet',
                'code' => 'MAIN',
                'email' => $data['email'] ?? null,
                'phone' => $mobile,
                'country' => $data['country'] ?? 'India',
                'is_primary' => true,
                'is_active' => true,
            ]);

            $administrator = User::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => $data['owner_name'],
                'email' => $email,
                'mobile' => $mobile,
                'role' => UserRole::Administrator,
                'is_active' => true,
                'verification_status' => 'pending',
                'requires_password_change' => (bool) ($data['require_password_change'] ?? false),
                'password' => Hash::make($data['password']),
            ]);

            $startDate = isset($data['subscription_starts_at']) ? Carbon::parse($data['subscription_starts_at']) : null;
            $subscription = $this->subscriptions->create($company, $plan, $actor, 'complimentary', $startDate);

            $onboarding->update([
                'company_id' => $company->id,
                'saas_plan_id' => $plan->id,
                'status' => 'completed',
                'current_stage' => 'complete',
                'completed_at' => now(),
            ]);

            // Email delivery uses the existing configured infrastructure. Mobile
            // delivery remains provider-neutral until an SMS adapter is configured.
            if (filled($data['email'] ?? null)) $this->verification->issue($administrator, 'email');

            $this->audit->record('saas.tenant.provisioned', $company, 'Tenant account provisioned.', [
                'subscription_id' => $subscription->id,
                'onboarding_id' => $onboarding->id,
                'industry' => $industry->key,
                'placeholder_company_name' => $company->hasPlaceholderName(),
            ]);

            return $onboarding->refresh();
        });
    }

    private function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
        if (strlen($digits) === 10) $digits = '91'.$digits;
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw ValidationException::withMessages(['mobile' => 'Enter a valid mobile number with country code.']);
        }
        return '+'.$digits;
    }

    private function pendingEmail(string $mobile): string
    {
        return 'mobile-'.ltrim($mobile, '+').'@pending.retailpos.local';
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function safePayload(array $data): array
    {
        unset($data['password']);
        return $data;
    }
}
