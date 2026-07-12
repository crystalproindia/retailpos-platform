<?php

namespace App\Services\Promotions;

use App\Events\Domain\Promotions\PromotionDomainEvent;
use App\Models\Promotions\PromotionSimulation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;

class PromotionSimulationService
{
    public function __construct(private readonly PromotionRuleEngine $engine, private readonly AuditLogger $auditLogger, private readonly DomainEventDispatcher $events) {}

    /** @param array<string, mixed> $cart @return array<string, mixed> */
    public function run(User $user, array $cart, ?string $title = null): array
    {
        $result = $this->engine->evaluate($user->company_id, $cart);
        $simulation = PromotionSimulation::create(['company_id' => $user->company_id, 'user_id' => $user->id, 'title' => $title, 'cart_payload' => $cart, 'result_payload' => $result, 'total_before_discount' => $result['total_before_discount'], 'total_discount' => $result['total_discount'], 'total_after_discount' => $result['total_after_discount'], 'simulated_at' => now()]);
        $this->auditLogger->record('promotion.simulation.ran', $simulation, 'Promotion simulator run');
        $this->events->dispatch(new PromotionDomainEvent('promotion.simulation.ran', $user->company_id, $user->id, PromotionSimulation::class, $simulation->id, ['total_discount' => $result['total_discount']]));
        return $result;
    }
}
