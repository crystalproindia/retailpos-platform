<?php

namespace App\Data\SaasBilling;

class CheckoutSession
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly int $invoiceId,
        public readonly int $subscriptionId,
        public readonly string $currency,
        public readonly string $amount,
        public readonly string $receipt,
        public readonly string $idempotencyKey,
    ) {}
}
