<?php

namespace App\Data\SaasBilling;

class PaymentIntent
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public readonly string $providerOrderId,
        public readonly string $status,
        public readonly array $metadata = [],
    ) {}
}
