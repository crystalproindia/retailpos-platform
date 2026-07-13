<?php

namespace App\Services\Pos;

use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use Illuminate\Support\Carbon;

class PosDashboardService
{
    /** @return array<int, int> */
    public function popularProductIds(int $companyId, ?int $branchId): array
    {
        return PosSaleItem::query()
            ->selectRaw('product_id, SUM(quantity) as quantity_sold')
            ->where('company_id', $companyId)
            ->whereHas('sale', fn ($query) => $query->where('status', 'completed')->when($branchId, fn ($sale) => $sale->where('branch_id', $branchId)))
            ->groupBy('product_id')
            ->orderByDesc('quantity_sold')
            ->limit(20)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<string, mixed> */
    public function summary(int $companyId, ?int $branchId): array
    {
        $today = Carbon::today();
        $completed = PosSale::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereDate('completed_at', $today);

        $sales = (clone $completed)->sum('total_amount');
        $bills = (clone $completed)->count();

        return [
            'today_sales' => (float) $sales,
            'today_bills' => $bills,
            'average_bill' => $bills ? round((float) $sales / $bills, 2) : 0,
            'held_bills' => PosSale::query()->where('company_id', $companyId)->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'held')->count(),
            'active_cashiers' => (clone $completed)->whereNotNull('completed_by')->distinct('completed_by')->count('completed_by'),
            'payments' => (clone $completed)->with('payments')->get()->flatMap->payments->groupBy('payment_method')->map(fn ($payments) => (float) $payments->sum('amount'))->all(),
            'recent_sales' => PosSale::query()->with(['customer', 'payments', 'completer'])->where('company_id', $companyId)->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'completed')->latest('completed_at')->limit(8)->get(),
            'top_products' => PosSaleItem::query()
                ->selectRaw('product_id, product_name, sku, SUM(quantity) as quantity_sold, SUM(line_total) as sales_total')
                ->where('company_id', $companyId)
                ->whereHas('sale', fn ($query) => $query->where('status', 'completed')->when($branchId, fn ($sale) => $sale->where('branch_id', $branchId)))
                ->groupBy('product_id', 'product_name', 'sku')
                ->orderByDesc('quantity_sold')
                ->limit(5)
                ->get(),
        ];
    }
}
