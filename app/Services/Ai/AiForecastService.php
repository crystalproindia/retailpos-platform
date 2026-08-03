<?php

namespace App\Services\Ai;

use App\Models\Ai\AiForecastResult;
use App\Models\Ai\AiForecastRun;
use App\Models\Ai\AiInsight;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Customers\Customer;
use App\Models\Inventory\StockLevel;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Outlets\OutletAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class AiForecastService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly OutletAccessService $outlets) {}

    public function run(int $companyId, string $type = 'all', ?User $actor = null): Collection
    {
        $types = $type === 'all' ? ['sales', 'inventory', 'customers', 'crm'] : [$type];

        return collect($types)->mapWithKeys(fn (string $forecastType) => [$forecastType => $this->runType($companyId, $forecastType, $actor)]);
    }

    public function dashboard(User $user): array
    {
        $companyId = $user->company_id;
        $outletIds = $this->outlets->accessibleOutlets($user)->pluck('id');
        $scopedResults = fn ($query) => $this->outlets->hasCompanyWideAccess($user) ? $query : $query->whereIn('outlet_id', $outletIds);

        return [
            'latest_runs' => AiForecastRun::query()->where('company_id', $companyId)->latest('completed_at')->limit(6)->get(),
            'sales' => $scopedResults(AiForecastResult::query()->where('company_id', $companyId))->whereHas('run', fn ($query) => $query->where('forecast_type', 'sales')->where('status', 'completed'))->latest('id')->limit(3)->get(),
            'stock_risks' => $scopedResults(AiForecastResult::query()->where('company_id', $companyId))->whereIn('classification', ['stockout_risk', 'overstock_risk', 'slow_moving', 'dead_stock'])->with('product')->latest('score')->limit(8)->get(),
            'customer_segments' => $scopedResults(AiForecastResult::query()->where('company_id', $companyId))->whereHas('run', fn ($query) => $query->where('forecast_type', 'customers')->where('status', 'completed'))->selectRaw('classification, count(*) as total')->groupBy('classification')->pluck('total', 'classification'),
            'crm_priorities' => $scopedResults(AiForecastResult::query()->where('company_id', $companyId))->whereHas('run', fn ($query) => $query->where('forecast_type', 'crm')->where('status', 'completed'))->with('lead')->latest('score')->limit(8)->get(),
            'insights' => $this->scopeInsights(AiInsight::query()->where('company_id', $companyId), $user, $outletIds)->whereIn('status', ['new', 'reviewed'])->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))->latest()->limit(10)->get(),
        ];
    }

    public function review(AiInsight $insight, User $user, string $status): void
    {
        abort_unless($insight->company_id === $user->company_id, 404);
        if (! $this->outlets->hasCompanyWideAccess($user)) {
            $canAccessOutlet = $insight->outlet_id && $this->outlets->accessibleOutlets($user)->contains('id', $insight->outlet_id);
            abort_unless($canAccessOutlet, 404);
        }
        $insight->update(['status' => $status, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        $this->auditLogger->record('ai.insight.'.$status, $insight, 'AI insight marked '.$status);
    }

    private function runType(int $companyId, string $type, ?User $actor): AiForecastRun
    {
        $settings = $this->settings($companyId);
        $historyDays = (int) $settings['minimum_sales_history_days'];
        $company = Company::query()->findOrFail($companyId);
        $timezone = $company->timezone ?: config('app.timezone');
        $today = CarbonImmutable::today($timezone);
        $identity = [
            'company_id' => $companyId,
            'forecast_type' => $type,
            'algorithm_version' => config('ai_forecasting.algorithm_version'),
            'training_start' => $today->subDays($historyDays)->toDateString(),
            'training_end' => $today->subDay()->toDateString(),
        ];
        $run = $this->findRun($identity);

        if ($run && $run->status === 'completed') return $run;
        if ($run && $run->status === 'running') return $run;

        if (! $run) {
            try {
                $run = AiForecastRun::create($identity + [
                    'parameters' => ['history_days' => $historyDays, 'advisory_only' => true, 'settings' => $settings],
                    'forecast_start' => $today->toDateString(), 'forecast_end' => $today->addDays(90)->toDateString(),
                    'status' => 'running', 'started_at' => now(), 'created_by' => $actor?->id,
                ]);
            } catch (QueryException $exception) {
                $existing = $this->findRun($identity);
                if ($existing) return $existing;

                throw $exception;
            }
        } else {
            $run->results()->delete();
            $run->update(['parameters' => ['history_days' => $historyDays, 'advisory_only' => true, 'settings' => $settings], 'status' => 'running', 'data_points' => 0, 'confidence_level' => null, 'safe_error_message' => null, 'started_at' => now(), 'completed_at' => null, 'created_by' => $actor?->id]);
        }

        try {
            $count = match ($type) {
                'sales' => $this->sales($run, $settings),
                'inventory' => $this->inventory($run, $settings),
                'customers' => $this->customers($run, $settings),
                'crm' => $this->crm($run),
                default => throw new \InvalidArgumentException('Unsupported forecast type.'),
            };
            $run->update(['status' => $count === 0 ? 'insufficient_data' : 'completed', 'data_points' => $count, 'confidence_level' => $count >= 56 ? 'high' : ($count >= $historyDays ? 'medium' : 'low'), 'completed_at' => now()]);
        } catch (\Throwable $exception) {
            report($exception);
            $run->update(['status' => 'failed', 'safe_error_message' => 'The forecast could not be prepared. Review source data and try again.', 'completed_at' => now()]);
        }

        if ($actor) $this->auditLogger->record('ai.forecast.run', $run, 'Explainable forecast generated', ['forecast_type' => $type, 'status' => $run->status]);

        return $run->refresh();
    }

    private function sales(AiForecastRun $run, array $settings): int
    {
        $count = 0;
        $company = Company::query()->findOrFail($run->company_id);
        $timezone = $company->timezone ?: config('app.timezone');
        $today = CarbonImmutable::today($timezone);

        foreach (Branch::query()->where('company_id', $run->company_id)->where('is_active', true)->orderBy('id')->get() as $outlet) {
            $daily = $this->dailyCompletedSales($run, $outlet->id, $timezone);
            if ($daily->count() < (int) $settings['minimum_sales_history_days']) continue;
            $mean = (float) $daily->avg();
            $recent = (float) $daily->take(-7)->avg();
            $prediction = round(($mean * .4) + ($recent * .6), 2);
            $variation = max(0.15, min(0.5, $daily->map(fn ($value) => abs((float) $value - $mean))->avg() / max($mean, 1)));
            foreach (config('ai_forecasting.forecast_horizons') as $days) {
                $value = round($prediction * $days, 2);
                AiForecastResult::create(['forecast_run_id' => $run->id, 'company_id' => $run->company_id, 'outlet_id' => $outlet->id, 'period_start' => $today->toDateString(), 'period_end' => $today->addDays($days - 1)->toDateString(), 'predicted_value' => $value, 'lower_bound' => round($value * (1 - $variation), 2), 'upper_bound' => round($value * (1 + $variation), 2), 'classification' => 'sales_forecast', 'explanation' => ['method' => 'weighted moving average', 'plain_language' => "Based on {$daily->count()} completed sales days; recent seven-day activity has greater weight."], 'supporting_metrics' => ['daily_average' => round($mean, 2), 'recent_daily_average' => round($recent, 2), 'history_days' => $daily->count(), 'variation_ratio' => round($variation, 3)]]);
                $count++;
            }
        }
        return $count;
    }

    private function inventory(AiForecastRun $run, array $settings): int
    {
        $count = 0; $horizon = 30; $safety = (int) $settings['safety_stock_days'];
        StockLevel::query()->with('product')->where('company_id', $run->company_id)->whereHas('product', fn ($query) => $query->where('is_active', true))->orderBy('id')->chunkById(100, function ($levels) use ($run, $horizon, $safety, &$count): void {
            foreach ($levels as $level) {
                $sold = (float) PosSaleItem::query()->where('company_id', $run->company_id)->where('product_id', $level->product_id)->whereHas('sale', fn ($query) => $query->where('branch_id', $level->branch_id)->where('status', 'completed')->where('sold_at', '>=', now()->subDays(28)))->sum('quantity');
                $velocity = $sold / 28; if ($velocity <= 0) $velocity = (float) ($level->average_daily_sales ?? 0);
                $available = max(0, (float) $level->quantity_available); $days = $velocity > 0 ? $available / $velocity : null;
                $classification = $velocity <= 0 ? 'dead_stock' : ($days <= 7 ? 'stockout_risk' : ($days > $horizon * 3 ? 'overstock_risk' : ($days > (int) $settings['slow_moving_days'] ? 'slow_moving' : 'stable')));
                $suggested = max(0, ceil(($velocity * ($horizon + $safety)) - $available));
                AiForecastResult::create(['forecast_run_id' => $run->id, 'company_id' => $run->company_id, 'outlet_id' => $level->branch_id, 'product_id' => $level->product_id, 'period_start' => today(), 'period_end' => today()->addDays($horizon), 'predicted_value' => $velocity, 'score' => $days === null ? null : round(max(0, 100 - min(100, $days * 4)), 2), 'classification' => $classification, 'explanation' => ['plain_language' => $this->inventoryExplanation($classification, $days), 'advisory_only' => true], 'supporting_metrics' => ['available_stock' => $available, 'daily_sales_velocity' => round($velocity, 3), 'days_remaining' => $days === null ? null : round($days, 1), 'suggested_reorder_quantity' => $suggested, 'safety_stock_days' => $safety]]);
                if (in_array($classification, ['stockout_risk', 'overstock_risk', 'slow_moving', 'dead_stock'], true)) $this->insight($run->company_id, $level->branch_id, $classification, $level->product_id, $classification === 'stockout_risk' ? 'warning' : 'info', $level->product?->name ?? 'Product', ['available_stock' => $available, 'daily_sales_velocity' => round($velocity, 3), 'suggested_reorder_quantity' => $suggested]);
                $count++;
            }
        }); return $count;
    }

    private function customers(AiForecastRun $run, array $settings): int
    {
        $count = 0; Customer::query()->where('company_id', $run->company_id)->orderBy('id')->chunkById(100, function ($customers) use ($run, $settings, &$count): void { foreach ($customers as $customer) { $days = $customer->last_purchase_at?->diffInDays(now()); $orders = (int) $customer->total_orders_count; $amount = (float) $customer->total_purchase_amount; $segment = $orders === 0 || $days === null ? 'insufficient_data' : ($days >= $settings['customer_lapsed_days'] ? 'lapsed' : ($days >= $settings['customer_at_risk_days'] ? 'at_risk' : ($orders >= 8 ? 'loyal' : ($days <= $settings['customer_active_days'] ? 'active' : 'new')))); if ($amount >= 100000 && $segment !== 'insufficient_data') $segment = 'high_value'; AiForecastResult::create(['forecast_run_id' => $run->id, 'company_id' => $run->company_id, 'outlet_id' => $customer->branch_id, 'customer_id' => $customer->id, 'classification' => $segment, 'score' => $orders === 0 ? null : min(100, round(($orders * 5) + max(0, 30 - min(30, $days ?? 30)) + min(30, $amount / 10000), 2)), 'explanation' => ['plain_language' => 'Segment is based on recency, completed-order count, and purchase value. It does not send a campaign.'], 'supporting_metrics' => ['days_since_purchase' => $days, 'completed_orders' => $orders, 'purchase_value' => round($amount, 2)]]); $count++; } }); return $count;
    }

    private function crm(AiForecastRun $run): int
    {
        $count = 0; CrmLead::query()->with('status')->where('company_id', $run->company_id)->whereNull('deleted_at')->orderBy('id')->chunkById(100, function ($leads) use ($run, &$count): void { foreach ($leads as $lead) { if ($lead->status?->is_won || $lead->status?->is_lost) continue; $overdue = $lead->next_follow_up_at?->isPast() ?? false; $score = min(100, ($overdue ? 40 : 0) + ((int) $lead->follow_up_urgency_rating * 8) + ((int) $lead->buying_interest_rating * 7) + ((int) $lead->client_receptiveness_rating * 3) + ($lead->last_contacted_at?->lt(now()->subDays(7)) ? 10 : 0)); AiForecastResult::create(['forecast_run_id' => $run->id, 'company_id' => $run->company_id, 'outlet_id' => $lead->branch_id, 'lead_id' => $lead->id, 'score' => $score, 'classification' => $score >= 70 ? 'high_priority' : ($score >= 40 ? 'normal_priority' : 'low_priority'), 'explanation' => ['plain_language' => $this->crmExplanation($lead, $overdue), 'rule_based' => true], 'supporting_metrics' => ['overdue' => $overdue, 'follow_up_urgency' => $lead->follow_up_urgency_rating, 'buying_interest' => $lead->buying_interest_rating, 'receptiveness' => $lead->client_receptiveness_rating]]); $count++; } }); return $count;
    }

    private function insight(int $companyId, int $outletId, string $type, int $productId, string $severity, string $productName, array $evidence): void
    {
        $insight = AiInsight::query()->firstOrNew(['company_id' => $companyId, 'outlet_id' => $outletId, 'insight_type' => $type, 'entity_type' => 'product', 'entity_id' => $productId]);
        $insight->fill(['severity' => $severity, 'title' => str($type)->replace('_', ' ')->headline().' · '.$productName, 'explanation' => 'This advisory signal is based on current available stock and completed sales history. Review the supporting transactions before taking action.', 'recommended_action' => 'Review stock, expected demand, and open purchases. No purchase order is created automatically.', 'evidence' => $evidence, 'expires_at' => now()->addDays((int) config('ai_forecasting.insight_retention_days'))]);
        if (! $insight->exists) $insight->status = 'new'; $insight->save();
    }
    private function dailyCompletedSales(AiForecastRun $run, int $outletId, string $timezone): Collection
    {
        $from = CarbonImmutable::parse($run->training_start->toDateString(), $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($run->training_end->toDateString(), $timezone)->endOfDay()->utc();
        $daily = collect();
        PosSale::query()->where('company_id', $run->company_id)->where('branch_id', $outletId)->where('status', 'completed')->whereBetween('sold_at', [$from, $to])->select(['id', 'sold_at', 'total_amount'])->orderBy('id')->chunkById(500, function ($sales) use ($daily, $timezone): void { foreach ($sales as $sale) { $day = $sale->sold_at->setTimezone($timezone)->toDateString(); $daily->put($day, (float) $daily->get($day, 0) + (float) $sale->total_amount); } });
        return $daily->sortKeys()->values();
    }
    private function findRun(array $identity): ?AiForecastRun
    {
        return AiForecastRun::query()->where('company_id', $identity['company_id'])->where('forecast_type', $identity['forecast_type'])->where('algorithm_version', $identity['algorithm_version'])->whereDate('training_start', $identity['training_start'])->whereDate('training_end', $identity['training_end'])->first();
    }
    private function scopeInsights($query, User $user, Collection $outletIds)
    {
        return $this->outlets->hasCompanyWideAccess($user) ? $query : $query->whereIn('outlet_id', $outletIds);
    }
    private function settings(int $companyId): array { return (Setting::query()->where('company_id', $companyId)->where('group', 'ai_forecasting')->where('key', 'settings')->value('value') ?? []) + config('ai_forecasting'); }
    private function inventoryExplanation(string $classification, ?float $days): string { return match ($classification) { 'stockout_risk' => 'Available stock may run out in '.($days === null ? 'an unknown number of' : round($days, 1)).' days at the recent sales rate.', 'overstock_risk' => 'Available stock covers more than the configured planning horizon at the recent sales rate.', 'slow_moving' => 'Recent demand suggests this product is moving slowly.', 'dead_stock' => 'No recent completed sales were available for this product.', default => 'Available stock appears aligned with recent sales velocity.' }; }
    private function crmExplanation(CrmLead $lead, bool $overdue): string { $reasons = []; if ($overdue) $reasons[] = 'follow-up is overdue'; if ($lead->buying_interest_rating) $reasons[] = 'buying interest is '.$lead->buying_interest_rating.'/5'; if ($lead->follow_up_urgency_rating) $reasons[] = 'urgency is '.$lead->follow_up_urgency_rating.'/5'; return $reasons ? 'Priority is higher because '.implode(', ', $reasons).'.' : 'Priority is limited because no overdue follow-up or staff-entered conversation rating is available.'; }
}
