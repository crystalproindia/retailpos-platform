<?php

namespace App\Services\Reports;

use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\ExpenseTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProfitAndLossService
{
    public function __construct(private readonly ProfitabilityReportingService $profitability) {}

    /** @param array{ids:array<int,int>|null,warehouse_id:int|null,label?:string} $scope @param array{from:\Carbon\CarbonImmutable,to:\Carbon\CarbonImmutable,timezone:string} $range @param array<string,mixed> $context */
    public function report(User $user, array $scope, array $range, array $context = []): array
    {
        $profit = $this->profitability->report($user, $scope, $range, $context);
        // Reversed originals remain historical financial entries. Their linked
        // posted reversal is negative, so both must be included to net to zero.
        $entries = ExpenseTransaction::query()->where('expense_transactions.company_id', $user->company_id)->whereIn('expense_transactions.status', [ExpenseTransaction::POSTED, ExpenseTransaction::REVERSED])
            ->whereDate('expense_transactions.transaction_date', '>=', $range['from']->toDateString())->whereDate('expense_transactions.transaction_date', '<=', $range['to']->toDateString())
            ->when($scope['ids'] !== null, fn ($query) => $query->whereIn('expense_transactions.branch_id', $scope['ids']));
        $companyWide = $scope['ids'] === null ? 0 : $this->minor((string) ExpenseTransaction::query()->where('company_id', $user->company_id)->whereNull('branch_id')->whereIn('status', [ExpenseTransaction::POSTED, ExpenseTransaction::REVERSED])->whereDate('transaction_date', '>=', $range['from']->toDateString())->whereDate('transaction_date', '<=', $range['to']->toDateString())->where('classification_snapshot', ExpenseCategory::OPERATING_EXPENSE)->sum('amount'));
        $totals = (clone $entries)->selectRaw('classification_snapshot, COALESCE(SUM(amount), 0) amount')->groupBy('classification_snapshot')->pluck('amount', 'classification_snapshot');
        $operating = $this->minor((string) ($totals[ExpenseCategory::OPERATING_EXPENSE] ?? '0'));
        $otherIncome = $this->minor((string) ($totals[ExpenseCategory::OTHER_INCOME] ?? '0'));
        $otherExpenses = $this->minor((string) ($totals[ExpenseCategory::OTHER_EXPENSE] ?? '0'));
        $byCategory = (clone $entries)->where('expense_transactions.classification_snapshot', ExpenseCategory::OPERATING_EXPENSE)->join('expense_categories', 'expense_categories.id', '=', 'expense_transactions.expense_category_id')->selectRaw('expense_categories.id, expense_categories.name, COALESCE(SUM(expense_transactions.amount), 0) amount')->groupBy('expense_categories.id', 'expense_categories.name')->orderByDesc('amount')->get()->map(fn ($row) => ['id' => $row->id, 'category' => $row->name, 'amount' => $this->minor((string) $row->amount)])->all();
        $netSales = $profit['net_sales']; $cogs = $profit['cost_of_goods_sold']; $grossProfit = $profit['gross_profit'];
        $operatingProfit = $grossProfit - $operating; $netProfit = $operatingProfit + $otherIncome - $otherExpenses;
        return ['period' => ['from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString(), 'timezone' => $range['timezone']], 'outlet' => $scope['label'] ?? null, 'gross_sales' => $profit['gross_sales'], 'returns_credits' => $profit['return_impact'], 'net_sales' => $netSales, 'cogs' => $cogs, 'gross_profit' => $grossProfit, 'gross_margin_percent' => $this->percent($grossProfit, $netSales), 'operating_expenses' => $operating, 'operating_expenses_by_category' => $byCategory, 'operating_profit' => $operatingProfit, 'operating_margin_percent' => $this->percent($operatingProfit, $netSales), 'other_income' => $otherIncome, 'other_expenses' => $otherExpenses, 'net_profit' => $netProfit, 'net_margin_percent' => $this->percent($netProfit, $netSales), 'company_wide_expenses' => $companyWide, 'has_unallocated_company_expenses' => $scope['ids'] !== null && $companyWide > 0];
    }

    /** @param array{ids:array<int,int>|null,warehouse_id:int|null,label?:string} $scope @param array{from:\Carbon\CarbonImmutable,to:\Carbon\CarbonImmutable,timezone:string} $range */
    public function monthly(User $user, array $scope, array $range): array
    {
        $rows = [];
        for ($cursor = $range['from']->startOfMonth(); $cursor->lte($range['to']); $cursor = $cursor->addMonth()) {
            $from = $cursor->lt($range['from']) ? $range['from'] : $cursor->startOfMonth();
            $to = $cursor->endOfMonth()->gt($range['to']) ? $range['to'] : $cursor->endOfMonth();
            $rows[] = ['label' => $cursor->format('M Y'), 'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()], 'report' => $this->report($user, $scope, ['from' => $from, 'to' => $to, 'timezone' => $range['timezone']])];
        }
        return $rows;
    }

    private function minor(string $amount): int { return (int) bcmul($amount, '100', 0); }
    private function percent(int $value, int $denominator): ?float { return $denominator > 0 ? round(($value * 100) / $denominator, 2) : null; }
}
