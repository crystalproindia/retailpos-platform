<?php

namespace App\Data\Crm;

readonly class GeneratedSalesMessage
{
    public function __construct(
        public string $subject,
        public string $message,
        public ?string $whatsAppUrl,
        public ?string $emailUrl,
    ) {}
}
