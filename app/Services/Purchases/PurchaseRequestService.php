<?php

namespace App\Services\Purchases;

use App\Enums\Purchases\PurchaseRequestPriority;
use App\Enums\Purchases\PurchaseRequestStatus;
use App\Enums\Purchases\PurchaseSourceType;
use App\Events\Domain\Purchases\PurchaseDomainEvent;
use App\Models\Inventory\ReorderSuggestion;
use App\Models\Purchases\PurchaseApprovalLog;
use App\Models\Purchases\PurchaseRequest;
use App\Models\Purchases\SupplierProduct;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    public function __construct(
        private readonly PurchaseNumberService $numbers,
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($user, $data): PurchaseRequest {
            $request = PurchaseRequest::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'request_number' => $this->numbers->next($user->company_id, 'pr'),
                'source_type' => $data['source_type'] ?? PurchaseSourceType::Manual->value,
                'source_id' => $data['source_id'] ?? null,
                'status' => $data['status'] ?? PurchaseRequestStatus::Draft->value,
                'priority' => $data['priority'] ?? PurchaseRequestPriority::Normal->value,
                'requested_by' => $user->id,
                'notes' => $data['notes'] ?? null,
                'expected_by' => $data['expected_by'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $request->items()->create($item);
            }

            $this->auditLogger->record('purchase.request.created', $request, 'Purchase request created');
            $this->dispatch('purchase.request.created', $request, $user, ['request_number' => $request->request_number]);

            return $request->load('items.product', 'items.supplier');
        });
    }

    public function submit(PurchaseRequest $request, User $user): PurchaseRequest
    {
        $from = $request->status->value;
        $request->update(['status' => PurchaseRequestStatus::PendingReview->value]);
        $this->approvalLog($request, $user, 'submitted', $from, PurchaseRequestStatus::PendingReview->value);
        $this->auditLogger->record('purchase.request.submitted', $request, 'Purchase request submitted');
        $this->dispatch('purchase.request.submitted', $request, $user, ['request_number' => $request->request_number]);

        return $request->refresh();
    }

    public function approve(PurchaseRequest $request, User $user): PurchaseRequest
    {
        $from = $request->status->value;
        $request->load('items');
        foreach ($request->items as $item) {
            $item->update(['approved_quantity' => $item->approved_quantity ?? $item->requested_quantity]);
        }

        $request->update([
            'status' => PurchaseRequestStatus::Approved->value,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        $this->approvalLog($request, $user, 'approved', $from, PurchaseRequestStatus::Approved->value);
        $this->auditLogger->record('purchase.request.approved', $request, 'Purchase request approved');
        $this->dispatch('purchase.request.approved', $request, $user, ['request_number' => $request->request_number]);

        return $request->refresh();
    }

    public function reject(PurchaseRequest $request, User $user, ?string $comments = null): PurchaseRequest
    {
        $from = $request->status->value;
        $request->update([
            'status' => PurchaseRequestStatus::Rejected->value,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        $this->approvalLog($request, $user, 'rejected', $from, PurchaseRequestStatus::Rejected->value, $comments);
        $this->auditLogger->record('purchase.request.rejected', $request, 'Purchase request rejected');
        $this->dispatch('purchase.request.rejected', $request, $user, ['request_number' => $request->request_number, 'comments' => $comments]);

        return $request->refresh();
    }

    public function markConverted(PurchaseRequest $request, User $user, int $purchaseOrderId): PurchaseRequest
    {
        $from = $request->status->value;
        $request->update(['status' => PurchaseRequestStatus::ConvertedToPo->value]);
        $this->approvalLog($request, $user, 'converted_to_po', $from, PurchaseRequestStatus::ConvertedToPo->value);
        $this->auditLogger->record('purchase.request.converted_to_po', $request, 'Purchase request converted to PO', ['purchase_order_id' => $purchaseOrderId]);
        $this->dispatch('purchase.request.converted_to_po', $request, $user, ['request_number' => $request->request_number, 'purchase_order_id' => $purchaseOrderId]);

        return $request->refresh();
    }

    /**
     * @param  array<int, int>  $suggestionIds
     */
    public function createFromReorderSuggestions(User $user, array $suggestionIds): PurchaseRequest
    {
        return DB::transaction(function () use ($user, $suggestionIds): PurchaseRequest {
            $suggestions = ReorderSuggestion::query()
                ->with('product')
                ->where('company_id', $user->company_id)
                ->whereIn('id', $suggestionIds)
                ->where('status', 'pending')
                ->get();

            abort_if($suggestions->isEmpty(), 422, 'No pending reorder suggestions selected.');

            $warehouseId = $suggestions->first()->warehouse_id;
            $items = $suggestions->map(function (ReorderSuggestion $suggestion): array {
                $supplierProduct = SupplierProduct::query()
                    ->where('company_id', $suggestion->company_id)
                    ->where('product_id', $suggestion->product_id)
                    ->where('is_preferred', true)
                    ->first();

                return [
                    'product_id' => $suggestion->product_id,
                    'supplier_id' => $supplierProduct?->supplier_id,
                    'requested_quantity' => $suggestion->suggested_quantity,
                    'estimated_price' => $supplierProduct?->purchase_price,
                    'notes' => $suggestion->reason,
                ];
            })->all();

            $request = $this->create($user, [
                'warehouse_id' => $warehouseId,
                'source_type' => PurchaseSourceType::ReorderSuggestion->value,
                'source_id' => $suggestions->first()->id,
                'priority' => PurchaseRequestPriority::High->value,
                'status' => PurchaseRequestStatus::PendingReview->value,
                'notes' => 'Created from reorder suggestions: '.$suggestions->pluck('id')->implode(', '),
                'items' => $items,
            ]);

            $suggestions->each->update([
                'status' => 'reviewed',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLogger->record('purchase.reorder_request.created', $request, 'Purchase request created from reorder suggestions', ['suggestion_ids' => $suggestions->pluck('id')->all()]);
            $this->dispatch('purchase.reorder_request.created', $request, $user, ['request_number' => $request->request_number, 'suggestion_ids' => $suggestions->pluck('id')->all()]);

            return $request;
        });
    }

    private function approvalLog(PurchaseRequest $request, User $user, string $action, ?string $from, string $to, ?string $comments = null): void
    {
        PurchaseApprovalLog::create([
            'company_id' => $request->company_id,
            'approvable_type' => PurchaseRequest::class,
            'approvable_id' => $request->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user->id,
            'comments' => $comments,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $eventKey, PurchaseRequest $request, User $user, array $payload): void
    {
        $this->domainEvents->dispatch(new PurchaseDomainEvent(
            key: $eventKey,
            companyId: $request->company_id,
            actorId: $user->id,
            aggregateType: PurchaseRequest::class,
            aggregateId: $request->id,
            payload: $payload,
        ));
    }
}
