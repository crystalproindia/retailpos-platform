<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmCustomer;
use App\Models\User;
use App\Repositories\Crm\InvoiceRepository;
use App\Repositories\Crm\LeadRepository;
use App\Services\Finance\ReceivableService;

/** Composes existing, authorized CRM and finance read models; it writes nothing. */
class Customer360Service
{
    public function __construct(private readonly CustomerLifecycleService $lifecycle, private readonly InvoiceRepository $invoices, private readonly ReceivableService $receivables, private readonly LeadRepository $leads) {}

    /** @return array<string,mixed> */
    public function forCustomer(User $user, CrmCustomer $customer): array
    {
        // Never hydrate the nested commercial journey through an unscoped
        // relationship. The lead repository is the branchless/outlet authority
        // used by the rest of CRM, and document rows then belong to that lead.
        $lead = $customer->lead_id
            ? $this->leads->findForUser($user, $customer->lead_id)->load(['stageHistory', 'proformas', 'invoices'])
            : null;
        $history = $this->invoices->customerHistory($user, $customer->id);

        return [
            'lifecycle' => $this->lifecycle->classify($user, $customer),
            'lead' => $lead,
            'latest_activity' => $lead?->activities->first(),
            'stage_history' => $lead?->stageHistory ?? collect(),
            'notes' => $lead?->notes ?? collect(),
            'quotations' => $lead?->quotations ?? collect(),
            'proformas' => $lead?->proformas ?? collect(),
            'invoices' => $history['invoices'],
            // Finance remains the sole authority for balances, payments and credits.
            'finance' => $user->can('finance.receivables.view') ? $this->receivables->customerSummary($user, $customer) : null,
        ];
    }
}
