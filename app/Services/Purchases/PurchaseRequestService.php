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
use App\Services\Inventory\InventoryLocationAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(
        private readonly PurchaseNumberService $numbers,
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
        private readonly InventoryLocationAccessService $locations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($user, $data): PurchaseRequest {
            $warehouse = ! empty($data['warehouse_id'])
                ? $this->locations->authorize($user, (int) $data['warehouse_id'])
                : null;
            $request = PurchaseRequest::create([
                'company_id' => $user->company_id,
                'branch_id' => $warehouse?->branch_id ?? $user->branch_id,
                'warehouse_id' => $warehouse?->id,
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
        return DB::transaction(function () use ($request, $user): PurchaseRequest {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== PurchaseRequestStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Only a draft purchase request can be submitted.']);
            }
            $from = $request->status->value;
            $request->update(['status' => PurchaseRequestStatus::PendingReview->value, 'submitted_at' => now()]);
            $this->approvalLog($request, $user, 'submitted', $from, PurchaseRequestStatus::PendingReview->value);
            $this->auditLogger->record('purchase.request.submitted', $request, 'Purchase request submitted');
            $this->dispatch('purchase.request.submitted', $request, $user, ['request_number' => $request->request_number]);

            return $request->refresh();
        });
    }

    /** @param array<int, array{item_id:int,approved_quantity:string|int|float,approval_notes?:string|null}> $items */
    public function approve(PurchaseRequest $request, User $user, array $items = [], ?string $comments = null): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $user, $items, $comments): PurchaseRequest {
            $request = PurchaseRequest::query()->with('items')->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== PurchaseRequestStatus::PendingReview) {
                throw ValidationException::withMessages(['status' => 'Only a submitted purchase request can be approved.']);
            }

            $decisions = collect($items)->keyBy('item_id');
            $hasPartialApproval = false;
            $approvedAny = false;
            foreach ($request->items as $item) {
                $decision = $decisions->get($item->id);
                $quantity = $decision['approved_quantity'] ?? $item->requested_quantity;
                if (! is_numeric($quantity) || (float) $quantity < 0 || (float) $quantity > (float) $item->requested_quantity) {
                    throw ValidationException::withMessages(['items' => 'Approved quantities must be between zero and the requested quantity.']);
                }
                $approvedAny = $approvedAny || (float) $quantity > 0;
                $hasPartialApproval = $hasPartialApproval || (float) $quantity !== (float) $item->requested_quantity;
                $item->update([
                    'approved_quantity' => $quantity,
                    'approval_notes' => $decision['approval_notes'] ?? null,
                ]);
            }
            if (! $approvedAny) {
                throw ValidationException::withMessages(['items' => 'Approve at least one requested quantity or reject the request with a reason.']);
            }

            $from = $request->status->value;
            $to = $hasPartialApproval ? PurchaseRequestStatus::PartiallyApproved : PurchaseRequestStatus::Approved;
            $request->update(['status' => $to->value, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            $this->approvalLog($request, $user, 'approved', $from, $to->value, $comments);
            $this->auditLogger->record('purchase.request.approved', $request, $hasPartialApproval ? 'Purchase request partially approved' : 'Purchase request approved');
            $this->dispatch('purchase.request.approved', $request, $user, ['request_number' => $request->request_number, 'partial' => $hasPartialApproval]);

            return $request->refresh();
        });
    }

    public function reject(PurchaseRequest $request, User $user, ?string $comments = null): PurchaseRequest
    {
        if (blank($comments)) {
            throw ValidationException::withMessages(['comments' => 'Provide a reason when rejecting a purchase request.']);
        }
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

    public function duplicate(PurchaseRequest $request, User $user): PurchaseRequest
    {
        $request->load('items');

        return $this->create($user, [
            'warehouse_id' => $request->warehouse_id,
            'priority' => $request->priority->value,
            'expected_by' => $request->expected_by?->toDateString(),
            'notes' => 'Duplicated from '.$request->request_number.'. '.($request->notes ?? ''),
            'items' => $request->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'supplier_id' => $item->supplier_id,
                'requested_quantity' => $item->requested_quantity,
                'estimated_price' => $item->estimated_price,
                'expected_by' => $item->expected_by?->toDateString(),
                'notes' => $item->notes,
            ])->all(),
        ]);
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
