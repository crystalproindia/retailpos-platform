<?php

namespace App\Services\Ai;

use App\Models\Crm\CrmLead;
use App\Models\Customers\Customer;
use App\Models\NotificationConditionState;
use App\Models\User;
use App\Services\Inventory\InventoryIntelligenceService;
use App\Services\Outlets\OutletAccessService;
use App\Services\Reports\ExecutiveReportingService;
use App\Services\Reports\RetailReportingService;

class BusinessIntelligenceContextService
{
    public function __construct(
        private readonly RetailReportingService $reports,
        private readonly ExecutiveReportingService $executive,
        private readonly InventoryIntelligenceService $inventory,
        private readonly OutletAccessService $outlets,
    ) {}

    /** @param array{label:string,date_from:string,date_to:string} $period @return array<string, mixed> */
    public function forIntent(User $user, string $intent, array $period, ?int $outletId): array
    {
        $filters = ['date_from' => $period['date_from'], 'date_to' => $period['date_to']];
        if ($outletId) {
            $filters['outlet_id'] = (string) $outletId;
        }

        return match ($intent) {
            'inventory', 'reorder', 'slow_stock' => $this->inventory($user, $intent, $filters, $period),
            'crm_followup' => $this->crm($user, $period),
            'customer_insight' => $this->customers($user, $period),
            default => $this->reporting($user, $intent, $filters, $period),
        };
    }

    /** @return array<string, mixed> */
    private function reporting(User $user, string $intent, array $filters, array $period): array
    {
        $summary = $this->reports->summary($user, $filters);
        $executive = in_array($intent, ['business_summary', 'sales_comparison', 'outlet_comparison', 'product_performance'], true)
            ? $this->executive->dashboard($user, $filters, true)
            : null;
        $profit = $summary['reports']['profitability'] ?? null;
        $sales = $summary['reports']['sales'];
        $scope = $summary['scope']['label'];

        return match ($intent) {
            'profitability' => $this->profitabilityContext($profit, $sales, $period, $scope),
            'outlet_comparison' => $this->outletContext($executive, $period, $scope),
            'product_performance' => $this->productContext($executive, $period, $scope),
            'sales_comparison' => $this->salesComparisonContext($executive, $period, $scope),
            'sales_summary' => [
                'title' => 'Sales for '.$period['label'],
                'summary' => $sales['count'] ? 'Your completed sales are ready to review.' : 'There are no completed sales in this period yet.',
                'facts' => [
                    $this->fact('Net sales', $summary['metrics']['net_sales'], 'money'),
                    $this->fact('Completed sales', $sales['count'], 'number'),
                    $this->fact('Average order value', $summary['metrics']['average_order_value'], 'money'),
                    $this->fact('Sales returns', $summary['metrics']['sales_return_value'], 'money'),
                ],
                'recommendations' => $sales['count'] ? ['Compare this period with the previous one before changing prices or promotions.'] : ['Complete a sale in RetailPOS to begin seeing useful trends.'],
                'coverage' => $sales['source'],
                'sources' => [$this->source('Sales report', 'reports.show', ['report' => 'sales'])],
                'followups' => ['Compare with last month', 'Show top products', 'How much profit did we make?'],
                'fact_count' => 4,
                'scope' => $scope,
            ],
            default => $this->businessContext($summary, $executive, $period, $scope, $user, $filters),
        };
    }

