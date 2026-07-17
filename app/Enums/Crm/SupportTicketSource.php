<?php

namespace App\Enums\Crm;

enum SupportTicketSource: string
{
    case Internal = 'internal'; case Phone = 'phone'; case WhatsApp = 'whatsapp'; case Email = 'email'; case CustomerPortal = 'customer_portal'; case Website = 'website'; case Other = 'other';
    public function label(): string { return str($this->value)->replace('_', ' ')->headline()->toString(); }
}
