<?php

namespace App\Enums\Crm;

enum InvoiceReminderStage: string
{
    case DueSoon = 'due_soon';
    case DueToday = 'due_today';
    case Overdue = 'overdue';
    case SecondOverdue = 'second_overdue';
    case FinalNotice = 'final_notice';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::DueSoon => 'Due soon',
            self::DueToday => 'Due today',
            self::Overdue => 'Overdue',
            self::SecondOverdue => 'Second overdue reminder',
            self::FinalNotice => 'Final notice',
            self::Manual => 'Manual reminder',
        };
    }

    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }

    public function defaultOffsetDays(): int
    {
        return match ($this) {
            self::DueSoon => -3,
            self::DueToday => 0,
            self::Overdue => 3,
            self::SecondOverdue => 7,
            self::FinalNotice => 15,
            self::Manual => 0,
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::DueSoon => 'Friendly reminder: invoice {invoice_number} is due soon',
            self::DueToday => 'Invoice {invoice_number} is due today',
            self::Overdue => 'Payment reminder: invoice {invoice_number} is overdue',
            self::SecondOverdue => 'Second payment reminder: invoice {invoice_number}',
            self::FinalNotice => 'Final payment reminder: invoice {invoice_number}',
            self::Manual => 'Payment reminder: invoice {invoice_number}',
        };
    }

    public function defaultIntro(): string
    {
        return match ($this) {
            self::DueSoon => 'A friendly reminder that this invoice is due soon. Please let us know if you need any help with payment.',
            self::DueToday => 'This invoice is due today. Please arrange payment at your earliest convenience.',
            self::Overdue => 'This invoice is now overdue. Please review the outstanding balance and arrange payment.',
            self::SecondOverdue => 'This is a second reminder that the invoice balance remains outstanding. Please contact us if there is a payment issue.',
            self::FinalNotice => 'This invoice remains outstanding. Please contact us promptly to confirm the payment arrangement.',
            self::Manual => 'This is a reminder that your invoice balance remains outstanding.',
        };
    }
}
