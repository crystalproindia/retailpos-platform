<?php

namespace App\Services\Saas;

use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanChangeService
{
    public function __construct(private readonly UsageService $usage, private readonly EntitlementService $entitlements, private readonly AuditLogger $audit) {}

    /** @return array<string, array{current:int,limit:?int}> */
    public function conflicts(SaasSubscription $subscription, SaasPlan $plan): array
    {
        $plan->loadMissing('limits');
        $limits = $plan->limits->pluck('limit_value', 'limit_key');
        return collect(['users','branches','warehouses','products'])->mapWithKeys(function (string $key) use ($subscription, $limits): array {
            $limit = $limits->get($key);
            $current = $this->usage->current($subscription->company, $key);
            return $limit !== null && $current > $limit ? [$key => ['current' => $current, 'limit' => $limit]] : [];
        })->all();
    }

    public function schedule(SaasSubscription $subscription, SaasPlan $plan, User $actor, bool $immediate, ?string $reason = null): SaasSubscription
    {
        $conflicts = $this->conflicts($subscription, $plan);
        if ($immediate && $conflicts !== []) throw ValidationException::withMessages(['plan' => 'This plan cannot be applied immediately while current usage exceeds its limits.']);
        return DB::transaction(function () use ($subscription, $plan, $actor, $immediate, $reason): SaasSubscription {
            $subscription = SaasSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($immediate) return $this->apply($subscription, $plan, $actor, $reason);
            $subscription->update(['pending_saas_plan_id' => $plan->id, 'pending_change_at' => $subscription->renewal_date, 'pending_change_reason' => $reason]);
            $subscription->events()->create(['event_key' => 'PlanChangeScheduled', 'from_status' => $subscription->status, 'to_status' => $subscription->status, 'payload' => ['plan_id' => $plan->id, 'reason' => $reason, 'previous_snapshot' => $this->snapshot($subscription)], 'actor_id' => $actor->id]);
            $this->audit->record('saas.plan_change.scheduled', $subscription, 'Plan change scheduled.', ['plan_id' => $plan->id]);
            return $subscription->refresh();
        });
    }

    public function cancelScheduledChange(SaasSubscription $subscription, User $actor): SaasSubscription
    {
        $subscription->update(['pending_saas_plan_id' => null, 'pending_change_at' => null, 'pending_change_reason' => null]);
        $subscription->events()->create(['event_key' => 'PlanChangeCancelled', 'from_status' => $subscription->status, 'to_status' => $subscription->status, 'payload' => ['cancelled_plan_id' => $subscription->pending_saas_plan_id], 'actor_id' => $actor->id]);
        $this->audit->record('saas.plan_change.cancelled', $subscription, 'Scheduled plan change cancelled.');
        return $subscription->refresh();
    }

    private function apply(SaasSubscription $subscription, SaasPlan $plan, User $actor, ?string $reason): SaasSubscription
    {
        $plan->loadMissing(['features', 'limits']); $snapshot = $plan->snapshot();
        $subscription->update(['saas_plan_id' => $plan->id, 'billing_interval' => $plan->billing_interval, 'currency' => $plan->currency, 'price_snapshot' => $plan->base_price, 'tax_snapshot' => $plan->tax_percentage, 'setup_fee_snapshot' => $plan->setup_fee, 'feature_snapshot' => $snapshot['features'], 'limit_snapshot' => $snapshot['limits'], 'pending_saas_plan_id' => null, 'pending_change_at' => null, 'pending_change_reason' => null]);
        $previous = $this->snapshot($subscription);
        $subscription->events()->create(['event_key' => 'PlanChanged', 'from_status' => $subscription->status, 'to_status' => $subscription->status, 'payload' => ['plan_id' => $plan->id, 'reason' => $reason, 'previous_snapshot' => $previous, 'provider_billing_adjustment' => 'manual_review_required'], 'actor_id' => $actor->id]);
        $this->audit->record('saas.plan_change.applied', $subscription, 'Plan change applied.', ['plan_id' => $plan->id]);
        $this->entitlements->clear($subscription->company);
        return $subscription->refresh();
    }

    /** @return array<string, mixed> */
    private function snapshot(SaasSubscription $subscription): array
    {
        return [
            'plan_id' => $subscription->saas_plan_id,
            'billing_interval' => $subscription->billing_interval,
            'currency' => $subscription->currency,
            'price' => $subscription->price_snapshot,
            'features' => $subscription->feature_snapshot,
            'limits' => $subscription->limit_snapshot,
        ];
    }
}
