<?php

namespace App\Events\Domain\Cms;

use App\Contracts\Events\DomainEvent;
use App\Events\Domain\Concerns\SerializesDomainEvent;

class CmsPagePublished extends SerializesDomainEvent implements DomainEvent
{
    public function eventKey(): string
    {
        return 'cms.page.published';
    }
}
