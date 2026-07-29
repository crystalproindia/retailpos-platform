<?php

namespace App\Data\SaasBilling;

class RefundRequest
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public readonly string $paymentId,
        public readonly string $amount,
        public readonly string $currency,
        public readonly ?string $reason = null,
        public readonly array $metadata = [],
    ) {}
}
