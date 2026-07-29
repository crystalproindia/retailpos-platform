<?php

namespace App\Data\SaasBilling;

class WebhookEvent
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly ?string $paymentId,
        public readonly ?string $orderId,
        public readonly ?string $refundId,
        public readonly ?string $status,
        public readonly ?string $amount,
        public readonly ?string $currency,
        public readonly array $payload = [],
    ) {}
}
