<?php

namespace App\Events\Domain\Crm;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class SupportTicketEvent extends SerializesDomainEvent implements DomainEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly string $key, ?int $companyId, ?int $actorId, ?string $aggregateType, ?int $aggregateId, array $payload = [], ?string $correlationId = null) { parent::__construct($companyId, $actorId, $aggregateType, $aggregateId, $payload, $correlationId); }
    public function eventKey(): string { return $this->key; }
}
