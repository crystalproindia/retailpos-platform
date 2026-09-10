<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmInvoice;
use App\Models\User;
use App\Services\Finance\ReceivableService;
use Carbon\CarbonImmutable;

/** Read-only, deterministic lifecycle classification for CRM Customer 360. */
class CustomerLifecycleService
{
    public function __construct(private readonly ReceivableService $receivables) {}

    /** @return array{status:string, explanation:string, recognized_invoice_count:int, recent_recognized_invoice_count:int, last_recognized_invoice_at:?string} */
    public function classify(User $user, CrmCustomer $customer): array
    {
        // Reuse the finance-authorized invoice scope. Draft, cancelled and void
        // documents never establish customer activity or lifecycle state.
        $invoices = $this->receivables->invoiceQuery($user)->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft', 'cancelled', 'void'])->orderByDesc('issue_date')->orderByDesc('created_at')->get();
        // Lifecycle is a reporting-date concept.  Keep it in the company's
        // reporting timezone so a date boundary is never affected by the
        // server timezone (and Carbon's test clock remains deterministic).
        $timezone = $user->company?->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $count = $invoices->count();
        $last = $invoices->first();
        $lastAt = $last ? ($last->issue_date ?? $last->created_at) : null;
        $days = $lastAt ? CarbonImmutable::parse($lastAt->toDateString(), $timezone)->diffInDays($today) : null;
        $atRisk = (int) config('crm_reports.customer_lifecycle.at_risk_after_days', 90);
        $dormant = (int) config('crm_reports.customer_lifecycle.dormant_after_days', 180);
        $newWindow = (int) config('crm_reports.customer_lifecycle.new_customer_days', 30);
        // Day 90 begins At Risk, therefore "within 90 days" means 0–89 days.
        $recentCount = $invoices->filter(fn (CrmInvoice $invoice): bool => CarbonImmutable::parse(($invoice->issue_date ?? $invoice->created_at)->toDateString(), $timezone)->diffInDays($today) < $atRisk)->count();

        [$status, $explanation] = match (true) {
            $count === 0 => ['Prospect', 'No recognized invoice is recorded.'],
            $days >= $dormant => ['Dormant', "Last recognized invoice was {$days} days ago ({$dormant}+ days)."],
            $days >= $atRisk => ['At Risk', "Last recognized invoice was {$days} days ago ({$atRisk}–".($dormant - 1).' days).'],
            $recentCount >= 2 => ['Repeat Customer', "{$recentCount} recognized invoices are recorded within the last {$atRisk} days."],
            $days <= $newWindow => ['New Customer', "One recognized invoice was recorded within the last {$newWindow} days."],
            default => ['Active Customer', "One recognized invoice remains active within {$atRisk} days."],
        };

        return ['status' => $status, 'explanation' => $explanation, 'recognized_invoice_count' => $count, 'recent_recognized_invoice_count' => $recentCount, 'last_recognized_invoice_at' => $lastAt?->toDateString()];
    }
}
