<?php

namespace App\Services\Purchases;

use App\Events\Domain\Purchases\PurchaseDomainEvent;
use App\Models\Purchases\GoodsReceiptItem;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseReturnItem;
use App\Models\Purchases\Supplier;
use App\Models\Purchases\SupplierScoreSnapshot;
use App\Services\Events\DomainEventDispatcher;

class SupplierScoreService
{
    public function __construct(private readonly DomainEventDispatcher $domainEvents) {}

    public function snapshot(Supplier $supplier, ?int $actorId = null, ?string $notes = null): SupplierScoreSnapshot
    {
        $purchaseValue = (float) PurchaseOrder::query()
            ->where('company_id', $supplier->company_id)
            ->where('supplier_id', $supplier->id)
            ->whereNotIn('status', ['cancelled'])
            ->sum('grand_total');

        $receiptStats = GoodsReceiptItem::query()
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->where('goods_receipts.company_id', $supplier->company_id)
            ->where('goods_receipts.supplier_id', $supplier->id)
            ->selectRaw('COALESCE(SUM(accepted_quantity), 0) as accepted, COALESCE(SUM(rejected_quantity), 0) as rejected')
            ->first();

        $returnedQuantity = (float) PurchaseReturnItem::query()
            ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
            ->where('purchase_returns.company_id', $supplier->company_id)
            ->where('purchase_returns.supplier_id', $supplier->id)
            ->where('purchase_returns.status', 'completed')
            ->sum('quantity');

        $receivedQuantity = (float) ($receiptStats?->accepted ?? 0);
        $rejectedQuantity = (float) ($receiptStats?->rejected ?? 0);
        $totalInspected = $receivedQuantity + $rejectedQuantity;

        $priceScore = $purchaseValue > 0 ? 75.0 : null;
        $deliveryScore = $supplier->lead_time_days ? max(40, min(95, 100 - ($supplier->lead_time_days * 2))) : null;
        $returnQualityScore = $totalInspected > 0 ? max(0, 100 - (($rejectedQuantity + $returnedQuantity) / max(1, $totalInspected) * 100)) : null;
        $serviceScore = $supplier->manual_rating ? (float) $supplier->manual_rating : ($supplier->service_notes ? 70.0 : null);
        $productPerformanceScore = null;

        $scores = collect([$priceScore, $deliveryScore, $returnQualityScore, $serviceScore])->filter(fn ($score) => $score !== null);
        $overall = $scores->isNotEmpty() ? round($scores->avg(), 2) : null;

        $snapshot = SupplierScoreSnapshot::create([
            'company_id' => $supplier->company_id,
            'supplier_id' => $supplier->id,
            'product_performance_score' => $productPerformanceScore,
            'price_score' => $priceScore,
            'delivery_score' => $deliveryScore,
            'return_quality_score' => $returnQualityScore,
            'service_score' => $serviceScore,
            'overall_score' => $overall,
            'purchase_value' => $purchaseValue,
            'received_quantity' => $receivedQuantity,
            'rejected_quantity' => $rejectedQuantity,
            'returned_quantity' => $returnedQuantity,
            'delayed_delivery_count' => 0,
            'calculated_at' => now(),
            'notes' => $notes ?? 'Rule-based score from purchase, GRN, return, lead-time, and manual service data. POS sales contribution is future-ready.',
        ]);

        $supplier->update(['rating' => $overall]);

        $this->domainEvents->dispatch(new PurchaseDomainEvent(
            key: 'purchase.supplier.score_updated',
            companyId: $supplier->company_id,
            actorId: $actorId,
            aggregateType: Supplier::class,
            aggregateId: $supplier->id,
            payload: [
                'supplier_id' => $supplier->id,
                'overall_score' => $overall,
                'sales_score_status' => 'future_ready_no_pos_sales_history',
            ],
        ));

        return $snapshot;
    }
}
