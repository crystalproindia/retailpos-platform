<?php

namespace App\Events\Domain\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class SerializesDomainEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        protected readonly ?int $companyId,
        protected readonly ?int $actorId,
        protected readonly ?string $aggregateType,
        protected readonly ?int $aggregateId,
        protected readonly array $payload = [],
        protected ?string $correlationId = null,
        protected readonly ?string $causationId = null,
        protected readonly ?CarbonImmutable $occurredAt = null,
    ) {
        //
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function actorId(): ?int
    {
        return $this->actorId;
    }

    public function aggregateType(): ?string
    {
        return $this->aggregateType;
    }

    public function aggregateId(): ?int
    {
        return $this->aggregateId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function occurredAt(): CarbonImmutable
    {
        return $this->occurredAt ?? CarbonImmutable::now();
    }

    public function correlationId(): string
    {
        return $this->correlationId ??= Str::uuid()->toString();
    }

    public function causationId(): ?string
    {
        return $this->causationId;
    }
}
