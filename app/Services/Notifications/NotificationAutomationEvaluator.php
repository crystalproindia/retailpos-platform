<?php

namespace App\Services\Notifications;

use App\Enums\Crm\ProformaStatus;
use App\Enums\Crm\QuotationStatus;
use App\Enums\Purchases\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\Purchases\PurchaseOrder;
use App\Models\User;
use App\Services\Finance\PayableService;
use App\Services\Finance\FinancialPeriodResolver;
use App\Services\Finance\ProfitAndLossInsightService;
use App\Services\Finance\ReceivableService;
use App\Services\Inventory\InventoryIntelligenceService;
use App\Services\Reports\ExecutiveReportingService;
use Carbon\CarbonImmutable;

class NotificationAutomationEvaluator
{
    private const SOURCE_LIMIT = 500;

    public function __construct(
        private readonly NotificationAutomationSettingsService $settings,
        private readonly AutomationNotificationService $notifications,
        private readonly InventoryIntelligenceService $inventory,
        private readonly ExecutiveReportingService $executive,
        private readonly ReceivableService $receivables,
        private readonly PayableService $payables,
        private readonly FinancialPeriodResolver $periods,
        private readonly ProfitAndLossInsightService $profitAndLoss,
    ) {}

    /** @return array<string, array{created:int,recovered:int}> */
    public function evaluate(Company $company): array
    {
        $setting = $this->settings->forCompany($company);
        $administrator = User::query()
            ->where('company_id', $company->id)
            ->where('role', UserRole::Administrator->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $administrator) {
            return [];
        }

        $results = [];
        $results['inventory_stock'] = $this->notifications->sync($company, $setting, 'inventory_stock', $this->inventoryConditions($administrator, $setting));
        $results['receivable'] = $this->notifications->sync($company, $setting, 'receivable', $this->receivableConditions($administrator, $setting));
        $results['quotation'] = $this->notifications->sync($company, $setting, 'quotation', $this->quotationConditions($company, $setting));
        $results['proforma'] = $this->notifications->sync($company, $setting, 'proforma', $this->proformaConditions($company, $setting));
        $results['purchasing'] = $this->notifications->sync($company, $setting, 'purchasing', $this->purchaseConditions($administrator, $setting));
        $results['owner_summary'] = $this->notifications->sync($company, $setting, 'owner_summary', $this->ownerSummaryConditions($administrator, $setting));
        $results['monthly_expense_summary'] = $this->notifications->sync($company, $setting, 'monthly_expense_summary', $this->monthlySummaryConditions($administrator, $setting, false));
        $results['monthly_profit_and_loss_summary'] = $this->notifications->sync($company, $setting, 'monthly_profit_and_loss_summary', $this->monthlySummaryConditions($administrator, $setting, true));

        return $results;
    }

    private function inventoryConditions(User $administrator, $setting): array
    {
        if (! $setting->low_stock_enabled && ! $setting->out_of_stock_enabled && ! $setting->reorder_enabled) {
            return [];
        }

        $dashboard = $this->inventory->dashboard($administrator, ['velocity_period' => 30]);
        $conditions = [];
        foreach (collect($dashboard['rows'])->take(self::SOURCE_LIMIT) as $row) {
            $subjectId = (int) $row['product_id'];
            $branchId = $row['outlet_id'] ? (int) $row['outlet_id'] : null;
            $base = [
                'subject_type' => 'inventory_product_warehouse',
                'subject_id' => $this->compoundId($subjectId, (int) $row['warehouse_id']),
                'branch_id' => $branchId,
                'category' => 'inventory',
                'icon' => 'inventory',
                'action_url' => route('inventory.intelligence.index', ['warehouse_id' => $row['warehouse_id'], 'product_id' => $subjectId]),
                'action_label' => 'Review Inventory',
                'context' => ['product_id' => $subjectId, 'warehouse_id' => $row['warehouse_id'], 'available' => $row['available']],
            ];
            if ($setting->out_of_stock_enabled && $row['is_out']) {
                $conditions[] = $base + [
                    'stage' => 'out_of_stock', 'severity' => 'important', 'title' => 'Stock is out',
                    'message' => $row['product'].' is out of stock at '.$row['warehouse'].'.',
                    'email_details' => ['Product' => $row['product'], 'Location' => $row['warehouse'], 'Available' => '0'],
                ];
            } elseif ($setting->low_stock_enabled && $row['is_low']) {
                $conditions[] = $base + [
                    'stage' => 'low_stock', 'severity' => 'attention', 'title' => 'Stock is running low',
                    'message' => $row['product'].' has '.$this->quantity($row['available']).' remaining at '.$row['warehouse'].'.',
                    'email_details' => ['Product' => $row['product'], 'Location' => $row['warehouse'], 'Available' => $this->quantity($row['available'])],
                ];
            }
        }

        return $conditions;
    }

