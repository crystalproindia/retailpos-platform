<?php

namespace App\Data\SaasBilling;

class PaymentVerification
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public readonly string $paymentId,
        public readonly ?string $orderId,
        public readonly string $status,
        public readonly string $amount,
        public readonly string $currency,
        public readonly ?string $method = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
        public readonly array $metadata = [],
    ) {}

    public function isConfirmed(): bool
    {
        return in_array($this->status, ['captured', 'paid'], true);
    }
}