    private function businessContext(array $summary, ?array $executive, array $period, string $scope, User $user, array $filters): array
    {
        $profit = $summary['reports']['profitability'] ?? null;
        $insights = collect($executive['insights'] ?? [])->take(3);
        $alerts = NotificationConditionState::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->when(isset($filters['outlet_id']) && is_numeric($filters['outlet_id']), fn ($query) => $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', (int) $filters['outlet_id'])))
            ->when(! $this->outlets->hasCompanyWideAccess($user), fn ($query) => $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhereIn('branch_id', $this->outlets->accessibleOutlets($user)->modelKeys())))
            ->orderByDesc('last_detected_at')->limit(3)->get();
        $alertRecommendations = collect($alerts->map(fn (NotificationConditionState $state): string => match ($state->condition_type) {
            'inventory_stock' => 'Review the active inventory alert before replenishing or transferring stock.',
            'receivable' => 'Review the active receivable reminder and its current remaining balance.',
            'quotation', 'proforma' => 'Review the expiring sales document while it is still actionable.',
            'purchasing' => 'Review the purchase attention item before creating or changing an order.',
            default => 'Review the active Notification Center item.',
        })->all());

        return [
            'title' => 'Your business for '.$period['label'],
            'summary' => $summary['metrics']['invoice_count'] ? 'Here is a clear snapshot from your authorized RetailPOS data.' : 'There is not enough completed sales activity for a meaningful trend yet.',
            'facts' => [
                $this->fact('Net sales', $summary['metrics']['net_sales'], 'money'),
                $this->fact('Gross profit', $profit['gross_profit'] ?? null, 'money'),
                $this->fact('Outstanding receivables', $summary['metrics']['outstanding_receivables'], 'money'),
                $this->fact('Low-stock products', $summary['metrics']['low_stock_count'], 'number'),
            ],
            'recommendations' => $alertRecommendations->concat($insights->pluck('message'))->filter()->unique()->take(3)->values()->all() ?: ['Keep recording sales and stock movements so RetailPOS can surface stronger comparisons.'],
            'coverage' => $profit === null ? 'Sales and operations are included. Profitability is hidden because this account does not have profitability access.' : ((int) ($profit['unavailable_cost_item_count'] ?? 0) > 0 ? 'Some revenue has unavailable cost, so total profit coverage is incomplete.' : 'Profit figures use immutable sale-time cost snapshots.'),
            'sources' => [$this->source('Owner Command Center', 'reports.index'), $this->source('Sales report', 'reports.show', ['report' => 'sales'])],
            'followups' => ['How are sales today?', 'What should I reorder?', 'Which products are selling well?'],
            'fact_count' => 4 + $insights->count() + $alerts->count(),
            'scope' => $scope,
        ];
    }

    private function profitabilityContext(?array $profit, array $sales, array $period, string $scope): array
    {
        if ($profit === null) {
            return $this->unavailable('Profitability is not available for this account.', $period, $scope);
        }

        $known = (int) ($profit['captured_item_count'] ?? 0) + (int) ($profit['reconstructed_item_count'] ?? 0);

        return [
            'title' => 'Profitability for '.$period['label'],
            'summary' => $known ? 'Gross profit is based on the cost evidence RetailPOS has captured.' : 'RetailPOS has sales for this period, but not enough reliable cost evidence to state gross profit.',
            'facts' => [
                $this->fact('Net sales', $profit['net_sales'] ?? 0, 'money'),
                $this->fact('Gross profit', $known ? ($profit['gross_profit'] ?? 0) : null, 'money'),
                $this->fact('Gross margin', $known ? ($profit['gross_margin_percent'] ?? null) : null, 'percent'),
                $this->fact('Discount impact', $profit['discount_impact'] ?? $profit['discounts'] ?? 0, 'money'),
            ],
            'recommendations' => $known ? ['Review low-margin products and discount impact before changing prices.'] : ['Add product cost data to improve profitability coverage.'],
            'coverage' => (int) ($profit['unavailable_cost_item_count'] ?? 0) > 0 ? 'Some sales have unavailable cost. Revenue is complete, but aggregate profit is not fully covered.' : 'Known-cost sales use immutable captured or reconstructed cost snapshots.',
            'sources' => [$this->source('Profitability report', 'reports.show', ['report' => 'profitability'])],
            'followups' => ['Which products generate the most profit?', 'Are discounts hurting profit?', 'Compare with last month'],
            'fact_count' => 4,
            'scope' => $scope,
        ];
    }

