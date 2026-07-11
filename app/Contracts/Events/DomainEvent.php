<?php

namespace App\Contracts\Events;

use Carbon\CarbonImmutable;

interface DomainEvent
{
    public function eventKey(): string;

    public function companyId(): ?int;

    public function actorId(): ?int;

    public function aggregateType(): ?string;

    public function aggregateId(): ?int;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;

    public function occurredAt(): CarbonImmutable;

    public function correlationId(): string;

    public function causationId(): ?string;
}
