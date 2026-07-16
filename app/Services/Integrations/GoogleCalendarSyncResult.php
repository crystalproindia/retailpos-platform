<?php

namespace App\Services\Integrations;

class GoogleCalendarSyncResult
{
    public function __construct(
        public readonly bool $succeeded,
        public readonly string $message,
    ) {}
}