    private function salesComparisonContext(?array $executive, array $period, string $scope): array
    {
        $sales = collect($executive['kpis'] ?? [])->firstWhere('key', 'net_sales');
        $change = $sales['change'] ?? null;

        return [
            'title' => 'Sales comparison',
            'summary' => $change === null ? 'There is not enough comparable activity to state a reliable change.' : ('Net sales are '.abs((float) $change).'% '.((float) $change >= 0 ? 'higher' : 'lower').' than the previous matching period.'),
            'facts' => [$this->fact('Net sales', $sales['value'] ?? 0, 'money'), $this->fact('Previous-period change', $change, 'percent')],
            'recommendations' => ['Review product and outlet detail before drawing a conclusion about why sales changed.'],
            'coverage' => 'The comparison uses equal-length periods and completed authorized sales.',
            'sources' => [$this->source('Owner Command Center', 'reports.index')],
            'followups' => ['Show top products', 'Compare outlets', 'Why did profit change?'],
            'fact_count' => 2,
            'scope' => $scope,
        ];
    }

    private function outletContext(?array $executive, array $period, string $scope): array
    {
        $rows = collect($executive['outlets'] ?? [])->take(5);

        return [
            'title' => 'Outlet comparison for '.$period['label'],
            'summary' => $rows->isEmpty() ? 'There is not enough authorized outlet activity to compare yet.' : $rows->first()['outlet'].' has the highest visible net sales in this period.',
            'facts' => $rows->map(fn (array $row) => $this->fact($row['outlet'], $row['net_sales'], 'money'))->all(),
            'recommendations' => ['Compare product mix and discounting before deciding why one outlet is ahead.'],
            'coverage' => 'Only outlets authorized for this account are included.',
            'sources' => [$this->source('Outlet performance', 'reports.show', ['report' => 'outlets'])],
            'followups' => ['Which products sold best?', 'Show gross profit', 'What needs my attention?'],
            'fact_count' => $rows->count(),
            'scope' => $scope,
        ];
    }

    private function productContext(?array $executive, array $period, string $scope): array
    {
        $rows = collect($executive['products']['top'] ?? [])->take(5);

        return [
            'title' => 'Top products for '.$period['label'],
            'summary' => $rows->isEmpty() ? 'There is no authorized product profitability data for this period yet.' : $rows->first()['dimension'].' leads the visible product sales.',
            'facts' => $rows->map(fn (array $row) => $this->fact($row['dimension'], $row['net_sales'], 'money'))->all(),
            'recommendations' => ['Use sales, margin, and stock availability together before planning replenishment.'],
            'coverage' => 'Product rankings use authorized profitability records and do not infer missing product links.',
            'sources' => [$this->source('Profitability report', 'reports.show', ['report' => 'profitability'])],
            'followups' => ['What should I reorder?', 'Show slow-moving stock', 'Are discounts hurting profit?'],
            'fact_count' => $rows->count(),
            'scope' => $scope,
        ];
    }

    private function inventory(User $user, string $intent, array $filters, array $period): array
    {
        $data = $this->inventory->dashboard($user, ['outlet_id' => $filters['outlet_id'] ?? null, 'velocity_period' => 30]);
        $rows = collect(match ($intent) {
            'reorder' => $data['reorder'],
            'slow_stock' => $data['slow']->concat($data['dead'])->unique(fn ($row) => $row['warehouse_id'].'-'.$row['product_id']),
            default => $data['rows'],
        })->take(8);
        $title = match ($intent) {
            'reorder' => 'Reorder review', 'slow_stock' => 'Slow-moving stock', default => 'Inventory snapshot'
        };
        $summary = match ($intent) {
            'reorder' => $rows->isEmpty() ? 'No deterministic reorder recommendation needs attention right now.' : $rows->count().' product location(s) may need replenishment.',
            'slow_stock' => $rows->isEmpty() ? 'No slow or dead stock is visible in your authorized locations.' : $rows->count().' high-priority slow or dead stock item(s) are worth checking.',
            default => 'Here is the current authorized inventory position.',
        };

        return [
            'title' => $title,
            'summary' => $summary,
            'facts' => $intent === 'inventory' ? [
                $this->fact('Stock value', $data['cards']['stock_value_minor'], 'money'),
                $this->fact('Units on hand', $data['cards']['units_on_hand'], 'number'),
                $this->fact('Low-stock products', $data['cards']['low_stock_count'], 'number'),
                $this->fact('Out-of-stock products', $data['cards']['out_of_stock_count'], 'number'),
            ] : $rows->map(fn (array $row) => $this->fact($row['product'].' · '.$row['warehouse'], $intent === 'reorder' ? $row['suggested_reorder_quantity'] : $row['stock_value_minor'], $intent === 'reorder' ? 'quantity' : 'money'))->all(),
            'recommendations' => $rows->take(3)->pluck('reason')->filter()->values()->all() ?: ['Keep stock movements current for stronger recommendations.'],
            'coverage' => $data['methodology'].' Recommendations are advisory only.',
            'sources' => [$this->source('Inventory Intelligence', 'inventory.intelligence.index')],
            'followups' => ['What is running low?', 'Show dead stock', 'Which products are moving fastest?'],
            'fact_count' => $rows->count() + 4,
            'scope' => $data['scope_count'].' authorized warehouse(s)',
        ];
    }

