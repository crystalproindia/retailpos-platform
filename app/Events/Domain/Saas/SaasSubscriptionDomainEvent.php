<?php

namespace App\Events\Domain\Saas;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class SaasSubscriptionDomainEvent extends SerializesDomainEvent implements DomainEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly string $key, ?int $companyId, ?int $actorId, ?int $subscriptionId, array $payload = [], ?string $correlationId = null)
    {
        parent::__construct($companyId, $actorId, \App\Models\SaasSubscription::class, $subscriptionId, $payload, $correlationId);
    }

    public function eventKey(): string
    {
        return $this->key;
    }
}
