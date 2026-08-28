<?php

namespace App\Services\Finance;

use App\Models\Crm\CrmInvoicePayment;
use App\Models\Finance\CrmPaymentNumberSequence;
use Illuminate\Database\QueryException;

class CrmPaymentNumberService
{
    public function nextPaymentReference(int $companyId, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $next = $this->next($companyId, 'company:'.$companyId, 'payment_reference', $year, 'payment_reference', 'RPOS-PAY');

        return sprintf('RPOS-PAY-%d-%05d', $year, $next);
    }

    public function nextReceiptNumber(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $next = $this->next(null, 'global', 'receipt_number', $year, 'receipt_number', 'RPOS-RCPT');

        return sprintf('RPOS-RCPT-%d-%05d', $year, $next);
    }

    private function next(?int $companyId, string $scopeKey, string $type, int $year, string $column, string $prefix): int
    {
        $sequence = CrmPaymentNumberSequence::query()
            ->where('scope_key', $scopeKey)
            ->where('sequence_type', $type)
            ->where('calendar_year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            try {
                $sequence = CrmPaymentNumberSequence::create([
                    'company_id' => $companyId,
                    'scope_key' => $scopeKey,
                    'sequence_type' => $type,
                    'calendar_year' => $year,
                    'last_sequence' => $this->highestExisting($companyId, $column, $prefix, $year),
                ]);
            } catch (QueryException) {
                $sequence = CrmPaymentNumberSequence::query()
                    ->where('scope_key', $scopeKey)
                    ->where('sequence_type', $type)
                    ->where('calendar_year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence = CrmPaymentNumberSequence::query()->lockForUpdate()->findOrFail($sequence->id);
        }

        // Imported or manually recorded historical payments may not have a sequence row yet.
        $highest = $this->highestExisting($companyId, $column, $prefix, $year);
        $next = max((int) $sequence->last_sequence, $highest) + 1;
        $sequence->update(['last_sequence' => $next]);

        return $next;
    }

    private function highestExisting(?int $companyId, string $column, string $prefix, int $year): int
    {
        return CrmInvoicePayment::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where($column, 'like', $prefix.'-'.$year.'-%')
            ->lockForUpdate()
            ->pluck($column)
            ->map(fn (string $number): int => preg_match('/-(\d+)$/', $number, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;
    }
}