    private function crm(User $user, array $period): array
    {
        $query = CrmLead::query()->where('company_id', $user->company_id)->whereNull('won_at')->whereNull('lost_at');
        if (! $this->outlets->hasCompanyWideAccess($user)) {
            $query->whereIn('branch_id', $this->outlets->accessibleOutlets($user)->modelKeys());
        }
        $leads = $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay())->with('assignedUser')->orderBy('next_follow_up_at')->limit(8)->get();

        return [
            'title' => 'CRM follow-ups',
            'summary' => $leads->isEmpty() ? 'No due lead follow-ups are visible for your authorized scope.' : $leads->count().' lead follow-up(s) are due or overdue.',
            'facts' => $leads->map(fn (CrmLead $lead) => $this->fact($lead->business_name ?: $lead->contact_name ?: $lead->title, $lead->next_follow_up_at?->format('d M, H:i'), 'text'))->all(),
            'recommendations' => $leads->take(3)->map(fn (CrmLead $lead) => 'Review '.($lead->business_name ?: $lead->contact_name ?: $lead->title).' before contacting them.')->all(),
            'coverage' => 'Only open leads with a recorded due follow-up in your authorized outlets are included.',
            'sources' => [$this->source('Leads', 'crm.leads.index')],
            'followups' => ['Which leads are overdue?', 'Show promising leads', 'How are sales today?'],
            'fact_count' => $leads->count(),
            'scope' => 'Authorized CRM outlets',
        ];
    }

    private function customers(User $user, array $period): array
    {
        $query = Customer::query()->where('company_id', $user->company_id)->where('is_active', true);
        if (! $this->outlets->hasCompanyWideAccess($user)) {
            $query->whereIn('branch_id', $this->outlets->accessibleOutlets($user)->modelKeys());
        }
        $customers = $query->orderByDesc('total_purchase_amount')->limit(5)->get();

        return [
            'title' => 'Customer insight',
            'summary' => $customers->isEmpty() ? 'There is not enough customer purchase history to rank customers yet.' : $customers->first()->display_name.' has the highest recorded lifetime purchase value.',
            'facts' => $customers->map(fn (Customer $customer) => $this->fact($customer->display_name, (int) round(((float) $customer->total_purchase_amount) * 100), 'money'))->all(),
            'recommendations' => ['Review recent purchase dates before planning customer outreach.'],
            'coverage' => 'This uses recorded customer purchase summaries, not inferred customer value.',
            'sources' => [$this->source('Customers', 'customers.index')],
            'followups' => ['Which customers need follow-up?', 'How are sales this month?', 'Show top products'],
            'fact_count' => $customers->count(),
            'scope' => 'Authorized customer records',
        ];
    }

    private function unavailable(string $message, array $period, string $scope): array
    {
        return ['title' => 'Not enough data yet', 'summary' => $message, 'facts' => [], 'recommendations' => ['Use the linked RetailPOS report to review the available source data.'], 'coverage' => 'I do not have enough authorized RetailPOS data to answer that reliably yet.', 'sources' => [$this->source('Owner Command Center', 'reports.index')], 'followups' => ['How are sales today?', 'What needs my attention?'], 'fact_count' => 0, 'scope' => $scope];
    }

    private function fact(string $label, mixed $value, string $format): array
    {
        return ['label' => $label, 'value' => $value, 'format' => $format];
    }

    private function source(string $label, string $route, array $parameters = []): array
    {
        return ['label' => $label, 'url' => route($route, $parameters)];
    }
}
