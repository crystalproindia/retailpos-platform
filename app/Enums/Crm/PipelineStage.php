<?php

namespace App\Enums\Crm;

enum PipelineStage: string
{
    case NewLead = 'new_lead';
    case Contacted = 'contacted';
    case DemoScheduled = 'demo_scheduled';
    case ProposalSent = 'proposal_sent';
    case ProformaSent = 'proforma_sent';
    case PartiallyPaid = 'partially_paid';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::NewLead => 'New Lead',
            self::Contacted => 'Contacted',
            self::DemoScheduled => 'Demo Scheduled',
            self::ProposalSent => 'Proposal Sent',
            self::ProformaSent => 'Proforma Sent',
            self::PartiallyPaid => 'Partially Paid',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    public function statusType(): LeadStageType
    {
        return match ($this) {
            self::NewLead => LeadStageType::New,
            self::Contacted => LeadStageType::Contacted,
            self::DemoScheduled => LeadStageType::DemoScheduled,
            self::ProposalSent => LeadStageType::Proposal,
            self::ProformaSent => LeadStageType::ProformaSent,
            self::PartiallyPaid => LeadStageType::PartiallyPaid,
            self::Won => LeadStageType::Won,
            self::Lost => LeadStageType::Lost,
        };
    }

    public function probability(): int
    {
        return match ($this) {
            self::NewLead => 10,
            self::Contacted => 25,
            self::DemoScheduled => 50,
            self::ProposalSent => 65,
            self::ProformaSent => 80,
            self::PartiallyPaid => 90,
            self::Won => 100,
            self::Lost => 0,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Won, self::Lost], true);
    }

    public function shouldNotify(): bool
    {
        return in_array($this, [self::ProposalSent, self::ProformaSent, self::Won, self::Lost], true);
    }
}
