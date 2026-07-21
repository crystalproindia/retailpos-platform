<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\SaasSubscription;
use App\Models\SaasTenantOverride;
use Illuminate\Support\Facades\Cache;

class EntitlementService
{
    public function subscription(Company $company): ?SaasSubscription
    {
        return Cache::remember(
            "saas:subscription:{$company->id}",
            now()->addMinutes(5),
            fn (): ?SaasSubscription => SaasSubscription::query()
                ->where('company_id', $company->id)
                ->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due', 'suspended'])
                ->latest('id')
                ->first(),
        );
    }

    public function active(Company $company): bool
    {
        return in_array($this->subscription($company)?->status, ['trialing', 'active', 'grace_period', 'past_due'], true);
    }

    public function allows(Company $company, string $feature): bool
    {
        $override = $this->override($company, 'feature', $feature);

        if ($override !== null) {
            return (bool) $override;
        }

        $subscription = $this->subscription($company);

        return $this->active($company) && (bool) ($subscription?->feature_snapshot[$feature] ?? false);
    }

    public function limit(Company $company, string $key): ?int
    {
        $override = $this->override($company, 'limit', $key);

        if ($override !== null) {
            return (int) $override;
        }

        return $this->subscription($company)?->limit_snapshot[$key] ?? null;
    }

    public function clear(Company $company): void
    {
        Cache::forget("saas:subscription:{$company->id}");
    }

    private function override(Company $company, string $type, string $key): mixed
    {
        $override = SaasTenantOverride::query()
            ->where('company_id', $company->id)
            ->where('override_type', $type)
            ->where('key', $key)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
            ->latest('id')
            ->first();

        return $override?->value['value'] ?? null;
    }
}
