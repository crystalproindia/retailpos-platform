<?php

namespace App\Services\Promotions;

use App\Enums\Promotions\PromotionStatus;
use App\Events\Domain\Promotions\PromotionDomainEvent;
use App\Models\Promotions\PromotionRule;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromotionRuleService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly DomainEventDispatcher $events, private readonly PromotionSettingsService $settings) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): PromotionRule
    {
        return DB::transaction(function () use ($user, $data): PromotionRule {
            $rule = PromotionRule::create($this->payload($data) + ['company_id' => $user->company_id, 'created_by' => $user->id]);
            $this->syncRelations($rule, $data);
            $this->auditLogger->record('promotion.rule.created', $rule, 'Promotion rule created');
            $this->dispatch('promotion.rule.created', $rule, $user);
            return $rule->refresh()->load($this->relations());
        });
    }

    /** @param array<string, mixed> $data */
    public function update(PromotionRule $rule, User $user, array $data): PromotionRule
    {
        return DB::transaction(function () use ($rule, $user, $data): PromotionRule {
            $rule->update($this->payload($data));
            $this->syncRelations($rule, $data);
            $this->auditLogger->record('promotion.rule.updated', $rule, 'Promotion rule updated');
            $this->dispatch('promotion.rule.updated', $rule, $user);
            return $rule->refresh()->load($this->relations());
        });
    }

    public function activate(PromotionRule $rule, User $user): PromotionRule
    {
        $this->assertApproval($rule);
        $rule->update(['status' => PromotionStatus::Active->value, 'is_active' => true]);
        $this->auditLogger->record('promotion.rule.activated', $rule, 'Promotion rule activated');
        $this->dispatch('promotion.rule.activated', $rule, $user);
        return $rule->refresh();
    }

    public function pause(PromotionRule $rule, User $user): PromotionRule
    {
        $rule->update(['status' => PromotionStatus::Paused->value, 'is_active' => false]);
        $this->auditLogger->record('promotion.rule.paused', $rule, 'Promotion rule paused');
        $this->dispatch('promotion.rule.paused', $rule, $user);
        return $rule->refresh();
    }

    public function approve(PromotionRule $rule, User $user): PromotionRule
    {
        $rule->update(['approved_by' => $user->id, 'approved_at' => now()]);
        $this->auditLogger->record('promotion.rule.approved', $rule, 'Promotion rule approved');
        $this->dispatch('promotion.approval.required', $rule, $user, ['approved' => true]);
        return $rule->refresh();
    }

    public function delete(PromotionRule $rule): void { $rule->delete(); $this->auditLogger->record('promotion.rule.deleted', $rule, 'Promotion rule deleted'); }
    public function restore(PromotionRule $rule): PromotionRule { $rule->restore(); $this->auditLogger->record('promotion.rule.restored', $rule, 'Promotion rule restored'); return $rule->refresh(); }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function payload(array $data): array
    {
        $status = $data['status'] ?? PromotionStatus::Draft->value;
        return [
            'campaign_id' => $data['campaign_id'] ?? null, 'name' => $data['name'], 'slug' => $data['slug'] ?: Str::slug($data['name']), 'description' => $data['description'] ?? null,
            'promotion_type' => $data['promotion_type'], 'discount_type' => $data['discount_type'] ?? null, 'priority' => $data['priority'] ?? 100,
            'stackable' => (bool) ($data['stackable'] ?? false), 'exclusive' => (bool) ($data['exclusive'] ?? false), 'requires_coupon' => (bool) ($data['requires_coupon'] ?? false), 'auto_apply' => (bool) ($data['auto_apply'] ?? false),
            'start_at' => $data['start_at'] ?? null, 'end_at' => $data['end_at'] ?? null, 'usage_limit_total' => $data['usage_limit_total'] ?? null, 'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'usage_limit_per_day' => $data['usage_limit_per_day'] ?? null, 'minimum_bill_amount' => $data['minimum_bill_amount'] ?? null, 'minimum_quantity' => $data['minimum_quantity'] ?? null,
            'maximum_discount_amount' => $data['maximum_discount_amount'] ?? null, 'status' => $status, 'is_active' => $status === PromotionStatus::Active->value,
        ];
    }

    /** @param array<string, mixed> $data */
    private function syncRelations(PromotionRule $rule, array $data): void
    {
        foreach (['productTargets' => 'product_targets', 'categoryTargets' => 'category_targets', 'brandTargets' => 'brand_targets', 'variantTargets' => 'variant_targets', 'branchTargets' => 'branch_targets', 'channelTargets' => 'channel_targets'] as $relation => $key) {
            $rule->{$relation}()->delete();
            foreach (($data[$key] ?? []) as $target) {
                $field = match ($relation) { 'productTargets', 'variantTargets' => 'product_id', 'categoryTargets' => 'category_id', 'brandTargets' => 'brand_id', 'branchTargets' => 'branch_id', 'channelTargets' => 'sales_channel_id' };
                if (empty($target[$field])) continue;
                $rule->{$relation}()->create(['company_id' => $rule->company_id] + $target);
            }
        }
        $rule->conditions()->delete();
        foreach (($data['conditions'] ?? []) as $condition) { $rule->conditions()->create(['company_id' => $rule->company_id] + $condition); }
        $rule->actions()->delete();
        foreach (($data['actions'] ?? []) as $action) { $rule->actions()->create(['company_id' => $rule->company_id] + $action); }
    }

    private function assertApproval(PromotionRule $rule): void
    {
        $settings = $this->settings->settings($rule->company_id);
        abort_if($settings->require_approval_for_promotions && ! $rule->approved_at, 422, 'This promotion requires approval before activation.');
    }

    /** @return array<int, string> */
    private function relations(): array { return ['campaign', 'conditions', 'actions.freeProduct', 'productTargets', 'categoryTargets', 'brandTargets', 'variantTargets', 'branchTargets', 'channelTargets', 'coupons']; }

    /** @param array<string, mixed> $payload */
    private function dispatch(string $key, PromotionRule $rule, User $user, array $payload = []): void
    {
        $this->events->dispatch(new PromotionDomainEvent($key, $rule->company_id, $user->id, PromotionRule::class, $rule->id, $payload + ['rule_name' => $rule->name]));
    }
}
