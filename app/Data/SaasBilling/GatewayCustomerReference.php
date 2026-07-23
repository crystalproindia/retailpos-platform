<?php

namespace App\Data\SaasBilling;

class GatewayCustomerReference
{
    /** @param array<string,mixed> $metadata */
    public function __construct(public readonly ?string $providerCustomerId, public readonly array $metadata = []) {}
}
