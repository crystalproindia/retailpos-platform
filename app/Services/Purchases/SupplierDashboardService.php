<?php

namespace App\Services\Purchases;

use App\Models\Purchases\GoodsReceipt;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\Supplier;

class SupplierDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(int $companyId): array
    {
        return [
            'cards' => [
                ['label' => 'Active Suppliers', 'value' => Supplier::query()->where('company_id', $companyId)->where('is_active', true)->count(), 'tone' => 'success'],
                ['label' => 'Mapped Products', 'value' => Supplier::query()->where('company_id', $companyId)->withCount('products')->get()->sum('products_count'), 'tone' => 'neutral'],
                ['label' => 'Avg Score', 'value' => round((float) Supplier::query()->where('company_id', $companyId)->avg('rating'), 1), 'tone' => 'info'],
                ['label' => 'Low Rated', 'value' => Supplier::query()->where('company_id', $companyId)->where('rating', '<', 60)->count(), 'tone' => 'warning'],
            ],
            'topSuppliers' => Supplier::query()
                ->withCount(['products', 'purchaseOrders'])
                ->where('company_id', $companyId)
                ->orderByDesc('rating')
                ->limit(8)
                ->get(),
            'recentReceipts' => GoodsReceipt::query()
                ->with(['supplier', 'warehouse'])
                ->where('company_id', $companyId)
                ->latest()
                ->limit(6)
                ->get(),
            'recentReturns' => PurchaseReturn::query()
                ->with(['supplier', 'warehouse'])
                ->where('company_id', $companyId)
                ->latest()
                ->limit(6)
                ->get(),
            'purchaseOrdersBySupplier' => PurchaseOrder::query()
                ->with('supplier')
                ->where('company_id', $companyId)
                ->latest()
                ->limit(8)
                ->get(),
        ];
    }
}
