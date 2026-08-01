<?php

namespace App\Services\Tasks;

use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmLead;
use App\Models\Tasks\TaskRuleSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TaskRuleService
{
    public function __construct(
        private readonly TaskRuleRegistry $registry,
        private readonly TaskService $tasks,
    ) {}

    /** @return array<string, int> */
    public function generate(?int $companyId = null, int $limit = 250, bool $dryRun = false): array
    {
        $counts = ['companies' => 0, 'eligible' => 0, 'created' => 0, 'skipped' => 0];
        Company::query()->when($companyId, fn ($query) => $query->whereKey($companyId))->orderBy('id')->each(function (Company $company) use (&$counts, $limit, $dryRun): void {
            $counts['companies']++;
            foreach (TaskRuleSetting::query()->where('company_id', $company->id)->where('is_enabled', true)->get() as $setting) {
                $definition = $this->registry->definition($setting->rule_key);
                if (! $definition) {
                    continue;
                }

                foreach ($this->eligibleRecords($company->id, $setting->rule_key, $setting->threshold_hours ?? $definition['threshold_hours'], $limit) as [$record, $assignee, $dueAt, $title, $reason]) {
                    $counts['eligible']++;
                    if ($dryRun) {
                        continue;
                    }
                    if ($this->tasks->createRuleTask($company->id, $assignee, $record, $setting->rule_key, $title, $reason, $dueAt)) {
                        $counts['created']++;
                    } else {
                        $counts['skipped']++;
                    }
                }
            }
        });
        Log::info('Smart task rule generation completed.', $counts + ['dry_run' => $dryRun]);

        return $counts;
    }

    /** @return iterable<array{0: object, 1: User, 2: \DateTimeInterface, 3: string, 4: string}> */
    private function eligibleRecords(int $companyId, string $ruleKey, int $thresholdHours, int $limit): iterable
    {
        return match ($ruleKey) {
            'lead_first_contact_due' => CrmLead::query()
                ->where('company_id', $companyId)->whereNull('last_contacted_at')->where('created_at', '<=', now()->subHours($thresholdHours))
                ->whereHas('status', fn ($query) => $query->where('is_active', true)->where('is_won', false)->where('is_lost', false))
                ->with('assignedUser')->limit($limit)->get()
                ->map(fn (CrmLead $lead) => $this->leadRuleTuple($lead, now(), 'Initial contact is overdue.'))->filter(),
            'lead_follow_up_due' => CrmLead::query()
                ->where('company_id', $companyId)->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())
                ->whereHas('status', fn ($query) => $query->where('is_active', true)->where('is_won', false)->where('is_lost', false))
                ->with('assignedUser')->limit($limit)->get()
                ->map(fn (CrmLead $lead) => $this->leadRuleTuple($lead, $lead->next_follow_up_at, 'The lead follow-up time has arrived.'))->filter(),
            'crm_invoice_overdue' => CrmInvoice::query()
                ->where('company_id', $companyId)->where('balance_due', '>', 0)->whereDate('due_date', '<', today())->whereNull('cancelled_at')
                ->with('lead.assignedUser')->limit($limit)->get()
                ->map(function (CrmInvoice $invoice): ?array {
                    $assignee = $invoice->lead?->assignedUser;
                    return $assignee ? [$invoice, $assignee, now(), 'Follow up on overdue invoice', 'The CRM invoice is overdue with an outstanding balance.'] : null;
                })->filter(),
            default => collect(),
        };
    }

    /** @return array{0: CrmLead, 1: User, 2: \DateTimeInterface, 3: string, 4: string}|null */
    private function leadRuleTuple(CrmLead $lead, \DateTimeInterface $dueAt, string $reason): ?array
    {
        return $lead->assignedUser ? [$lead, $lead->assignedUser, $dueAt, 'Follow up with lead', $reason] : null;
    }
}
