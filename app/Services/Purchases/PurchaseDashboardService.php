<?php

namespace App\Services\Purchases;

use App\Models\Inventory\ReorderSuggestion;
use App\Models\Purchases\GoodsReceipt;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseRequest;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\Supplier;
use Illuminate\Support\Facades\DB;

class PurchaseDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(int $companyId): array
    {
        return [
            'cards' => [
                ['label' => 'Suppliers', 'value' => Supplier::query()->where('company_id', $companyId)->count(), 'tone' => 'neutral'],
                ['label' => 'Pending Requests', 'value' => PurchaseRequest::query()->where('company_id', $companyId)->where('status', 'pending_review')->count(), 'tone' => 'warning'],
                ['label' => 'Open POs', 'value' => PurchaseOrder::query()->where('company_id', $companyId)->whereIn('status', ['draft', 'pending_approval', 'approved', 'sent', 'partially_received'])->count(), 'tone' => 'info'],
                ['label' => 'Goods Receipts', 'value' => GoodsReceipt::query()->where('company_id', $companyId)->count(), 'tone' => 'success'],
                ['label' => 'Returns', 'value' => PurchaseReturn::query()->where('company_id', $companyId)->count(), 'tone' => 'danger'],
                ['label' => 'Pending Reorders', 'value' => ReorderSuggestion::query()->where('company_id', $companyId)->where('status', 'pending')->count(), 'tone' => 'warning'],
            ],
            'purchaseValue' => (float) PurchaseOrder::query()
                ->where('company_id', $companyId)
                ->whereNotIn('status', ['cancelled'])
                ->sum('grand_total'),
            'pendingApprovals' => [
                'requests' => PurchaseRequest::query()
                    ->with(['warehouse', 'requester'])
                    ->where('company_id', $companyId)
                    ->where('status', 'pending_review')
                    ->latest()
                    ->limit(5)
                    ->get(),
                'orders' => PurchaseOrder::query()
                    ->with(['supplier', 'warehouse'])
                    ->where('company_id', $companyId)
                    ->where('status', 'pending_approval')
                    ->latest()
                    ->limit(5)
                    ->get(),
            ],
            'recentOrders' => PurchaseOrder::query()
                ->with(['supplier', 'warehouse'])
                ->where('company_id', $companyId)
                ->latest()
                ->limit(8)
                ->get(),
            'supplierValue' => PurchaseOrder::query()
                ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                ->where('purchase_orders.company_id', $companyId)
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderByDesc(DB::raw('SUM(purchase_orders.grand_total)'))
                ->limit(6)
                ->get(['suppliers.name', DB::raw('SUM(purchase_orders.grand_total) as value')]),
        ];
    }
}
