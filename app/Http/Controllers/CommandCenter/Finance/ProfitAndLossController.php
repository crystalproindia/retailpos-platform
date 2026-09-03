<?php

namespace App\Http\Controllers\CommandCenter\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\FinancialPeriodResolver;
use App\Services\Finance\ProfitAndLossPdfService;
use App\Services\Outlets\OutletAccessService;
use App\Services\Reports\ProfitAndLossService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ProfitAndLossController extends Controller
{
    public function index(Request $request, ProfitAndLossService $reports, FinancialPeriodResolver $periods, OutletAccessService $outlets)
    {
        abort_unless($request->user()->can('finance.profit_and_loss.view'), 403);

        [$scope, $availableOutlets] = $this->scope($request, $outlets);
        $range = $periods->resolve($request->user()->company, $this->filters($request));
        $report = $reports->report($request->user(), $scope, $range, []);
        $outletComparison = $availableOutlets->map(fn ($outlet) => [
            'outlet' => $outlet,
            'report' => $reports->report($request->user(), [
                'ids' => [$outlet->id],
                'warehouse_id' => null,
                'label' => $outlet->name,
            ], $range, []),
        ]);

        return view('command-center.finance.profit-and-loss.index', [
            'report' => $report,
            'monthly' => $reports->monthly($request->user(), $scope, $range),
            'outletComparison' => $outletComparison,
            'outlets' => $availableOutlets,
            'scope' => $scope,
        ]);
    }

    public function csv(Request $request, ProfitAndLossService $reports, FinancialPeriodResolver $periods, OutletAccessService $outlets)
    {
        abort_unless($request->user()->can('finance.profit_and_loss.export'), 403);

        [$scope] = $this->scope($request, $outlets);
        $report = $reports->report($request->user(), $scope, $periods->resolve($request->user()->company, $this->filters($request)), []);

        return Response::streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            foreach ($this->csvRows($report) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'profit-and-loss.csv', ['Content-Type' => 'text/csv']);
    }

    public function pdf(Request $request, ProfitAndLossService $reports, FinancialPeriodResolver $periods, OutletAccessService $outlets, ProfitAndLossPdfService $pdf)
    {
        abort_unless($request->user()->can('finance.profit_and_loss.export'), 403);

        [$scope] = $this->scope($request, $outlets);
        $report = $reports->report($request->user(), $scope, $periods->resolve($request->user()->company, $this->filters($request)), []);

        return $pdf->render($request->user()->company, $report, $scope['label'])->download('profit-and-loss.pdf');
    }

    /** @return list<list<string|int|float|null>> */
    private function csvRows(array $report): array
    {
        $money = fn (string $key): float => $report[$key] / 100;
        $percent = fn (string $key): string|float => $report[$key] ?? '—';

        $rows = [
            ['Profit & Loss'],
            ['Period', $report['period']['from'].' to '.$report['period']['to']],
            [],
            ['Metric', 'Amount'],
            ['Gross Sales', $money('gross_sales')],
            ['Returns / Credits', $money('returns_credits')],
            ['Net Sales', $money('net_sales')],
            ['COGS', $money('cogs')],
            ['Gross Profit', $money('gross_profit')],
            ['Gross Margin %', $percent('gross_margin_percent')],
            ['Operating Expenses', $money('operating_expenses')],
        ];

        foreach ($report['operating_expenses_by_category'] as $category) {
            $rows[] = ['Operating Expense: '.$category['category'], $category['amount'] / 100];
        }

        return [...$rows,
            ['Operating Profit', $money('operating_profit')],
            ['Operating Margin %', $percent('operating_margin_percent')],
            ['Other Income', $money('other_income')],
            ['Other Expenses', $money('other_expenses')],
            ['Net Profit', $money('net_profit')],
            ['Net Margin %', $percent('net_margin_percent')],
        ];
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'period' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'outlet_id' => ['nullable', 'string'],
        ]) + ['period' => $request->input('period', 'this_month')];
    }

    /** @return array{0: array{ids: array<int, int>|null, warehouse_id: null, label: string}, 1: \Illuminate\Support\Collection} */
    private function scope(Request $request, OutletAccessService $outlets): array
    {
        $available = $outlets->accessibleOutlets($request->user());
        $outletId = $request->input('outlet_id');

        if ($outletId === 'all' || (! $outletId && $outlets->hasCompanyWideAccess($request->user()))) {
            abort_unless($outlets->hasCompanyWideAccess($request->user()), 403);

            return [[
                'ids' => null,
                'warehouse_id' => null,
                'label' => 'Company / Consolidated',
            ], $available];
        }

        $outlet = $available->firstWhere('id', (int) $outletId) ?? $outlets->current($request->user());
        abort_unless($available->contains('id', $outlet->id), 403);

        return [[
            'ids' => [$outlet->id],
            'warehouse_id' => null,
            'label' => $outlet->name,
        ], $available];
    }
}
