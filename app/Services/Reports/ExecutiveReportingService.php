<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\Pos\PosReturn;
use App\Models\Pos\PosSale;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseReturn;
use App\Models\User;
use App\Services\Finance\ProfitAndLossInsightService;
use App\Services\Finance\PayableService;
use App\Services\Finance\ReceivableService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExecutiveReportingService
{
    public function __construct(
        private readonly RetailReportingService $reports,
        private readonly ProfitabilityReportingService $profitability,
        private readonly ReceivableService $receivables,
        private readonly PayableService $payables,
        private readonly ProfitAndLossInsightService $profitAndLoss,
    ) {}

    /** @param array<string, mixed> $filters */
    public function dashboard(User $user, array $filters, bool $compare = true): array
    {
        $current = $this->reports->summary($user, $filters);
        $previous = $compare ? $this->reports->summary($user, $this->previousFilters($filters, $current['range'])) : null;
        $context = $this->emptyContext();
        $profit = $current['reports']['profitability'] ?? null;
        $previousProfit = $previous['reports']['profitability'] ?? null;
        $profitTrend = $profit
            ? $this->profitability->trend($user, $current['scope'], $current['range'], $context)
            : ['granularity' => $this->granularity($current['range']), 'points' => []];
        $operationsTrend = $this->operationsTrend($user, $current['scope'], $current['range']);
        $gst = $this->gstPosition($current);
        $previousGst = $previous ? $this->gstPosition($previous) : null;
        $outlets = $this->outletRows($user, $current, $profit);
        $salespeople = $this->salespersonRows($current, $profit);
        $products = $this->productRows($profit);
        $financial = $this->financialPosition($user, $current, $filters);
        $profitAndLoss = $user->can('finance.profit_and_loss.view')
            ? $this->profitAndLoss->summary(
                $user,
                $current['scope'],
                $current['range'],
                $previous ? $previous['range'] : null,
            )
            : null;

        return [
            'scope' => $current['scope'],
            'range' => $current['range'],
            'compare' => $compare,
            'comparison_label' => $previous ? $previous['range']['from']->toDateString().' to '.$previous['range']['to']->toDateString() : null,
            'kpis' => $this->kpis($current, $previous, $profit, $previousProfit, $gst, $previousGst, $profitTrend, $operationsTrend),
            'charts' => [
                'profitability' => $profitTrend,
                'operations' => $operationsTrend,
            ],
            'financial' => $financial,
            'profit_and_loss' => $profitAndLoss,
            'gst' => $gst,
            'products' => $products,
            'outlets' => $outlets,
            'salespeople' => $salespeople,
            'insights' => $this->insights($current, $profit, $gst, $products, $outlets, $financial, $profitAndLoss),
            'profitability_available' => $profit !== null,
            'profitability_complete' => $profit !== null && (int) $profit['unavailable_cost_item_count'] === 0,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function kpis(array $current, ?array $previous, ?array $profit, ?array $previousProfit, array $gst, ?array $previousGst, array $profitTrend, array $operationsTrend): array
    {
        $knownCost = $profit && ((int) $profit['captured_item_count'] + (int) $profit['reconstructed_item_count']) > 0;
        $previousKnownCost = $previousProfit && ((int) $previousProfit['captured_item_count'] + (int) $previousProfit['reconstructed_item_count']) > 0;
        $netSales = $profit['net_sales'] ?? $current['metrics']['net_sales'];
        $previousNetSales = $previousProfit['net_sales'] ?? ($previous['metrics']['net_sales'] ?? null);
        $purchase = $current['reports']['purchases'];
        $previousPurchase = $previous['reports']['purchases'] ?? null;

        return [
            $this->kpi('net_sales', 'Net Sales', $netSales, 'money', $previousNetSales, 'reports.show', 'sales', 'teal', array_column($profitTrend['points'], 'net_sales')),
            $this->kpi('gross_profit', 'Gross Profit', $knownCost ? $profit['gross_profit'] : null, 'money', $previousKnownCost ? $previousProfit['gross_profit'] : null, 'reports.show', 'profitability', 'emerald', array_column($profitTrend['points'], 'gross_profit')),
            $this->kpi('gross_margin', 'Gross Margin', $knownCost ? $profit['gross_margin_percent'] : null, 'percent', $previousKnownCost ? $previousProfit['gross_margin_percent'] : null, 'reports.show', 'profitability', 'sky', [], 'points'),
            $this->kpi('purchases', 'Purchases', $purchase['total'], 'money', $previousPurchase['total'] ?? null, 'reports.show', 'purchases', 'indigo', array_column($operationsTrend['points'], 'purchases')),
            $this->kpi('receivables', 'Receivables', $current['reports']['outstanding']['outstanding'], 'money', $previous['reports']['outstanding']['outstanding'] ?? null, 'reports.show', 'outstanding', 'amber'),
            $this->kpi('payables', 'Payables', $purchase['outstanding'], 'money', $previousPurchase['outstanding'] ?? null, 'purchases.reports.index', null, 'rose'),
            $this->kpi('gst_position', 'GST Position', $gst['net'], 'money', $previousGst['net'] ?? null, 'reports.show', 'gst', 'violet'),
            $this->kpi('inventory_value', 'Inventory Value', $current['metrics']['stock_value'], 'money', null, 'reports.show', 'inventory', 'slate'),
        ];
    }

    /** @return array<string, mixed> */
    private function kpi(string $key, string $label, mixed $value, string $format, mixed $previous, string $route, ?string $report, string $accent, array $sparkline = [], string $changeUnit = 'percent'): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'format' => $format,
            'previous' => $previous,
            'change' => $this->change($value, $previous, $changeUnit),
            'change_unit' => $changeUnit,
            'route' => $route,
            'report' => $report,
            'accent' => $accent,
            'sparkline' => array_slice($sparkline, -16),
        ];
    }

    private function change(mixed $current, mixed $previous, string $unit): ?string
    {
        if (! is_numeric($current) || ! is_numeric($previous)) {
            return null;
        }

        if ($unit === 'points') {
            return number_format((float) $current - (float) $previous, 1, '.', '');
        }

        if ((float) $previous == 0.0) {
            return null;
        }

        return number_format((((float) $current - (float) $previous) / abs((float) $previous)) * 100, 1, '.', '');
    }

    /** @return array{output:int,input:int,net:int,incomplete_count:int} */
    private function gstPosition(array $summary): array
    {
        $output = collect(['cgst', 'sgst', 'igst', 'cess'])->sum(fn (string $key): int => (int) $summary['reports']['gst'][$key]);
        $input = (int) $summary['reports']['purchases']['tax'];

        return ['output' => $output, 'input' => $input, 'net' => $output - $input, 'incomplete_count' => (int) $summary['reports']['gst']['incomplete_count']];
    }

    /** @return array{customers:array<int,array<string,mixed>>,suppliers:array<int,array<string,mixed>>,receivables:int,payables:int,overdue_receivables:int,overdue_payables:int} */
    private function financialPosition(User $user, array $summary, array $filters): array
    {
        $range = $summary['range'];
        $financeFilters = ['from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString(), 'outlet_id' => $filters['outlet_id'] ?? null];
        $receivables = $this->receivables->snapshot($user, $financeFilters, $range['to']->startOfDay());
        $payables = $this->payables->snapshot($user, $financeFilters, $range['to']->startOfDay());

        return [
            'customers' => $receivables['customers'],
            'suppliers' => $payables['suppliers'],
            'receivables' => $receivables['metrics']['outstanding'],
            'payables' => $payables['metrics']['payable'],
            'overdue_receivables' => $receivables['metrics']['overdue'],
            'overdue_payables' => $payables['metrics']['overdue'],
            'customer_credits' => $receivables['metrics']['customer_credits'],
            'refund_due' => 0,
        ];
    }

    /** @return array{top:array<int,array<string,mixed>>,slow:array<int,array<string,mixed>>} */
    private function productRows(?array $profit): array
    {
        if ($profit === null) {
            return ['top' => [], 'slow' => []];
        }

        $rows = collect($profit['product_rows'])->filter(fn (array $row): bool => filled($row['dimension']));
        $top = $rows->sortByDesc('net_sales')->take(6)->values()->all();
        $slow = $rows->filter(fn (array $row): bool => (float) $row['net_quantity'] > 0)
            ->sortBy(fn (array $row): array => [(float) $row['net_quantity'], $row['net_sales']])
            ->take(6)->values()->all();

        return ['top' => $top, 'slow' => $slow];
    }

    /** @return array<int, array<string, mixed>> */
    private function outletRows(User $user, array $summary, ?array $profit): array
    {
        $scope = $summary['scope'];
        $range = $summary['range'];
        $profitRows = collect($profit['outlet_rows'] ?? [])->keyBy('dimension');
        $salesRows = collect($summary['reports']['outlets']['rows'])->keyBy('outlet');
        $purchaseRows = PurchaseInvoice::query()
            ->where('company_id', $user->company_id)->whereNotIn('status', ['cancelled', 'draft'])
            ->when($scope['ids'] !== null, fn (Builder $query) => $query->whereIn('branch_id', $scope['ids']))
            ->when($scope['warehouse_id'], fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->whereDate('supplier_invoice_date', '>=', $range['from']->toDateString())
            ->whereDate('supplier_invoice_date', '<=', $range['to']->toDateString())
            ->selectRaw('branch_id, SUM(grand_total) as total')->groupBy('branch_id')->pluck('total', 'branch_id');

        return Branch::query()->where('company_id', $user->company_id)
            ->when($scope['ids'] !== null, fn (Builder $query) => $query->whereIn('id', $scope['ids']))
            ->orderBy('name')->get(['id', 'name'])->map(function (Branch $outlet) use ($profitRows, $salesRows, $purchaseRows): array {
                $profitRow = $profitRows->get($outlet->name);
                $salesRow = $salesRows->get($outlet->name);

                return [
                    'outlet_id' => $outlet->id,
                    'outlet' => $outlet->name,
                    'net_sales' => (int) ($profitRow['net_sales'] ?? $salesRow['net_sales'] ?? 0),
                    'gross_profit' => $profitRow['gross_profit'] ?? null,
                    'margin_percent' => $profitRow['margin_percent'] ?? null,
                    'purchases' => $this->minor($purchaseRows->get($outlet->id, 0)),
                ];
            })->sortByDesc('net_sales')->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function salespersonRows(array $summary, ?array $profit): array
    {
        if ($profit !== null) {
            return collect($profit['salesperson_rows'])->take(8)->values()->all();
        }

        return collect($summary['reports']['cashiers']['rows'])->take(8)->map(fn (array $row): array => [
            'dimension' => $row['cashier'],
            'invoice_count' => $row['sales_count'],
            'net_sales' => $row['net_sales'],
            'gross_profit' => null,
            'margin_percent' => null,
        ])->all();
    }

    /** @return array<int, array{tone:string,title:string,message:string,route:string,report:?string}> */
    private function insights(array $summary, ?array $profit, array $gst, array $products, array $outlets, array $financial, ?array $profitAndLoss): array
    {
        $insights = [];
        if ($profit && (float) $profit['revenue_cost_coverage_percent'] < 90) {
            $insights[] = $this->insight('warning', 'Profit coverage needs attention', number_format((float) $profit['revenue_cost_coverage_percent'], 1).'% of revenue has verified sale-time cost. Review unavailable lines before relying on total margin.', 'reports.show', 'profitability');
        } elseif ($profit && (float) $profit['gross_margin_percent'] > 0) {
            $insights[] = $this->insight('positive', 'Margin is measurable', 'Verified-cost revenue is producing a '.number_format((float) $profit['gross_margin_percent'], 1).'% gross margin in the selected period.', 'reports.show', 'profitability');
        }
        if ($financial['overdue_receivables'] > 0) {
            $insights[] = $this->insight('warning', 'Collections require follow-up', $this->money($financial['overdue_receivables']).' is overdue across authorized customer invoices.', 'reports.show', 'outstanding');
        }
        if ($gst['incomplete_count'] > 0) {
            $insights[] = $this->insight('warning', 'GST records need review', $gst['incomplete_count'].' invoice(s) are missing place-of-supply information.', 'reports.show', 'gst');
        }
        if ($summary['metrics']['low_stock_count'] > 0) {
            $insights[] = $this->insight('neutral', 'Low stock could constrain sales', $summary['metrics']['low_stock_count'].' stock position(s) are at or below their minimum level.', 'reports.show', 'inventory');
        }
        if (count($outlets) > 1) {
            $best = $outlets[0];
            $last = $outlets[array_key_last($outlets)];
            $insights[] = $this->insight('positive', $best['outlet'].' leads sales', $this->money($best['net_sales']).' net sales. '.$last['outlet'].' currently has the lowest sales in this authorized comparison.', 'reports.show', 'outlets');
        }
        if ($products['slow'] !== []) {
            $insights[] = $this->insight('neutral', 'Slow-moving stock is visible', count($products['slow']).' low-velocity product(s) are ready for inventory review.', 'reports.show', 'profitability');
        }
        if ($profitAndLoss && ($profitAndLoss['top_operating_expense']['amount'] ?? 0) > 0) {
            $top = $profitAndLoss['top_operating_expense'];
            $insights[] = $this->insight('neutral', 'Largest operating expense: '.$top['category'], $this->money((int) $top['amount']).' is the largest operating expense in the selected period.', 'finance.profit-and-loss.index', null);
        }

        return array_slice($insights, 0, 4);
    }

    /** @return array{tone:string,title:string,message:string,route:string,report:?string} */
    private function insight(string $tone, string $title, string $message, string $route, ?string $report): array
    {
        return compact('tone', 'title', 'message', 'route', 'report');
    }

    /** @return array{granularity:string,points:array<int,array<string,mixed>>} */
    private function operationsTrend(User $user, array $scope, array $range): array
    {
        $saleDate = $this->timestampDateExpression('sold_at', $range);
        $sales = PosSale::query()->where('company_id', $user->company_id)->where('status', 'completed')
            ->when($scope['ids'] !== null, fn (Builder $query) => $query->whereIn('branch_id', $scope['ids']))
            ->whereBetween('sold_at', [$range['from']->utc(), $range['to']->utc()])
            ->selectRaw("{$saleDate} as activity_date, SUM(total_amount) as amount")
            ->groupByRaw($saleDate)->get()->map(fn ($row): array => ['date' => $row->activity_date, 'amount' => $this->minor($row->amount)]);
        $returnDate = $this->timestampDateExpression('completed_at', $range);
        $salesReturns = PosReturn::query()->where('company_id', $user->company_id)->where('status', PosReturn::STATUS_COMPLETED)
            ->when($scope['ids'] !== null, fn (Builder $query) => $query->whereIn('branch_id', $scope['ids']))
            ->whereBetween('completed_at', [$range['from']->utc(), $range['to']->utc()])
            ->selectRaw("{$returnDate} as activity_date, SUM(refund_total) as amount")
            ->groupByRaw($returnDate)->get()->map(fn ($row): array => ['date' => $row->activity_date, 'amount' => -$this->minor($row->amount)]);
        $purchases = PurchaseInvoice::query()->where('company_id', $user->company_id)->whereNotIn('status', ['cancelled', 'draft'])
            ->when($scope['ids'] !== null, fn (Builder $query) => $query->whereIn('branch_id', $scope['ids']))
            ->when($scope['warehouse_id'], fn (Builder $query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->whereDate('supplier_invoice_date', '>=', $range['from']->toDateString())->whereDate('supplier_invoice_date', '<=', $range['to']->toDateString())
            ->selectRaw('supplier_invoice_date as activity_date, SUM(grand_total) as amount')
            ->groupBy('supplier_invoice_date')->get()->map(fn ($row): array => ['date' => CarbonImmutable::parse($row->activity_date)->toDateString(), 'amount' => $this->minor($row->amount)]);
        $purchaseReturns = PurchaseReturn::query()->where('purchase_returns.company_id', $user->company_id)->where('purchase_returns.status', 'approved')
            ->when($scope['ids'] !== null, fn (Builder $query) => $query->whereIn('purchase_returns.branch_id', $scope['ids']))
            ->when($scope['warehouse_id'], fn (Builder $query, int $warehouseId) => $query->where('purchase_returns.warehouse_id', $warehouseId))
            ->whereDate('purchase_returns.return_date', '>=', $range['from']->toDateString())->whereDate('purchase_returns.return_date', '<=', $range['to']->toDateString())
            ->join('purchase_return_items', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->selectRaw('purchase_returns.return_date as activity_date, SUM(purchase_return_items.quantity * purchase_return_items.unit_cost) as amount')
            ->groupBy('purchase_returns.return_date')->get()
            ->map(fn ($row): array => ['date' => CarbonImmutable::parse($row->activity_date)->toDateString(), 'amount' => -$this->minor($row->amount)]);

        return $this->bucketOperations($sales->concat($salesReturns), $purchases->concat($purchaseReturns), $range);
    }

    /** @return array{granularity:string,points:array<int,array<string,mixed>>} */
    private function bucketOperations(Collection $sales, Collection $purchases, array $range): array
    {
        $granularity = $this->granularity($range);
        $key = function (string $date) use ($granularity): string {
            $value = CarbonImmutable::parse($date);

            return match ($granularity) {
                'weekly' => $value->startOfWeek()->toDateString(),
                'monthly' => $value->startOfMonth()->toDateString(),
                default => $value->toDateString(),
            };
        };
        $labels = fn (string $date): string => match ($granularity) {
            'weekly' => 'Wk '.CarbonImmutable::parse($date)->format('d M'),
            'monthly' => CarbonImmutable::parse($date)->format('M Y'),
            default => CarbonImmutable::parse($date)->format('d M'),
        };
        $salesBuckets = $sales->groupBy(fn (array $row): string => $key($row['date']))->map(fn (Collection $rows): int => $rows->sum('amount'));
        $purchaseBuckets = $purchases->groupBy(fn (array $row): string => $key($row['date']))->map(fn (Collection $rows): int => $rows->sum('amount'));
        $keys = $salesBuckets->keys()->merge($purchaseBuckets->keys())->unique()->sort()->values();

        return ['granularity' => $granularity, 'points' => $keys->map(fn (string $date): array => ['key' => $date, 'label' => $labels($date), 'sales' => (int) $salesBuckets->get($date, 0), 'purchases' => (int) $purchaseBuckets->get($date, 0), 'variance' => (int) $salesBuckets->get($date, 0) - (int) $purchaseBuckets->get($date, 0)])->all()];
    }

    private function granularity(array $range): string
    {
        $days = $range['from']->diffInDays($range['to']);

        return $days <= 31 ? 'daily' : ($days <= 120 ? 'weekly' : 'monthly');
    }

    private function timestampDateExpression(string $column, array $range): string
    {
        $offsetMinutes = $range['from']->utcOffset();
        if (DB::getDriverName() === 'sqlite') {
            return "DATE({$column}, '".sprintf('%+d minutes', $offsetMinutes)."')";
        }

        $sign = $offsetMinutes < 0 ? '-' : '+';
        $absolute = abs($offsetMinutes);
        $offset = sprintf('%s%02d:%02d', $sign, intdiv($absolute, 60), $absolute % 60);

        return "DATE(CONVERT_TZ({$column}, '+00:00', '{$offset}'))";
    }

    /** @return array<string, mixed> */
    private function emptyContext(): array
    {
        return ['product_id' => null, 'category_id' => null, 'brand_id' => null, 'customer_id' => null, 'supplier_id' => null, 'cashier_id' => null, 'payment_method' => null, 'status' => null, 'sale_channel' => null, 'source' => null, 'discounted' => null, 'tax_classification' => null, 'movement_type' => null, 'stock_status' => null];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function previousFilters(array $filters, array $range): array
    {
        $days = $range['from']->diffInDays($range['to']) + 1;
        $to = $range['from']->subDay();
        $from = $to->subDays($days - 1);

        return array_merge($filters, ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
    }

    private function minor(mixed $value): int
    {
        $value = (string) ($value ?? '0');
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $amount = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$amount : $amount;
    }

    private function money(int $amount): string
    {
        return 'INR '.number_format($amount / 100, 2);
    }
}
