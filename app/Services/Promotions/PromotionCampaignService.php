<?php

namespace App\Services\Promotions;

use App\Enums\Promotions\PromotionStatus;
use App\Events\Domain\Promotions\PromotionDomainEvent;
use App\Models\Promotions\PromotionCampaign;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Str;

class PromotionCampaignService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly DomainEventDispatcher $events) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): PromotionCampaign
    {
        $campaign = PromotionCampaign::create($this->payload($data) + ['company_id' => $user->company_id, 'created_by' => $user->id]);
        $this->auditLogger->record('promotion.campaign.created', $campaign, 'Promotion campaign created');
        $this->dispatch('promotion.campaign.created', $campaign, $user);
        return $campaign;
    }

    /** @param array<string, mixed> $data */
    public function update(PromotionCampaign $campaign, User $user, array $data): PromotionCampaign
    {
        $campaign->update($this->payload($data));
        $this->auditLogger->record('promotion.campaign.updated', $campaign, 'Promotion campaign updated');
        $this->dispatch('promotion.campaign.updated', $campaign, $user);
        return $campaign->refresh();
    }

    public function delete(PromotionCampaign $campaign): void { $campaign->delete(); $this->auditLogger->record('promotion.campaign.deleted', $campaign, 'Promotion campaign deleted'); }
    public function restore(PromotionCampaign $campaign): PromotionCampaign { $campaign->restore(); $this->auditLogger->record('promotion.campaign.restored', $campaign, 'Promotion campaign restored'); return $campaign->refresh(); }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function payload(array $data): array
    {
        $status = $data['status'] ?? PromotionStatus::Draft->value;
        return [
            'name' => $data['name'], 'slug' => $data['slug'] ?: Str::slug($data['name']), 'description' => $data['description'] ?? null,
            'campaign_type' => $data['campaign_type'], 'start_at' => $data['start_at'] ?? null, 'end_at' => $data['end_at'] ?? null,
            'status' => $status, 'priority' => $data['priority'] ?? 100, 'is_active' => $status === PromotionStatus::Active->value,
        ];
    }

    private function dispatch(string $key, PromotionCampaign $campaign, User $user): void
    {
        $this->events->dispatch(new PromotionDomainEvent($key, $campaign->company_id, $user->id, PromotionCampaign::class, $campaign->id, ['campaign_name' => $campaign->name]));
    }
}
