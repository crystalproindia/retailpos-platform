<?php

namespace App\Services\Saas;

use App\Models\IntegrationConnection;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class SaasGatewaySettingsService
{
    public function __construct(private readonly AuditLogger $audit, private readonly SaasPaymentGatewayManager $gateways) {}

    public function forPlatform(): ?IntegrationConnection
    {
        return IntegrationConnection::query()->where('provider', SaasPaymentGatewayManager::RAZORPAY_PROVIDER)->latest('id')->first();
    }

    /** @param array<string,mixed> $data */
    public function save(User $actor, array $data): IntegrationConnection
    {
        if (! $actor->is_platform_admin || ! $actor->can('saas.billing.gateway.manage')) {
            abort(403);
        }
        if (($data['mode'] ?? 'test') !== 'test') {
            throw ValidationException::withMessages(['mode' => 'This release permits Razorpay test mode only.']);
        }
        $connection = IntegrationConnection::query()->firstOrNew(['company_id' => $actor->company_id, 'provider' => SaasPaymentGatewayManager::RAZORPAY_PROVIDER]);
        $settings = $connection->settings ?? [];
        $settings['key_id'] = trim((string) ($data['key_id'] ?? $settings['key_id'] ?? ''));
        $settings['mode'] = 'test';
        $settings['enabled'] = (bool) ($data['enabled'] ?? false);
        if (filled($data['key_secret'] ?? null)) $connection->access_token = $data['key_secret'];
        if (filled($data['webhook_secret'] ?? null)) $connection->refresh_token = $data['webhook_secret'];
        $connection->fill([
            'name' => 'Razorpay SaaS Billing',
            'account_email' => $data['account_email'] ?? $connection->account_email,
            'settings' => $settings,
            'status' => $settings['enabled'] && filled($settings['key_id']) && filled($connection->access_token) && filled($connection->refresh_token) ? 'connected' : 'disconnected',
            'connected_by' => $actor->id,
            'connected_at' => now(),
        ])->save();
        $this->audit->record('saas.billing.gateway_saved', $connection, 'Razorpay test-mode gateway settings saved.', ['company_id' => $actor->company_id]);

        return $connection->refresh();
    }

    /** @return array{configured:bool,message:string} */
    public function testConnection(): array
    {
        $connection = $this->gateways->active();
        if (! $connection) return ['configured' => false, 'message' => 'Razorpay test mode is not fully configured and enabled.'];
        if (blank(($connection->settings ?? [])['key_id'] ?? null)) return ['configured' => false, 'message' => 'A Razorpay test key ID is required.'];

        return ['configured' => true, 'message' => 'Razorpay test-mode credentials are available. No payment request was made.'];
    }

    public function maskedKeyId(?IntegrationConnection $connection): ?string
    {
        $key = (string) (($connection?->settings ?? [])['key_id'] ?? '');
        return $key === '' ? null : str($key)->substr(0, 6)->append(str_repeat('•', max(4, strlen($key) - 6)))->toString();
    }
}
