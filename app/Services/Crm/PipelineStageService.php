<?php

namespace App\Services\Crm;

use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\PipelineStage;
use App\Enums\Crm\ProformaStatus;
use App\Enums\Crm\QuotationStatus;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use Illuminate\Support\Collection;

class PipelineStageService
{
    /**
     * @return Collection<string, CrmLeadStatus>
     */
    public function statusesForCompany(int $companyId): Collection
    {
        return collect(PipelineStage::cases())
            ->mapWithKeys(fn (PipelineStage $stage): array => [$stage->value => $this->statusFor($companyId, $stage)]);
    }

    public function statusFor(int $companyId, PipelineStage $stage): CrmLeadStatus
    {
        return CrmLeadStatus::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'stage_type' => $stage->statusType()->value,
            ],
            [
                'name' => $stage->label(),
                'slug' => $this->slugFor($stage),
                'tone' => $this->toneFor($stage),
                'probability' => $stage->probability(),
                'is_won' => $stage === PipelineStage::Won,
                'is_lost' => $stage === PipelineStage::Lost,
                'is_active' => true,
                'sort_order' => $this->sortOrderFor($stage),
            ],
        );
    }

    public function stageFor(CrmLead $lead): PipelineStage
    {
        $statusType = $lead->status?->stage_type;

        if (in_array($statusType, [LeadStageType::Won, LeadStageType::Lost, LeadStageType::Spam], true)) {
            return $statusType === LeadStageType::Won ? PipelineStage::Won : PipelineStage::Lost;
        }

        $proformaStatus = $lead->latestProforma?->status;
        if ($proformaStatus === ProformaStatus::PartiallyPaid) {
            return PipelineStage::PartiallyPaid;
        }

        if (in_array($statusType, [LeadStageType::PartiallyPaid, LeadStageType::ProformaSent], true)) {
            return $statusType === LeadStageType::PartiallyPaid ? PipelineStage::PartiallyPaid : PipelineStage::ProformaSent;
        }

        if (in_array($proformaStatus, [ProformaStatus::Sent, ProformaStatus::Overdue, ProformaStatus::Paid, ProformaStatus::Converted], true)) {
            return PipelineStage::ProformaSent;
        }

        if ($statusType === LeadStageType::Proposal
            || in_array($lead->latestQuotation?->status, [QuotationStatus::Sent, QuotationStatus::Accepted, QuotationStatus::Converted], true)) {
            return PipelineStage::ProposalSent;
        }

        if ($statusType === LeadStageType::DemoScheduled || $lead->latestDemoSchedule?->isActive()) {
            return PipelineStage::DemoScheduled;
        }

        if (in_array($statusType, [LeadStageType::Contacted, LeadStageType::Qualified, LeadStageType::FollowUp], true)) {
            return PipelineStage::Contacted;
        }

        return PipelineStage::NewLead;
    }

    public function stageForStatus(CrmLeadStatus $status): PipelineStage
    {
        return match ($status->stage_type) {
            LeadStageType::Contacted, LeadStageType::Qualified, LeadStageType::FollowUp => PipelineStage::Contacted,
            LeadStageType::DemoScheduled => PipelineStage::DemoScheduled,
            LeadStageType::Proposal => PipelineStage::ProposalSent,
            LeadStageType::ProformaSent => PipelineStage::ProformaSent,
            LeadStageType::PartiallyPaid => PipelineStage::PartiallyPaid,
            LeadStageType::Won => PipelineStage::Won,
            LeadStageType::Lost, LeadStageType::Spam => PipelineStage::Lost,
            default => PipelineStage::NewLead,
        };
    }

    private function sortOrderFor(PipelineStage $stage): int
    {
        return array_search($stage, PipelineStage::cases(), true) + 1;
    }

    private function toneFor(PipelineStage $stage): string
    {
        return match ($stage) {
            PipelineStage::NewLead => 'neutral',
            PipelineStage::Contacted, PipelineStage::DemoScheduled, PipelineStage::PartiallyPaid => 'info',
            PipelineStage::ProposalSent, PipelineStage::ProformaSent => 'warning',
            PipelineStage::Won => 'success',
            PipelineStage::Lost => 'danger',
        };
    }

    private function slugFor(PipelineStage $stage): string
    {
        return match ($stage) {
            PipelineStage::NewLead => 'new',
            PipelineStage::Contacted => 'contacted',
            PipelineStage::DemoScheduled => 'demo-scheduled',
            PipelineStage::ProposalSent => 'proposal-sent',
            PipelineStage::ProformaSent => 'proforma-sent',
            PipelineStage::PartiallyPaid => 'partially-paid',
            PipelineStage::Won => 'won',
            PipelineStage::Lost => 'lost',
        };
    }
}
