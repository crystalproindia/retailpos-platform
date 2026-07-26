<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;

class EmailDeliveryWebhookDiagnostics
{
    /** @return array{last_accepted_at:?string,last_rejected_at:?string,signature_failures:int,events_processed:int,duplicates_ignored:int} */
    public function snapshot(): array
    {
        return [
            'last_accepted_at' => Cache::get($this->key('last_accepted_at')),
            'last_rejected_at' => Cache::get($this->key('last_rejected_at')),
            'signature_failures' => (int) Cache::get($this->key('signature_failures'), 0),
            'events_processed' => (int) Cache::get($this->key('events_processed'), 0),
            'duplicates_ignored' => (int) Cache::get($this->key('duplicates_ignored'), 0),
        ];
    }

    public function accepted(bool $duplicate): void
    {
        Cache::put($this->key('last_accepted_at'), now()->toIso8601String(), now()->addDays(30));
        Cache::increment($this->key($duplicate ? 'duplicates_ignored' : 'events_processed'));
    }

    public function rejected(bool $signatureFailure = false): void
    {
        Cache::put($this->key('last_rejected_at'), now()->toIso8601String(), now()->addDays(30));
        if ($signatureFailure) Cache::increment($this->key('signature_failures'));
    }

    private function key(string $suffix): string
    {
        return 'email-delivery:webhook:'.$suffix;
    }
}
