<?php

namespace App\Enums\Crm;

enum LeadStageType: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case DemoScheduled = 'demo_scheduled';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
