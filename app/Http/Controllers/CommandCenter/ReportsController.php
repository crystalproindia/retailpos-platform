<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\Customers\Customer;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Purchases\Supplier;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use App\Services\Reports\RetailReportingService;
use App\Support\Reports\ReportValueFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request, RetailReportingService $reports, OutletAccessService $outlets): View
    {
        return view('command-center.reports.index', $this->payload($request, $reports, $outlets));
    }

    public function show(Request $request, RetailReportingService $reports, OutletAccessService $outlets, string $report): View
    {
        return view('command-center.reports.show', $this->payload($request, $reports, $outlets, $report) + ['report' => $reports->report($request->user(), $report, $this->filters($request))]);
    }

    public function export(Request $request, RetailReportingService $reports, ReportValueFormatter $formatter, string $report)
    {
        $data = $reports->report($request->user(), $report, $this->filters($request));
        $warehouseName = filled($data['scope']['warehouse_id'] ?? null)
            ? Warehouse::query()->where('company_id', $request->user()->company_id)->find($data['scope']['warehouse_id'])?->name
            : 'All warehouses';
        $detail = collect($data['detail'])->except('notice', 'source', 'method');
        $rows = $detail->has('rows')
            ? collect($detail->get('rows'))->map(fn (array $row) => collect($row)->map(fn ($value, string $key) => $formatter->csv($key, $value))->all())
            : $detail->map(fn ($value, $key) => ['Metric' => str($key)->replace('_', ' ')->headline()->toString(), 'Value' => $formatter->csv($key, $value)]);

        return Response::streamDownload(function () use ($rows, $data, $warehouseName): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Generated at', now($data['range']['timezone'])->toIso8601String()]);
            fputcsv($stream, ['Outlet scope', $data['scope']['label']]);
            fputcsv($stream, ['Warehouse scope', $warehouseName]);
            fputcsv($stream, ['Date range', $data['range']['from']->toDateString().' to '.$data['range']['to']->toDateString()]);
            fputcsv($stream, []);
            fputcsv($stream, array_keys($rows->first() ?? ['Metric' => null, 'Value' => null]));
            $rows->each(fn (array $row) => fputcsv($stream, array_map(fn ($value) => preg_match('/^[=+\\-@]/', (string) $value) ? "'".$value : $value, $row)));
            fclose($stream);
        }, "retailpos-{$report}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function payload(Request $request, RetailReportingService $reports, OutletAccessService $outlets, ?string $report = null): array
    {
        $availableOutlets = $outlets->accessibleOutlets($request->user());

        return [
            'overview' => $reports->overview($request->user(), $this->filters($request)),
            'outlets' => $availableOutlets,
            'warehouses' => Warehouse::query()
                ->where('company_id', $request->user()->company_id)
                ->when(! $outlets->hasCompanyWideAccess($request->user()), fn ($query) => $query->whereIn('branch_id', $availableOutlets->modelKeys()))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'branch_id', 'name']),
            'canViewAllOutlets' => $outlets->hasCompanyWideAccess($request->user()),
            'reportValueFormatter' => app(ReportValueFormatter::class),
            'advancedFilters' => $this->advancedFilters($request->user(), $report),
        ];
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'outlet_id' => ['nullable', 'string'],
            'warehouse_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'product_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'cashier_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'max:32'],
            'sale_channel' => ['nullable', 'string', 'max:32'],
            'discounted' => ['nullable', 'boolean'],
            'tax_classification' => ['nullable', 'string', 'max:24'],
            'movement_type' => ['nullable', 'string', 'max:64'],
            'stock_status' => ['nullable', Rule::in(['negative', 'out', 'low', 'available'])],
        ]);
    }

    /** @return array<int, array{name: string, label: string, options: array<int, array{value: string|int, label: string}>}> */
    private function advancedFilters(User $user, ?string $report): array
    {
        if ($report === null) {
            return [];
        }

        $options = [
            'products' => Product::query()->where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->limit(100)->get(['id', 'name']),
            'categories' => InventoryCategory::query()->where('company_id', $user->company_id)->orderBy('name')->limit(100)->get(['id', 'name']),
            'customers' => Customer::query()->where('company_id', $user->company_id)->where('is_active', true)->orderBy('display_name')->limit(100)->get(['id', 'display_name']),
            'suppliers' => Supplier::query()->where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->limit(100)->get(['id', 'name']),
            'cashiers' => User::query()->where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->limit(100)->get(['id', 'name']),
        ];

        $select = fn (string $name, string $label, array $items): array => ['name' => $name, 'label' => $label, 'options' => $items];
        $records = fn ($items, string $attribute): array => $items->map(fn ($item) => ['value' => $item->id, 'label' => $item->{$attribute}])->all();
        $status = fn (array $items): array => array_map(fn (string $item) => ['value' => $item, 'label' => str($item)->replace('_', ' ')->headline()->toString()], $items);
        $sales = [
            $select('product_id', 'Product', $records($options['products'], 'name')),
            $select('category_id', 'Category', $records($options['categories'], 'name')),
            $select('customer_id', 'Customer', $records($options['customers'], 'display_name')),
            $select('cashier_id', 'Cashier', $records($options['cashiers'], 'name')),
            $select('payment_method', 'Payment method', $status(['cash', 'card', 'upi', 'wallet', 'credit'])),
            $select('sale_channel', 'Sale channel', $status(['retail', 'wholesale', 'online'])),
            $select('discounted', 'Discount', [['value' => '1', 'label' => 'Discount applied'], ['value' => '0', 'label' => 'No discount']]),
            $select('status', 'Sale status', $status(['completed', 'voided'])),
        ];

        return match ($report) {
            'sales', 'outlets', 'cashiers', 'profitability' => $sales,
            'purchases' => [
                $select('supplier_id', 'Supplier', $records($options['suppliers'], 'name')),
                $select('product_id', 'Product', $records($options['products'], 'name')),
                $select('category_id', 'Category', $records($options['categories'], 'name')),
                $select('status', 'Purchase status', $status(['approved', 'verified', 'cancelled', 'draft'])),
            ],
            'inventory' => [
                $select('product_id', 'Product', $records($options['products'], 'name')),
                $select('category_id', 'Category', $records($options['categories'], 'name')),
                $select('stock_status', 'Stock status', $status(['negative', 'out', 'low', 'available'])),
            ],
            'movements' => [
                $select('product_id', 'Product', $records($options['products'], 'name')),
                $select('category_id', 'Category', $records($options['categories'], 'name')),
                $select('movement_type', 'Movement type', $status(['opening', 'purchase', 'purchase_return', 'sale', 'sale_void', 'adjustment', 'transfer_dispatch', 'transfer_receive'])),
            ],
            'gst', 'outstanding' => [
                $select('tax_classification', 'Tax classification', $status(['intra_state', 'inter_state', 'export', 'exempt'])),
                $select('status', 'Invoice status', $status(['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled', 'void'])),
            ],
            'payments' => [
                $select('payment_method', 'Payment method', $status(['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'wallet', 'credit'])),
                $select('status', 'Payment status', $status(['recorded', 'cleared', 'failed', 'reversed'])),
            ],
            'returns' => [
                $select('supplier_id', 'Supplier', $records($options['suppliers'], 'name')),
                $select('product_id', 'Product', $records($options['products'], 'name')),
                $select('category_id', 'Category', $records($options['categories'], 'name')),
                $select('status', 'Return status', $status(['draft', 'pending_approval', 'approved', 'rejected'])),
            ],
            'sales_returns' => [
                $select('customer_id', 'Customer', $records($options['customers'], 'display_name')),
                $select('status', 'Return status', $status(['completed'])),
            ],
            default => [],
        };
    }
}
