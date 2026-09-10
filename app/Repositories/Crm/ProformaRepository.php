<?php

namespace App\Repositories\Crm;

use App\Enums\Crm\ProformaStatus;
use App\Enums\UserRole;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProformaRepository
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->query($user)->with('lead')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $scope) => $scope->where('proforma_number', 'like', "%{$search}%")->orWhere('customer_company', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_email', 'like', "%{$search}%")->orWhere('customer_phone', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()->paginate(12)->withQueryString();
    }

    public function find(User $user, int $id): CrmProformaInvoice
    {
        return $this->query($user)->with(['lead', 'customer', 'quotation', 'onboarding', 'items', 'payments.recorder', 'shares.creator'])->findOrFail($id);
    }

    public function metrics(User $user): array
    {
        $query = $this->query($user);

        return ['total_value' => (float) (clone $query)->sum('grand_total'), 'pending_value' => (float) (clone $query)->whereIn('status', [ProformaStatus::Sent->value, ProformaStatus::PartiallyPaid->value, ProformaStatus::Overdue->value])->sum('balance_amount'), 'paid_month' => (float) (clone $query)->whereHas('payments', fn (Builder $payments) => $payments->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()]))->sum('paid_amount'), 'overdue' => (clone $query)->where('due_date', '<', today())->whereNotIn('status', [ProformaStatus::Paid->value, ProformaStatus::Cancelled->value])->count(), 'partial' => (clone $query)->where('status', ProformaStatus::PartiallyPaid->value)->count(), 'latest' => (clone $query)->latest()->limit(6)->get()];
    }

    private function query(User $user): Builder
    {
        $outletIds = $this->outlets->accessibleOutlets($user, false)->pluck('id');
        $wide = $this->outlets->hasCompanyWideAccess($user);
        $isSales = ($user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role)) === UserRole::Sales;

        return CrmProformaInvoice::query()->where('company_id', $user->company_id)
            ->when(! $wide, fn (Builder $query) => $query->where(function (Builder $scope) use ($user, $outletIds): void {
                $scope->whereHas('lead', function (Builder $lead) use ($user, $outletIds): void {
                    $lead->whereIn('branch_id', $outletIds)
                        ->orWhere(fn (Builder $legacy) => $legacy->whereNull('branch_id')->where(fn (Builder $owner) => $owner->where('assigned_user_id', $user->id)->orWhere('created_by', $user->id)));
                })->orWhereNull('lead_id');
            }))
            ->when($isSales, fn (Builder $query) => $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)));
    }
}
