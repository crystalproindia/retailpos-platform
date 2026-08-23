<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use App\Models\Purchases\Supplier;
use App\Services\Inventory\InventoryIntelligenceService;
use App\Services\Inventory\InventoryLocationAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryIntelligenceController extends Controller
{
    public function index(Request $request, InventoryIntelligenceService $intelligence, InventoryLocationAccessService $locations): View
    {
        $filters = $this->filters($request);

        return view('command-center.inventory.intelligence.index', [
            'intelligence' => $intelligence->dashboard($request->user(), $filters),
            'filters' => $filters,
            'warehouses' => $locations->accessibleWarehouses($request->user(), false),
            'categories' => InventoryCategory::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(['id', 'name']),
            'brands' => InventoryBrand::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->limit(250)->get(['id', 'name', 'sku']),
            'suppliers' => Supplier::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->limit(250)->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, InventoryIntelligenceService $intelligence, string $dataset): StreamedResponse
    {
        abort_unless(in_array($dataset, ['reorder', 'fast', 'slow', 'dead', 'aging', 'transfers'], true), 404);
        $rows = $intelligence->exportRows($request->user(), $dataset, $this->filters($request));

        return Response::streamDownload(function () use ($dataset, $rows): void {
            $stream = fopen('php://output', 'w');
            if ($dataset === 'aging') {
                fputcsv($stream, ['Age bucket', 'Quantity', 'Stock value', 'Percentage']);
                foreach ($rows as $row) {
                    $this->write($stream, [$row['label'], number_format($row['quantity'], 3, '.', ''), number_format($row['value_minor'] / 100, 2, '.', ''), number_format($row['percentage'], 2, '.', '').'%']);
                }
            } elseif ($dataset === 'transfers') {
                fputcsv($stream, ['Product', 'SKU', 'From', 'From stock', 'To', 'To stock', 'Suggested quantity', 'Reason']);
                foreach ($rows as $row) {
                    $this->write($stream, [$row['product'], $row['sku'], $row['source_warehouse'], $row['source_stock'], $row['destination_warehouse'], $row['destination_stock'], $row['suggested_quantity'], $row['reason']]);
                }
            } else {
                fputcsv($stream, ['Product', 'SKU', 'Outlet', 'Warehouse', 'On hand', 'Units sold', 'Daily velocity', 'Stock value', 'Suggested reorder', 'Purchase value', 'Health']);
                foreach ($rows as $row) {
                    $this->write($stream, [$row['product'], $row['sku'], $row['outlet'], $row['warehouse'], $row['on_hand'], $row['units_sold'], $row['daily_velocity'], number_format($row['stock_value_minor'] / 100, 2, '.', ''), $row['suggested_reorder_quantity'], number_format($row['recommended_purchase_value_minor'] / 100, 2, '.', ''), str($row['health'])->headline()]);
                }
            }
            fclose($stream);
        }, 'inventory-intelligence-'.$dataset.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'velocity_period' => ['nullable', Rule::in([7, 30, 60, 90])],
            'stock_status' => ['nullable', Rule::in(['low', 'out', 'fast', 'slow', 'dead', 'overstocked', 'reorder'])],
            'aging_range' => ['nullable', Rule::in(['0_30', '31_60', '61_90', '91_180', '180_plus', 'unknown'])],
        ]);
    }

    /** @param resource $stream */
    private function write($stream, array $row): void
    {
        fputcsv($stream, array_map(fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value, $row));
    }
}
