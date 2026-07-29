<?php

namespace App\Services\Saas;

use App\Models\SaasTenantOnboarding;
use App\Models\User;

class TenantOnboardingService
{
    public function __construct(private readonly TenantProvisioningService $provisioning) {}

    /**
     * Backward-compatible adapter for the existing onboarding form. New public
     * signup and platform quick-account creation both use TenantProvisioningService.
     *
     * @param array<string, mixed> $data
     */
    public function complete(array $data, User $actor): SaasTenantOnboarding
    {
        return $this->provisioning->provision([
            'idempotency_key' => $data['idempotency_key'],
            'owner_name' => $data['admin_name'],
            'mobile' => $data['phone'],
            'password' => $data['admin_password'],
            'company_name' => $data['trade_name'] ?? $data['legal_name'],
            'legal_name' => $data['legal_name'],
            'email' => $data['admin_email'],
            'industry' => in_array($data['industry'] ?? null, array_column(config('saas.industries', []), 'key'), true) ? $data['industry'] : 'other',
            'saas_plan_id' => $data['saas_plan_id'],
            'branch_name' => $data['branch_name'] ?? null,
            'timezone' => $data['timezone'],
            'currency' => $data['currency'],
            'country' => $data['country'],
        ], $actor);
    }
}
