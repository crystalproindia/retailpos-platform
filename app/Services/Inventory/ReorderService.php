<?php

namespace App\Services\Inventory;

use App\Events\Domain\Inventory\ReorderSuggested;
use App\Models\Inventory\ReorderRule;
use App\Models\Inventory\ReorderSuggestion;
use App\Models\Inventory\StockLevel;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;

class ReorderService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveRule(User $user, array $data, ?ReorderRule $rule = null): ReorderRule
    {
        $payload = $data + [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'is_active' => true,
        ];

        $model = $rule ? tap($rule)->update($payload) : ReorderRule::create($payload);
        $this->auditLogger->record($rule ? 'inventory.reorder_rule.updated' : 'inventory.reorder_rule.created', $model, 'Inventory reorder rule saved');

        return $model->refresh();
    }

    public function generateSuggestion(ReorderRule $rule, User $user): ?ReorderSuggestion
    {
        $stock = StockLevel::query()
            ->where('company_id', $rule->company_id)
            ->where('product_id', $rule->product_id)
            ->when($rule->warehouse_id, fn ($query) => $query->where('warehouse_id', $rule->warehouse_id))
            ->sum('quantity_available');

        if ((float) $stock > (float) $rule->reorder_point) {
            return null;
        }

        $risk = $stock <= 0 ? 'high' : ($stock <= (float) ($rule->safety_stock ?? 0) ? 'high' : 'medium');
        $estimatedStockout = $rule->average_daily_sales && (float) $rule->average_daily_sales > 0
            ? now()->addDays(max(0, (int) floor((float) $stock / (float) $rule->average_daily_sales)))->toDateString()
            : null;

        $suggestion = ReorderSuggestion::create([
            'company_id' => $rule->company_id,
            'branch_id' => $rule->branch_id,
            'warehouse_id' => $rule->warehouse_id,
            'product_id' => $rule->product_id,
            'current_stock' => $stock,
            'available_stock' => $stock,
            'reorder_point' => $rule->reorder_point,
            'suggested_quantity' => $rule->reorder_quantity,
            'stockout_risk_level' => $risk,
            'estimated_stockout_date' => $estimatedStockout,
            'reason' => "Available stock ({$stock}) is at or below reorder point ({$rule->reorder_point}).",
            'status' => ReorderSuggestion::STATUS_PENDING,
        ]);

        $this->auditLogger->record('inventory.reorder_suggestion.generated', $suggestion, 'Reorder suggestion generated');
        $this->domainEvents->dispatch(new ReorderSuggested(
            companyId: $rule->company_id,
            actorId: $user->id,
            aggregateType: ReorderSuggestion::class,
            aggregateId: $suggestion->id,
            payload: [
                'suggestion_id' => $suggestion->id,
                'product_id' => $suggestion->product_id,
                'suggested_quantity' => $suggestion->suggested_quantity,
                'stockout_risk_level' => $suggestion->stockout_risk_level,
            ],
        ));

        return $suggestion;
    }

    public function review(ReorderSuggestion $suggestion, User $user): ReorderSuggestion
    {
        $suggestion->update([
            'status' => ReorderSuggestion::STATUS_REVIEWED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        $this->auditLogger->record('inventory.reorder_suggestion.reviewed', $suggestion, 'Reorder suggestion reviewed');

        return $suggestion;
    }

    public function dismiss(ReorderSuggestion $suggestion, User $user): ReorderSuggestion
    {
        $suggestion->update([
            'status' => ReorderSuggestion::STATUS_DISMISSED,
            'dismissed_by' => $user->id,
            'dismissed_at' => now(),
        ]);

        $this->auditLogger->record('inventory.reorder_suggestion.dismissed', $suggestion, 'Reorder suggestion dismissed');

        return $suggestion;
    }
}
