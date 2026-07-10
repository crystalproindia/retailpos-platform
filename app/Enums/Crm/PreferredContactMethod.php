<?php

namespace App\Enums\Crm;

enum PreferredContactMethod: string
{
    case Email = 'email';
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case Meeting = 'meeting';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Phone => 'Phone',
            self::WhatsApp => 'WhatsApp',
            self::Meeting => 'Meeting',
        };
    }
}
