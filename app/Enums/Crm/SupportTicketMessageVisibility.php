<?php

namespace App\Enums\Crm;

enum SupportTicketMessageVisibility: string
{
    case Internal = 'internal'; case CustomerSafe = 'customer_safe';
    public function label(): string { return $this === self::Internal ? 'Internal note' : 'Customer-safe reply'; }
}
