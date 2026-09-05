<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Services\Reports\ProfitAndLossService;
use Carbon\CarbonImmutable;

/**
 * Presents the authoritative P&L report as compact, read-only decision data.
 * It intentionally owns no accounting queries or calculations.
 */
class ProfitAndLossInsightService
{
    public function __construct(private readonly ProfitAndLossService $reports) {}

    /**
     * @param array{ids:array<int,int>|null,warehouse_id:int|null,label?:string} $scope
     * @param array{from:CarbonImmutable,to:CarbonImmutable,timezone:string} $range
     * @param array{from:CarbonImmutable,to:CarbonImmutable,timezone:string}|null $previousRange
     * @return array<string,mixed>
     */
    public function summary(User $user, array $scope, array $range, ?array $previousRange = null): array
    {
        $report = $this->reports->report($user, $scope, $range);
        $previous = $previousRange ? $this->reports->report($user, $scope, $previousRange) : null;
        $topExpense = collect($report['operating_expenses_by_category'])
            ->first(fn (array $row): bool => (int) $row['amount'] > 0);

        return [
            'report' => $report,
            'previous' => $previous,
            'metrics' => [
                'net_sales' => $report['net_sales'],
                'gross_profit' => $report['gross_profit'],
                'gross_margin_percent' => $report['gross_margin_percent'],
                'operating_expenses' => $report['operating_expenses'],
                'operating_profit' => $report['operating_profit'],
                'net_profit' => $report['net_profit'],
                'net_margin_percent' => $report['net_margin_percent'],
                'operating_expense_ratio_percent' => $this->percent($report['operating_expenses'], $report['net_sales']),
            ],
            'changes' => $previous ? [
                'net_sales' => $this->change($report['net_sales'], $previous['net_sales']),
                'operating_expenses' => $this->change($report['operating_expenses'], $previous['operating_expenses']),
                'net_profit' => $this->change($report['net_profit'], $previous['net_profit']),
            ] : [],
            'top_operating_expense' => $topExpense,
            'has_unallocated_company_expenses' => $report['has_unallocated_company_expenses'],
        ];
    }

    private function percent(int $value, int $denominator): ?float
    {
        return $denominator > 0 ? round(($value * 100) / $denominator, 2) : null;
    }

    private function change(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) * 100) / abs($previous), 2);
    }
}
