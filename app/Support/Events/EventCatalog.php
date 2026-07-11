<?php

namespace App\Support\Events;

use Illuminate\Support\Collection;

class EventCatalog
{
    /**
     * @return Collection<string, array<string, mixed>>
     */
    public function all(): Collection
    {
        return collect(config('events.catalog', []));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $eventKey): ?array
    {
        return $this->all()->get($eventKey);
    }

    /**
     * @return array<int, string>
     */
    public function defaultChannels(string $eventKey): array
    {
        return $this->find($eventKey)['default_channels'] ?? ['database'];
    }

    /**
     * @return array<int, string>
     */
    public function allowedChannels(string $eventKey): array
    {
        return $this->find($eventKey)['allowed_channels'] ?? ['database'];
    }

    public function severity(string $eventKey): string
    {
        return $this->find($eventKey)['severity'] ?? 'info';
    }

    public function category(string $eventKey): string
    {
        return $this->find($eventKey)['category'] ?? 'System';
    }

    public function channelEnabled(string $channel): bool
    {
        return (bool) (config("events.channels.{$channel}.enabled") ?? false);
    }

    /**
     * @return Collection<string, Collection<string, array<string, mixed>>>
     */
    public function grouped(): Collection
    {
        return $this->all()->groupBy('category');
    }
}