    private function receivableConditions(User $administrator, $setting): array
    {
        if (! $setting->payment_reminders_enabled) {
            return [];
        }

        $today = CarbonImmutable::now($this->timezone($administrator->company, $setting))->startOfDay();
        $before = collect($setting->payment_before_due_days ?: [3])->map(fn ($day) => (int) $day)->filter(fn ($day) => $day > 0)->unique()->sortDesc();
        $overdue = collect($setting->payment_overdue_days ?: [1, 7, 30])->map(fn ($day) => (int) $day)->filter(fn ($day) => $day > 0)->unique()->sort();

        return $this->receivables->openQuery($administrator)
            ->whereNotNull('due_date')
            ->where(fn ($query) => $query->whereNull('do_not_remind_before')->orWhereDate('do_not_remind_before', '<=', $today->toDateString()))
            ->orderBy('due_date')->limit(self::SOURCE_LIMIT)->get()
            ->map(function (CrmInvoice $invoice) use ($today, $before, $overdue): ?array {
                $dueDate = CarbonImmutable::parse($invoice->due_date->toDateString(), $today->timezone)->startOfDay();
                $days = (int) $today->diffInDays($dueDate, false);
                $stage = null;
                $title = null;
                if ($days === 0) {
                    $stage = 'due_today';
                    $title = 'Payment is due today';
                } elseif ($days > 0 && $before->contains($days)) {
                    $stage = 'due_in_'.$days;
                    $title = 'Payment is due soon';
                } elseif ($days < 0) {
                    $late = abs($days);
                    $threshold = $overdue->filter(fn (int $day) => $day <= $late)->max();
                    if ($threshold) {
                        $stage = 'overdue_'.$threshold;
                        $title = 'Payment is overdue';
                    }
                }
                if (! $stage) {
                    return null;
                }
                $customer = $invoice->billing_company ?: $invoice->billing_name ?: 'Customer';
                $timing = $days < 0 ? ' overdue by '.abs($days).' day'.(abs($days) === 1 ? '' : 's') : ($days === 0 ? ' due today' : ' due in '.$days.' days');

                return [
                    'subject_type' => $invoice->getMorphClass(), 'subject_id' => $invoice->id, 'branch_id' => $invoice->branch_id,
                    'stage' => $stage, 'severity' => $days < 0 ? 'important' : 'attention', 'category' => 'receivable', 'icon' => 'finance',
                    'title' => $title, 'message' => $customer.' has '.$this->money($invoice->balance_due).$timing.'.',
                    'action_url' => route('sales.invoices.show', $invoice), 'action_label' => 'Open Invoice',
                    'customer_email' => $invoice->billing_email, 'customer_name' => $invoice->billing_name ?: $invoice->billing_company,
                    'context' => ['invoice_number' => $invoice->invoice_number, 'balance_due' => (string) $invoice->balance_due, 'days' => $days],
                    'email_details' => ['Invoice' => $invoice->invoice_number, 'Outstanding' => $this->money($invoice->balance_due), 'Due date' => $invoice->due_date->format('d M Y')],
                ];
            })->filter()->values()->all();
    }

    private function quotationConditions(Company $company, $setting): array
    {
        if (! $setting->quotation_expiry_enabled) {
            return [];
        }

        $today = CarbonImmutable::now($this->timezone($company, $setting))->startOfDay();

        return CrmQuotation::query()->where('company_id', $company->id)
            ->whereIn('status', [QuotationStatus::Sent->value, QuotationStatus::Viewed->value])
            ->whereNotNull('valid_until')->orderBy('valid_until')->limit(self::SOURCE_LIMIT)->get()
            ->map(fn (CrmQuotation $quotation) => $this->documentExpiryCondition(
                document: $quotation,
                date: $quotation->valid_until,
                today: $today,
                noticeDays: (int) $setting->document_expiry_notice_days,
                kind: 'Quotation',
                number: $quotation->quotation_number,
                customer: $quotation->customer_company ?: $quotation->customer_name,
                amount: $quotation->grand_total,
                routeName: 'crm.quotations.show',
            ))->filter()->values()->all();
    }

