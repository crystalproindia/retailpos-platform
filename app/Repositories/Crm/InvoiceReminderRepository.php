<?php

namespace App\Repositories\Crm;

use App\Models\Crm\CrmInvoice;
use App\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Builder;

class InvoiceReminderRepository
{
    public function automaticCandidates(?int $companyId = null): Builder
    {
        return CrmInvoice::query()
            ->with(['company', 'creator'])
            ->whereHas('company', fn (Builder $company) => $company->where('is_active', true))
            ->whereNotNull('due_date')
            ->whereNotNull('billing_email')
            ->where('balance_due', '>', 0)
            ->whereNotIn('status', ['draft', 'paid', 'cancelled', 'void'])
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId));
    }

    /** @return array{queued_today:int,sent_today:int,failed:int,upcoming_due_soon:int,overdue_awaiting:int,invalid_email:int} */
    public function summary(int $companyId, int $dueSoonDays, string $timezone): array
    {
        $today = now($timezone)->startOfDay();
        $deliveries = NotificationDelivery::query()
            ->where('company_id', $companyId)
            ->whereNotNull('reminder_stage');

        $invoices = CrmInvoice::query()
            ->where('company_id', $companyId)
            ->where('balance_due', '>', 0)
            ->whereNotIn('status', ['draft', 'paid', 'cancelled', 'void']);

        return [
            'queued_today' => (clone $deliveries)->whereDate('queued_at', $today)->count(),
            'sent_today' => (clone $deliveries)->whereDate('sent_at', $today)->whereIn('status', ['sent', 'delivered'])->count(),
            'failed' => (clone $deliveries)->whereIn('status', ['temporarily_failed', 'permanently_failed', 'bounced', 'rejected'])->count(),
            'upcoming_due_soon' => (clone $invoices)->whereDate('due_date', $today->addDays($dueSoonDays))->count(),
            'overdue_awaiting' => (clone $invoices)->whereDate('due_date', '<', $today)->count(),
            'invalid_email' => (clone $invoices)->where(fn (Builder $query) => $query->whereNull('billing_email')->orWhere('billing_email', 'not like', '%@%'))->count(),
        ];
    }
}
