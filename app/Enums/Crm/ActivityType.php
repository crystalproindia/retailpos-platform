<?php

namespace App\Enums\Crm;

enum ActivityType: string
{
    case Call = 'call';
    case Meeting = 'meeting';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Task = 'task';
    case FollowUp = 'follow_up';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Meeting => 'Meeting',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::Task => 'Task',
            self::FollowUp => 'Follow-up',
            self::Note => 'Note',
        };
    }
}
