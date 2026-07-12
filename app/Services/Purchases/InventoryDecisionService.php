<?php

namespace App\Services\Purchases;

use App\Models\Inventory\ReorderSuggestion;
use App\Models\Inventory\StockLevel;
use App\Models\Purchases\SupplierProduct;

class InventoryDecisionService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(int $companyId): array
    {
        $stockRows = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->where('company_id', $companyId)
            ->orderBy('quantity_available')
            ->limit(50)
            ->get()
            ->map(function (StockLevel $level): array {
                $averageDailySales = (float) ($level->average_daily_sales ?? 0);
                $available = (float) $level->quantity_available;
                $expectedSalesDays = $averageDailySales > 0
                    ? round($available / $averageDailySales, 1)
                    : null;

                return [
                    'stock_level' => $level,
                    'available' => $available,
                    'average_daily_sales' => $averageDailySales,
                    'expected_sales_days' => $expectedSalesDays,
                    'expected_sales_label' => $expectedSalesDays === null ? 'Not enough sales data' : $expectedSalesDays.' days',
                    'risk' => $available <= 0 ? 'stockout' : ($available <= (float) $level->reorder_point ? 'reorder' : 'healthy'),
                    'suggested_quantity' => $level->reorder_quantity ?: max(0, (float) $level->maximum_stock - $available),
                    'preferred_supplier' => SupplierProduct::query()
                        ->with('supplier')
                        ->where('company_id', $level->company_id)
                        ->where('product_id', $level->product_id)
                        ->where('is_preferred', true)
                        ->first(),
                    'ai_status' => 'Rule-based decision now. AI-ready adapter can score this row later.',
                ];
            });

        return [
            'cards' => [
                ['label' => 'Decision Rows', 'value' => $stockRows->count(), 'tone' => 'neutral'],
                ['label' => 'Reorder Risk', 'value' => $stockRows->where('risk', 'reorder')->count(), 'tone' => 'warning'],
                ['label' => 'Stockout Risk', 'value' => $stockRows->where('risk', 'stockout')->count(), 'tone' => 'danger'],
                ['label' => 'Pending Suggestions', 'value' => ReorderSuggestion::query()->where('company_id', $companyId)->where('status', 'pending')->count(), 'tone' => 'info'],
            ],
            'rows' => $stockRows,
            'suggestions' => ReorderSuggestion::query()
                ->with(['product', 'warehouse'])
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->latest()
                ->limit(20)
                ->get(),
        ];
    }
}
