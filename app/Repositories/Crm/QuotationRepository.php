<?php

namespace App\Repositories\Crm;

use App\Enums\Crm\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Crm\CrmQuotation;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QuotationRepository
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    /** @param array<string, mixed> $filters */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->queryForUser($user)
            ->with(['lead', 'creator'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('quotation_number', 'like', "%{$search}%")
                        ->orWhere('customer_company', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhereHas('lead', fn (Builder $lead) => $lead->where('title', 'like', "%{$search}%")->orWhere('business_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();
    }

    public function findForUser(User $user, int $quotationId): CrmQuotation
    {
        return $this->queryForUser($user)
            ->with(['lead.status', 'lead.assignedUser', 'items', 'creator', 'updater', 'auditLogs.user', 'crmCustomer.primaryContact', 'shares.creator'])
            ->findOrFail($quotationId);
    }

    /** @return array{draft: int, sent: int, accepted: int, total_value: float, pending_value: float, latest: Collection<int, CrmQuotation>} */
    public function dashboardMetrics(User $user): array
    {
        $base = $this->queryForUser($user);

        return [
            'draft' => (clone $base)->where('status', QuotationStatus::Draft->value)->count(),
            'sent' => (clone $base)->where('status', QuotationStatus::Sent->value)->count(),
            'accepted' => (clone $base)->where('status', QuotationStatus::Accepted->value)->count(),
            'total_value' => (float) (clone $base)->sum('grand_total'),
            'pending_value' => (float) (clone $base)->whereIn('status', [QuotationStatus::Draft->value, QuotationStatus::Sent->value])->sum('grand_total'),
            'latest' => (clone $base)->with('lead')->latest('created_at')->limit(6)->get(),
        ];
    }

    private function queryForUser(User $user): Builder
    {
        $outletIds = $this->outlets->accessibleOutlets($user, false)->pluck('id');
        $hasCompanyWideAccess = $this->outlets->hasCompanyWideAccess($user);

        return CrmQuotation::query()
            ->where('company_id', $user->company_id)
            ->when(! $hasCompanyWideAccess, fn (Builder $query) => $query->where(function (Builder $scope) use ($user, $outletIds): void {
                $scope->whereHas('lead', function (Builder $lead) use ($user, $outletIds): void {
                    $lead->whereIn('branch_id', $outletIds)
                        ->orWhere(fn (Builder $legacy) => $legacy->whereNull('branch_id')->where(fn (Builder $owner) => $owner->where('assigned_user_id', $user->id)->orWhere('created_by', $user->id)));
                })->orWhereNull('lead_id');
            }))
            ->when($this->isSales($user), fn (Builder $query) => $query->whereHas('lead', fn (Builder $lead) => $lead->where('assigned_user_id', $user->id)));
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }
}
