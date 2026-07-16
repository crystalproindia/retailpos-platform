<?php

namespace App\Events\Domain\Crm;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class PipelineStageChanged extends SerializesDomainEvent implements DomainEvent
{
    public function eventKey(): string
    {
        return 'crm.pipeline.stage_changed';
    }
}