    private function proformaConditions(Company $company, $setting): array
    {
        if (! $setting->proforma_expiry_enabled) {
            return [];
        }

        $today = CarbonImmutable::now($this->timezone($company, $setting))->startOfDay();

        return CrmProformaInvoice::query()->where('company_id', $company->id)
            ->whereIn('status', [ProformaStatus::Sent->value, ProformaStatus::PartiallyPaid->value, ProformaStatus::Overdue->value])
            ->whereNotNull('due_date')->orderBy('due_date')->limit(self::SOURCE_LIMIT)->get()
            ->map(fn (CrmProformaInvoice $proforma) => $this->documentExpiryCondition(
                document: $proforma,
                date: $proforma->due_date,
                today: $today,
                noticeDays: (int) $setting->document_expiry_notice_days,
                kind: 'Proforma',
                number: $proforma->proforma_number,
                customer: $proforma->customer_company ?: $proforma->customer_name,
                amount: $proforma->balance_amount,
                routeName: 'crm.proformas.show',
            ))->filter()->values()->all();
    }

    private function purchaseConditions(User $administrator, $setting): array
    {
        if (! $setting->purchase_reminders_enabled) {
            return [];
        }

        $conditions = [];
        $dashboard = $this->inventory->dashboard($administrator, ['velocity_period' => 30]);
        foreach (collect($dashboard['reorder'])->filter(fn (array $row): bool => $setting->reorder_enabled && ! $row['is_low'] && ! $row['is_out'])->take(200) as $row) {
            $conditions[] = [
                'subject_type' => 'inventory_reorder', 'subject_id' => $this->compoundId((int) $row['product_id'], (int) $row['warehouse_id']),
                'branch_id' => $row['outlet_id'] ? (int) $row['outlet_id'] : null, 'stage' => 'recommended', 'severity' => 'attention',
                'category' => 'purchasing', 'icon' => 'purchases', 'title' => 'A purchase recommendation is ready',
                'message' => $row['product'].' may need '.$this->quantity($row['suggested_reorder_quantity']).' units for '.$row['warehouse'].'.',
                'action_url' => route('inventory.intelligence.index', ['warehouse_id' => $row['warehouse_id'], 'product_id' => $row['product_id']]),
                'action_label' => 'Review Purchase Recommendation', 'context' => ['product_id' => $row['product_id'], 'warehouse_id' => $row['warehouse_id']],
            ];
        }

        $today = now($setting->timezone ?: config('app.timezone'))->toDateString();
        PurchaseOrder::query()->where('company_id', $administrator->company_id)
            ->whereIn('status', [PurchaseOrderStatus::Approved->value, PurchaseOrderStatus::Sent->value, PurchaseOrderStatus::SupplierConfirmed->value, PurchaseOrderStatus::PartiallyReceived->value])
            ->whereNotNull('expected_delivery_date')->whereDate('expected_delivery_date', '<', $today)
            ->orderBy('expected_delivery_date')->limit(200)->get()->each(function (PurchaseOrder $order) use (&$conditions, $today): void {
                $late = CarbonImmutable::parse($order->expected_delivery_date)->diffInDays(CarbonImmutable::parse($today));
                $conditions[] = [
                    'subject_type' => $order->getMorphClass(), 'subject_id' => $order->id, 'branch_id' => $order->branch_id,
                    'stage' => 'receipt_overdue', 'severity' => 'important', 'category' => 'purchasing', 'icon' => 'purchases',
                    'title' => 'A purchase delivery needs attention',
                    'message' => $order->po_number.' is '.$late.' day'.($late === 1 ? '' : 's').' past its expected delivery date.',
                    'action_url' => route('purchases.orders.show', $order), 'action_label' => 'Open Purchase Order',
                    'context' => ['po_number' => $order->po_number, 'days_overdue' => $late],
                ];
            });

        $this->payables->openQuery($administrator)->with('supplier')->whereNotNull('due_date')->whereDate('due_date', '<=', $today)
            ->orderBy('due_date')->limit(100)->get()->each(function ($invoice) use (&$conditions, $today): void {
                $late = CarbonImmutable::parse($invoice->due_date)->diffInDays(CarbonImmutable::parse($today));
                $conditions[] = [
                    'subject_type' => $invoice->getMorphClass(), 'subject_id' => $invoice->id, 'branch_id' => $invoice->branch_id,
                    'stage' => $late > 0 ? 'payable_overdue' : 'payable_due_today', 'severity' => $late > 0 ? 'important' : 'attention',
                    'category' => 'purchasing', 'icon' => 'finance', 'title' => $late > 0 ? 'A supplier payment is overdue' : 'A supplier payment is due today',
                    'message' => ($invoice->supplier?->name ?: 'Supplier').' is owed '.$this->money($invoice->outstanding_total).($late > 0 ? ' and the bill is '.$late.' day'.($late === 1 ? '' : 's').' overdue.' : ' today.'),
                    'action_url' => route('purchases.invoices.show', $invoice), 'action_label' => 'Open Supplier Bill',
                    'context' => ['invoice_number' => $invoice->invoice_number, 'balance_due' => (string) $invoice->outstanding_total, 'days_overdue' => $late],
                ];
            });

        return $conditions;
    }

