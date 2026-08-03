<?php

namespace App\Services\Pos;

use App\Models\Pos\PosInvoiceSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class PosNumberService
{
    public function next(int $companyId, int $branchId, ?string $prefix = null, ?Carbon $at = null): array
    {
        $at ??= now();
        $financialYear = $this->financialYear($at);
        $prefix = strtoupper($prefix ?: 'POS');
        $sequence = PosInvoiceSequence::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('financial_year', $financialYear)
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            try {
                $sequence = PosInvoiceSequence::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'financial_year' => $financialYear,
                    'prefix' => $prefix,
                    'last_sequence' => 0,
                ]);
            } catch (QueryException) {
                // A concurrent counter allocated this series first; lock its row and continue.
                $sequence = PosInvoiceSequence::query()
                    ->where('company_id', $companyId)
                    ->where('branch_id', $branchId)
                    ->where('financial_year', $financialYear)
                    ->where('prefix', $prefix)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $sequence->increment('last_sequence');

        return [
            'number' => sprintf('%s-%s-%06d', $prefix, $financialYear, $sequence->last_sequence),
            'financial_year' => $financialYear,
        ];
    }

    public function heldReference(): string
    {
        return 'HLD-'.strtoupper(str()->random(12));
    }

    private function financialYear(Carbon $at): string
    {
        $start = $at->month >= 4 ? $at->year : $at->year - 1;

        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }
}
