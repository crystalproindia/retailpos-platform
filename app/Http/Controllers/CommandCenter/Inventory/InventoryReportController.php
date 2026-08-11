<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Inventory\InventoryStockViewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class InventoryReportController extends Controller
{
    private const REPORTS = ['stock-by-location', 'stock-movement', 'stock-valuation', 'transfers', 'in-transit', 'discrepancies', 'adjustments', 'count-variance', 'batches', 'expiry', 'serials', 'ageing', 'slow-dead', 'reorder', 'low-stock'];

    public function show(Request $request, InventoryStockViewService $reports, InventoryLocationAccessService $locations, string $report = 'stock-by-location'): View
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);
        $filters = $this->filters($request);

        return view('command-center.inventory.reports.show', ['report' => $report, 'rows' => $reports->reportRows($request->user(), $report, $filters), 'warehouses' => $locations->accessibleWarehouses($request->user(), false), 'products' => Product::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->limit(200)->get(), 'reportTypes' => self::REPORTS]);
    }

    public function export(Request $request, InventoryStockViewService $reports, string $report)
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);
        $rows = $reports->reportRows($request->user(), $report, $this->filters($request));

        return Response::streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, array_keys($rows->first() ?? ['message' => null]));
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn ($value) => preg_match('/^[\t\r\n ]*[=+\-@]/', (string) $value) ? "'".$value : $value, $row));
            }
            fclose($stream);
        }, 'inventory-'.$report.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate(['warehouse_id' => ['nullable', 'integer'], 'product_id' => ['nullable', 'integer'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
    }
}
