<?php

namespace App\Services\Purchases;

use App\Enums\Purchases\PurchaseReturnStatus;
use App\Events\Domain\Purchases\PurchaseDomainEvent;
use App\Models\Purchases\GoodsReceipt;
use App\Models\Purchases\PurchaseApprovalLog;
use App\Models\Purchases\PurchaseReturn;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(
        private readonly PurchaseNumberService $numbers,
        private readonly SupplierScoreService $scores,
        private readonly StockService $stockService,
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($user, $data): PurchaseReturn {
            $receipt = empty($data['goods_receipt_id'])
                ? null
                : GoodsReceipt::query()->where('company_id', $user->company_id)->findOrFail((int) $data['goods_receipt_id']);

            $return = PurchaseReturn::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'warehouse_id' => $receipt?->warehouse_id ?? $data['warehouse_id'],
                'supplier_id' => $receipt?->supplier_id ?? $data['supplier_id'],
                'goods_receipt_id' => $receipt?->id,
                'return_number' => $this->numbers->next($user->company_id, 'return'),
                'status' => $data['status'] ?? PurchaseReturnStatus::Draft->value,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $item) {
                $return->items()->create([
                    'product_id' => $item['product_id'],
                    'stock_location_id' => $item['stock_location_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'reason' => $item['reason'] ?? null,
                ]);
            }

            $this->auditLogger->record('purchase.return.created', $return, 'Purchase return created');
            $this->dispatch('purchase.return.created', $return, $user, ['return_number' => $return->return_number]);

            return $return->refresh()->load(['supplier', 'warehouse', 'goodsReceipt', 'items.product']);
        });
    }

    public function approve(PurchaseReturn $return, User $user): PurchaseReturn
    {
        $from = $return->status->value;
        $return->update([
            'status' => PurchaseReturnStatus::Approved->value,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        $this->approvalLog($return, $user, 'approved', $from, PurchaseReturnStatus::Approved->value);
        $this->auditLogger->record('purchase.return.approved', $return, 'Purchase return approved');
        $this->dispatch('purchase.return.approved', $return, $user, ['return_number' => $return->return_number]);

        return $return->refresh();
    }

    public function complete(PurchaseReturn $return, User $user): PurchaseReturn
    {
        if ($return->status === PurchaseReturnStatus::Completed) {
            return $return;
        }

        return DB::transaction(function () use ($return, $user): PurchaseReturn {
            $return->load(['items', 'supplier']);

            foreach ($return->items as $item) {
                $this->stockService->recordPurchaseReturn($user, [
                    'branch_id' => $return->branch_id,
                    'warehouse_id' => $return->warehouse_id,
                    'stock_location_id' => $item->stock_location_id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $return->id,
                    'reason' => 'Purchase return '.$return->return_number,
                    'notes' => $item->reason,
                ]);
            }

            $from = $return->status->value;
            $return->update(['status' => PurchaseReturnStatus::Completed->value]);
            $this->approvalLog($return, $user, 'completed', $from, PurchaseReturnStatus::Completed->value);
            $this->scores->snapshot($return->supplier, $user->id, 'Supplier score refreshed after purchase return.');
            $this->auditLogger->record('purchase.return.completed', $return, 'Purchase return completed and posted to stock');
            $this->dispatch('purchase.return.completed', $return, $user, ['return_number' => $return->return_number]);

            return $return->refresh()->load(['supplier', 'warehouse', 'goodsReceipt', 'items.product']);
        });
    }

    private function approvalLog(PurchaseReturn $return, User $user, string $action, ?string $from, string $to): void
    {
        PurchaseApprovalLog::create([
            'company_id' => $return->company_id,
            'approvable_type' => PurchaseReturn::class,
            'approvable_id' => $return->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $eventKey, PurchaseReturn $return, User $user, array $payload): void
    {
        $this->domainEvents->dispatch(new PurchaseDomainEvent(
            key: $eventKey,
            companyId: $return->company_id,
            actorId: $user->id,
            aggregateType: PurchaseReturn::class,
            aggregateId: $return->id,
            payload: $payload + ['supplier_id' => $return->supplier_id],
        ));
    }
}
