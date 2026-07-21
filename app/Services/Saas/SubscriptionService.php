<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementService $entitlements,
    ) {
    }

    public function create(Company $company, SaasPlan $plan, User $actor, string $method = 'manual'): SaasSubscription
    {
        $subscription = DB::transaction(function () use ($company, $plan, $actor, $method): SaasSubscription {
            $active = SaasSubscription::query()
                ->where('company_id', $company->id)
                ->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due', 'suspended'])
                ->lockForUpdate()
                ->first();

            if ($active) {
                throw ValidationException::withMessages(['company' => 'Tenant already has a current subscription.']);
            }

            $plan->loadMissing(['features', 'limits']);
            $snapshot = $plan->snapshot();
            $trial = $plan->trial_days > 0;
            $today = today();

            $subscription = SaasSubscription::create([
                'company_id' => $company->id,
                'saas_plan_id' => $plan->id,
                'subscription_number' => 'SUB-'.strtoupper(Str::ulid()),
                'status' => $trial ? 'trialing' : 'active',
                'billing_interval' => $plan->billing_interval,
                'currency' => $plan->currency,
                'price_snapshot' => $plan->base_price ?? 0,
                'tax_snapshot' => $plan->tax_percentage ?? 0,
                'setup_fee_snapshot' => $plan->setup_fee ?? 0,
                'feature_snapshot' => $snapshot['features'],
                'limit_snapshot' => $snapshot['limits'],
                'trial_starts_at' => $trial ? $today : null,
                'trial_ends_at' => $trial ? $today->copy()->addDays($plan->trial_days) : null,
                'starts_at' => $today,
                'current_period_starts_at' => $today,
                'current_period_ends_at' => $this->periodEnd($today, $plan->billing_interval),
                'renewal_date' => $this->periodEnd($today, $plan->billing_interval),
                'grace_period_ends_at' => $trial ? $today->copy()->addDays($plan->trial_days + $plan->grace_period_days) : null,
                'billing_method' => $method,
            ]);

            $this->event($subscription, $trial ? 'TrialStarted' : 'SubscriptionActivated', null, $subscription->status, $actor);
            $this->audit->record('saas.subscription.created', $subscription, 'SaaS subscription created.');

            return $subscription;
        });

        $this->entitlements->clear($company);

        return $subscription;
    }

    public function transition(SaasSubscription $subscription, string $status, User $actor, ?string $reason = null): SaasSubscription
    {
        $updated = DB::transaction(function () use ($subscription, $status, $actor, $reason): SaasSubscription {
            $subscription = SaasSubscription::lockForUpdate()->findOrFail($subscription->id);
            $from = $subscription->status;
            if ($from === $status) {
                return $subscription;
            }

            if (! $this->canTransition($from, $status)) {
                throw ValidationException::withMessages(['status' => 'This subscription lifecycle change is not allowed.']);
            }

            $subscription->update([
                'status' => $status,
                'suspended_at' => $status === 'suspended' ? now() : $subscription->suspended_at,
                'cancelled_at' => $status === 'cancelled' ? now() : $subscription->cancelled_at,
                'reactivated_at' => $status === 'active' && $from === 'suspended' ? now() : $subscription->reactivated_at,
            ]);

            $this->event($subscription, 'Subscription'.str($status)->headline()->replace(' ', ''), $from, $status, $actor, ['reason' => $reason]);
            $this->audit->record('saas.subscription.transitioned', $subscription, 'Subscription status changed.', [
                'from' => $from,
                'to' => $status,
                'reason' => $reason,
            ]);

            return $subscription->refresh();
        });

        $this->entitlements->clear($updated->company);

        return $updated;
    }

    public function requestChange(SaasSubscription $subscription, User $actor, string $event, array $payload): void
    {
        $this->event($subscription, $event, $subscription->status, $subscription->status, $actor, $payload);
        $this->audit->record('saas.subscription.requested', $subscription, 'Subscription change requested.', ['event' => $event] + $payload);
    }

    public function scheduleCancellation(SaasSubscription $subscription, User $actor, bool $immediate, ?string $reason = null): SaasSubscription
    {
        if ($immediate) {
            return $this->transition($subscription, 'cancelled', $actor, $reason);
        }

        $subscription->update(['cancellation_effective_at' => $subscription->current_period_ends_at]);
        $this->event($subscription, 'SubscriptionCancellationScheduled', $subscription->status, $subscription->status, $actor, ['reason' => $reason]);
        $this->audit->record('saas.subscription.cancellation_scheduled', $subscription, 'Subscription cancellation scheduled.');

        return $subscription->refresh();
    }

    private function periodEnd($start, string $interval)
    {
        return match ($interval) {
            'quarterly' => $start->copy()->addMonths(3),
            'half-yearly' => $start->copy()->addMonths(6),
            'yearly' => $start->copy()->addYear(),
            default => $start->copy()->addMonth(),
        };
    }

    private function event(SaasSubscription $subscription, string $key, ?string $from, string $to, User $actor, array $payload = []): void
    {
        $subscription->events()->create([
            'event_key' => $key,
            'from_status' => $from,
            'to_status' => $to,
            'payload' => $payload,
            'actor_id' => $actor->id,
        ]);
    }

    private function canTransition(string $from, string $to): bool
    {
        return in_array($to, match ($from) {
            'trialing' => ['active', 'grace_period', 'expired', 'cancelled', 'suspended'],
            'active' => ['grace_period', 'past_due', 'suspended', 'cancelled'],
            'grace_period' => ['active', 'past_due', 'suspended', 'expired', 'cancelled'],
            'past_due' => ['active', 'grace_period', 'suspended', 'expired', 'cancelled'],
            'suspended' => ['active', 'cancelled', 'expired'],
            'cancelled', 'expired' => ['active'],
            default => [],
        }, true);
    }
}
