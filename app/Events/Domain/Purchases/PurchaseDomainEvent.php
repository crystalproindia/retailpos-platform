<?php

namespace App\Events\Domain\Purchases;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;
use Carbon\CarbonImmutable;

class PurchaseDomainEvent extends SerializesDomainEvent implements DomainEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly string $key,
        ?int $companyId,
        ?int $actorId,
        ?string $aggregateType,
        ?int $aggregateId,
        array $payload = [],
        ?string $correlationId = null,
        ?string $causationId = null,
        ?CarbonImmutable $occurredAt = null,
    ) {
        parent::__construct($companyId, $actorId, $aggregateType, $aggregateId, $payload, $correlationId, $causationId, $occurredAt);
    }

    public function eventKey(): string
    {
        return $this->key;
    }
}
