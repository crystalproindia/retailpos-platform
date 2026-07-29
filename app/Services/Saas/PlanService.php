<?php

namespace App\Services\Saas;

use App\Models\SaasPlan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** @param array<string, mixed> $data */
    public function save(?SaasPlan $plan, array $data, User $actor): SaasPlan
    {
        return DB::transaction(function () use ($plan, $data, $actor): SaasPlan {
            $features = $data['features'] ?? [];
            $limits = $data['limits'] ?? [];
            unset($data['features'], $data['limits']);

            $plan ??= new SaasPlan();
            $plan->fill($data);
            $plan->save();

            foreach ($features as $key => $enabled) {
                $plan->features()->updateOrCreate(['feature_key' => $key], ['is_enabled' => (bool) $enabled]);
            }

            foreach ($limits as $key => $value) {
                $plan->limits()->updateOrCreate(['limit_key' => $key], [
                    // Blank means unlimited. Zero is never overloaded as unlimited.
                    'limit_value' => filled($value) ? (int) $value : null,
                ]);
            }

            $plan->load(['features', 'limits']);
            $version = (int) $plan->versions()->max('version') + 1;
            $plan->versions()->create([
                'version' => $version,
                'snapshot' => $plan->snapshot(),
                'created_by' => $actor->id,
            ]);

            $this->audit->record(
                $plan->wasRecentlyCreated ? 'saas.plan.created' : 'saas.plan.updated',
                $plan,
                'SaaS plan saved.',
            );

            return $plan;
        });
    }

    public function duplicate(SaasPlan $plan, User $actor): SaasPlan
    {
        $plan->load(['features', 'limits']);

        return $this->save(null, [
            'name' => $plan->name.' copy',
            'code' => Str::slug($plan->code.'-copy-'.Str::lower(Str::random(4))),
            'description' => $plan->description,
            'status' => 'draft',
            'billing_interval' => $plan->billing_interval,
            'currency' => $plan->currency,
            'base_price' => $plan->base_price,
            'setup_fee' => $plan->setup_fee,
            'tax_percentage' => $plan->tax_percentage,
            'trial_days' => $plan->trial_days,
            'grace_period_days' => $plan->grace_period_days,
            'sort_order' => $plan->sort_order,
            'is_public' => false,
            'is_recommended' => false,
            'is_custom' => $plan->is_custom,
            'notes' => $plan->notes,
            'features' => $plan->features->pluck('is_enabled', 'feature_key')->all(),
            'limits' => $plan->limits->mapWithKeys(fn ($limit) => [$limit->limit_key => $limit->limit_value])->all(),
        ], $actor);
    }
}
