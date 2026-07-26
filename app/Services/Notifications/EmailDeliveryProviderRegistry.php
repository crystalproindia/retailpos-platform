<?php

namespace App\Services\Notifications;

class EmailDeliveryProviderRegistry
{
    /** @return array{key:?string,label:string,webhook_enabled:bool,production_adapter:bool} */
    public function configuration(): array
    {
        $provider = str((string) config('email-delivery.provider'))->lower()->trim()->toString() ?: null;

        return [
            'key' => $provider,
            'label' => $provider ? ($this->definitions()[$provider]['label'] ?? 'Unsupported provider') : 'No provider selected',
            'webhook_enabled' => (bool) config('email-delivery.webhook.enabled', false),
            'production_adapter' => (bool) ($this->definitions()[$provider]['production_adapter'] ?? false),
        ];
    }

    public function acceptsWebhook(string $provider): bool
    {
        $provider = str($provider)->lower()->trim()->toString();
        $selected = $this->configuration()['key'];

        return $provider === 'generic' && ($selected === null || $selected === 'generic');
    }

    /** @return array<string, array{label:string,production_adapter:bool}> */
    private function definitions(): array
    {
        return [
            'generic' => ['label' => 'Generic signed webhook contract', 'production_adapter' => false],
        ];
    }
}
