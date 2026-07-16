<?php

namespace App\Enums\Crm;

enum LeadStageType: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case DemoScheduled = 'demo_scheduled';
    case Proposal = 'proposal';
    case ProformaSent = 'proforma_sent';
    case PartiallyPaid = 'partially_paid';
    case FollowUp = 'follow_up';
    case Won = 'won';
    case Lost = 'lost';
    case Spam = 'spam';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
