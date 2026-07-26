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
        $cacheKey = "saas:subscription:{$company->id}";
        $subscription = Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            fn (): ?SaasSubscription => SaasSubscription::query()
                ->where('company_id', $company->id)
                ->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due', 'suspended', 'expired'])
                ->latest('id')
                ->first(),
        );

        if ($subscription === null || $subscription instanceof SaasSubscription) {
            return $subscription;
        }

        // File or serialized caches may retain an old class payload across a
        // deployment. Discard it and recover from the authoritative database.
        Cache::forget($cacheKey);

        return Cache::remember($cacheKey, now()->addMinutes(5), fn (): ?SaasSubscription => SaasSubscription::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due', 'suspended', 'expired'])
            ->latest('id')
            ->first());
    }

    public function active(Company $company): bool
    {
        return in_array($this->subscription($company)?->status, ['trialing', 'active', 'grace_period', 'past_due'], true);
    }

    public function allows(Company $company, string $feature): bool
    {
        $feature = $this->canonicalFeature($feature);
        $override = $this->override($company, 'feature', $feature);

        if ($override !== null) {
            return (bool) $override;
        }

        $subscription = $this->subscription($company);

        if (! $this->active($company) || ! $subscription) {
            return false;
        }

        if ((bool) ($subscription->feature_snapshot[$feature] ?? false)) {
            return true;
        }

        $legacy = collect(config('saas.entitlement_aliases', []))
            ->filter(fn (string $canonical): bool => $canonical === $feature)
            ->keys()
            ->first();

        return $legacy ? (bool) ($subscription->feature_snapshot[$legacy] ?? false) : false;
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

    public function clearForCompanyId(int $companyId): void
    {
        Cache::forget("saas:subscription:{$companyId}");
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

    private function canonicalFeature(string $feature): string
    {
        return config('saas.entitlement_aliases.'.$feature, $feature);
    }
}