    private function ownerSummaryConditions(User $administrator, $setting): array
    {
        $timezone = $setting->timezone ?: $administrator->company?->timezone ?: config('app.timezone');
        $now = CarbonImmutable::now($timezone);
        $scheduled = CarbonImmutable::parse($now->toDateString().' '.$setting->summary_time, $timezone);
        $daily = $setting->daily_summary_enabled && $now->format('H') === $scheduled->format('H');
        $weekly = $setting->weekly_summary_enabled && $now->isMonday() && $now->format('H') === $scheduled->format('H');
        if (! $daily && ! $weekly) {
            return [];
        }

        $from = $weekly ? $now->startOfWeek() : $now->startOfDay();
        $dashboard = $this->executive->dashboard($administrator, [
            'date_from' => $from->toDateString(),
            'date_to' => $now->toDateString(),
        ], false);
        $kpis = collect($dashboard['kpis'])->keyBy('key');
        $sales = (int) ($kpis->get('net_sales')['value'] ?? 0);
        $profit = $kpis->get('gross_profit')['value'] ?? null;
        $receivables = (int) ($kpis->get('receivables')['value'] ?? 0);
        $period = $weekly ? 'weekly' : 'daily';

        return [[
            'subject_type' => $administrator->company->getMorphClass(), 'subject_id' => $administrator->company_id,
            'branch_id' => null, 'stage' => $period.'_'.$now->format('Ymd'), 'severity' => 'info', 'category' => 'owner_summary',
            'icon' => 'analytics', 'title' => 'Your RetailPOS '.$period.' summary',
            'message' => 'Net sales are '.$this->minorMoney($sales).', with '.$this->minorMoney($receivables).' in receivables requiring visibility.',
            'action_url' => route('reports.index'), 'action_label' => 'Open Owner Command Center', 'administrators_only' => true,
            'context' => ['net_sales_minor' => $sales, 'gross_profit_minor' => $profit, 'receivables_minor' => $receivables],
            'email_details' => [
                'Net sales' => $this->minorMoney($sales),
                'Gross profit' => $profit === null ? 'Cost coverage unavailable' : $this->minorMoney((int) $profit),
                'Receivables' => $this->minorMoney($receivables),
            ],
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function monthlySummaryConditions(User $administrator, $setting, bool $includeProfitAndLoss): array
    {
        $enabled = $includeProfitAndLoss
            ? $setting->monthly_profit_and_loss_summary_enabled
            : $setting->monthly_expense_summary_enabled;
        $timezone = $this->timezone($administrator->company, $setting);
        $now = CarbonImmutable::now($timezone);
        $scheduled = CarbonImmutable::parse($now->toDateString().' '.$setting->summary_time, $timezone);

        if (! $enabled || $now->day !== 1 || $now->format('H') !== $scheduled->format('H')) {
            return [];
        }

        $range = $this->periods->resolve($administrator->company, ['period' => 'last_month'], $now);
        $previousRange = $this->periods->resolve($administrator->company, [
            'period' => 'custom',
            'date_from' => $range['from']->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'date_to' => $range['from']->subMonthNoOverflow()->endOfMonth()->toDateString(),
        ], $now);
        $pnl = $this->profitAndLoss->summary($administrator, [
            'ids' => null,
            'warehouse_id' => null,
            'label' => 'Company / Consolidated',
        ], $range, $previousRange);
        $report = $pnl['report'];
        $top = $pnl['top_operating_expense'];
        $periodKey = $range['from']->format('Ym');
        $category = $includeProfitAndLoss ? 'profit_and_loss_summary' : 'expense_summary';
        $title = $includeProfitAndLoss ? 'Your monthly profit & loss summary' : 'Your monthly expense summary';
        $message = $includeProfitAndLoss
            ? 'Net sales were '.$this->minorMoney($report['net_sales']).', operating expenses were '.$this->minorMoney($report['operating_expenses']).', and net profit was '.$this->minorMoney($report['net_profit']).'.'
            : 'Operating expenses were '.$this->minorMoney($report['operating_expenses']).' against '.$this->minorMoney($report['net_sales']).' in net sales.';

        return [[
            'subject_type' => $administrator->company->getMorphClass(),
            'subject_id' => $administrator->company_id,
            'branch_id' => null,
            'stage' => 'month_'.$periodKey,
            'severity' => 'info',
            'category' => $category,
            'icon' => 'finance',
            'title' => $title,
            'message' => $message,
            'action_url' => route('finance.profit-and-loss.index', ['period' => 'last_month', 'outlet_id' => 'all']),
            'action_label' => 'Open Profit & Loss',
            'administrators_only' => true,
            'context' => [
                'period_from' => $range['from']->toDateString(),
                'period_to' => $range['to']->toDateString(),
                'net_sales_minor' => $report['net_sales'],
                'operating_expenses_minor' => $report['operating_expenses'],
                'net_profit_minor' => $report['net_profit'],
                'top_operating_expense' => $top['category'] ?? null,
            ],
            'email_details' => array_filter([
                'Net sales' => $this->minorMoney($report['net_sales']),
                'Operating expenses' => $this->minorMoney($report['operating_expenses']),
                'Net profit' => $includeProfitAndLoss ? $this->minorMoney($report['net_profit']) : null,
                'Top operating expense' => $top ? $top['category'].' · '.$this->minorMoney($top['amount']) : null,
            ]),
        ]];
    }

    private function documentExpiryCondition(object $document, \DateTimeInterface $date, CarbonImmutable $today, int $noticeDays, string $kind, string $number, ?string $customer, mixed $amount, string $routeName): ?array
    {
        $expiryDate = CarbonImmutable::parse($date->toDateString(), $today->timezone)->startOfDay();
        $days = (int) $today->diffInDays($expiryDate, false);
        if ($days > $noticeDays) {
            return null;
        }
        $stage = $days < 0 ? 'expired' : ($days === 0 ? 'expires_today' : 'expires_in_'.$days);
        $timing = $days < 0 ? 'expired '.abs($days).' day'.(abs($days) === 1 ? '' : 's').' ago' : ($days === 0 ? 'expires today' : 'expires in '.$days.' day'.($days === 1 ? '' : 's'));

        return [
            'subject_type' => $document->getMorphClass(), 'subject_id' => $document->id, 'branch_id' => $document->branch_id ?? null,
            'stage' => $stage, 'severity' => $days < 0 ? 'important' : 'attention', 'category' => strtolower($kind), 'icon' => 'orders',
            'title' => $kind.' '.($days < 0 ? 'has expired' : ($days === 0 ? 'expires today' : 'expires soon')),
            'message' => $number.' for '.($customer ?: 'Customer').' '.$timing.' and is worth '.$this->money($amount).'.',
            'action_url' => route($routeName, $document), 'action_label' => 'Open '.$kind,
            'context' => ['document_number' => $number, 'days' => $days, 'amount' => (string) $amount],
        ];
    }

    private function timezone(Company $company, $setting): string
    {
        return $setting->timezone ?: $company->timezone ?: config('app.timezone');
    }

    private function compoundId(int $first, int $second): int
    {
        return (int) hexdec(substr(hash('sha256', $first.'|'.$second), 0, 15));
    }

    private function quantity(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    private function money(mixed $major): string
    {
        return '₹'.number_format((float) $major, 2);
    }

    private function minorMoney(int $minor): string
    {
        return '₹'.number_format($minor / 100, 2);
    }
}
