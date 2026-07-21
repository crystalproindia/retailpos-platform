<?php

namespace App\Services\Saas;

use App\Models\SaasSubscription;

class SaasLifecycleService
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /** @return array{trials: int, reminders: int, transitions: int} */
    public function processTrials(bool $dryRun = false): array
    {
        $summary = ['trials' => 0, 'reminders' => 0, 'transitions' => 0];
        SaasSubscription::query()->where('status', 'trialing')->orderBy('id')->chunkById(100, function ($subscriptions) use (&$summary, $dryRun): void {
            foreach ($subscriptions as $subscription) {
                $summary['trials']++;
                $days = (int) today()->diffInDays($subscription->trial_ends_at, false);
                if (in_array($days, [7, 3, 1], true)) {
                    $summary['reminders']++;
                    if (! $dryRun) $this->subscriptions->recordReminder($subscription, 'TrialEndingIn'.$days.'Days', "trial-ending:{$subscription->id}:{$days}:".today()->toDateString());
                }
                if ($subscription->trial_ends_at?->isFuture()) continue;
                $summary['transitions']++;
                if ($dryRun) continue;
                $target = $subscription->grace_period_ends_at?->isFuture() ? 'grace_period' : 'expired';
                $this->subscriptions->transition($subscription, $target, null, 'Trial period ended.', "trial-ended:{$subscription->id}:{$target}:".today()->toDateString());
            }
        });
        return $summary;
    }

    /** @return array{renewals: int, reminders: int, transitions: int} */
    public function processRenewals(bool $dryRun = false): array
    {
        $summary = ['renewals' => 0, 'reminders' => 0, 'transitions' => 0];
        SaasSubscription::query()->whereIn('status', ['active', 'past_due', 'grace_period'])->orderBy('id')->chunkById(100, function ($subscriptions) use (&$summary, $dryRun): void {
            foreach ($subscriptions as $subscription) {
                $summary['renewals']++;
                $days = (int) today()->diffInDays($subscription->renewal_date, false);
                if (in_array($days, config('saas.renewal_reminder_days', []), true)) {
                    $summary['reminders']++;
                    if (! $dryRun) $this->subscriptions->recordReminder($subscription, 'RenewalReminder'.($days === 0 ? 'Due' : $days.'Days'), "renewal-reminder:{$subscription->id}:{$days}:{$subscription->renewal_date?->toDateString()}");
                }
                if ($subscription->status === 'active' && $subscription->renewal_date?->isPast()) {
                    $summary['transitions']++;
                    if (! $dryRun) $this->subscriptions->transition($subscription, 'grace_period', null, 'Renewal payment is due.', "renewal-grace:{$subscription->id}:{$subscription->renewal_date?->toDateString()}");
                }
            }
        });
        return $summary;
    }

    /** @return array{inspected: int, transitions: int} */
    public function processExpirations(bool $dryRun = false): array
    {
        $summary = ['inspected' => 0, 'transitions' => 0];
        SaasSubscription::query()->whereIn('status', ['grace_period', 'past_due'])->orderBy('id')->chunkById(100, function ($subscriptions) use (&$summary, $dryRun): void {
            foreach ($subscriptions as $subscription) {
                $summary['inspected']++;
                if (! $subscription->grace_period_ends_at?->isPast()) continue;
                $summary['transitions']++;
                if (! $dryRun) $this->subscriptions->transition($subscription, 'suspended', null, 'Grace period ended.', "subscription-suspended:{$subscription->id}:{$subscription->grace_period_ends_at?->toDateString()}");
            }
        });
        return $summary;
    }
}
