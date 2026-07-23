<?php

namespace App\Services\Saas;

use App\Contracts\SaasBilling\PaymentGateway;
use App\Models\IntegrationConnection;

class SaasPaymentGatewayManager
{
    public const RAZORPAY_PROVIDER = 'razorpay_saas_billing';

    public function active(): ?IntegrationConnection
    {
        return IntegrationConnection::query()->where('provider', self::RAZORPAY_PROVIDER)->where('status', 'connected')->get()
            ->first(fn (IntegrationConnection $connection): bool => (($connection->settings ?? [])['enabled'] ?? false) === true && (($connection->settings ?? [])['mode'] ?? 'test') === 'test');
    }

    public function gateway(IntegrationConnection $connection): PaymentGateway
    {
        if ($connection->provider !== self::RAZORPAY_PROVIDER || (($connection->settings ?? [])['mode'] ?? 'test') !== 'test') {
            throw new SaasPaymentGatewayException('Only a connected Razorpay test-mode billing gateway can be used.');
        }

        return new RazorpayPaymentGateway($connection);
    }
}
