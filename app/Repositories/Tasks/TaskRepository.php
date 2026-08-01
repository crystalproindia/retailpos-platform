<?php

namespace App\Repositories\Tasks;

use App\Enums\Tasks\TaskStatus;
use App\Enums\Tasks\TaskType;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\CrmSupportTicket;
use App\Models\Tasks\Task;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaskRepository
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    /** @param array<string, mixed> $filters */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->applyFilters($this->queryForUser($user), $filters)
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    /** @param array<string, mixed> $filters */
    public function exportForUser(User $user, array $filters = []): Collection
    {
        return $this->applyFilters($this->queryForUser($user)->where('task_type', TaskType::Work->value), $filters)
            ->orderBy('due_at')
            ->limit(5000)
            ->get();
    }

    public function findForUser(User $user, int $taskId): Task
    {
        return $this->queryForUser($user)->findOrFail($taskId);
    }

    public function queryForUser(User $user): Builder
    {
        $outletIds = $this->outlets->accessibleOutlets($user)->pluck('id');
        $companyWide = $this->outlets->hasCompanyWideAccess($user) && $user->can('tasks.view_team');
        $teamAccess = $user->can('tasks.view_team');

        return Task::query()
            ->where('company_id', $user->company_id)
            ->whereNull('archived_at')
            ->with(['outlet', 'owner', 'assignee', 'assignedEmployee', 'creator', 'completedBy', 'related'])
            ->where(function (Builder $query) use ($user, $outletIds, $companyWide, $teamAccess): void {
                $query->where(function (Builder $personal) use ($user): void {
                    $personal->where('task_type', TaskType::Personal->value)->where('owner_user_id', $user->id);
                })->orWhere(function (Builder $work) use ($user, $outletIds, $companyWide, $teamAccess): void {
                    $work->where('task_type', TaskType::Work->value)
                        ->where(function (Builder $visible) use ($user, $outletIds, $companyWide, $teamAccess): void {
                            $visible->where('owner_user_id', $user->id)
                                ->orWhere('assigned_user_id', $user->id)
                                ->orWhere('created_by_user_id', $user->id);

                            if ($companyWide) {
                                $visible->orWhereRaw('1 = 1');
                            } elseif ($teamAccess && $outletIds->isNotEmpty()) {
                                $visible->orWhereIn('outlet_id', $outletIds);
                            }
                        });
                });
            });
    }

    /** @return array<string, int> */
    public function personalMetrics(User $user): array
    {
        $base = $this->queryForUser($user)->where('task_type', TaskType::Personal->value);

        return [
            'today' => (clone $base)->whereDate('due_at', today())->whereIn('status', $this->openStatuses())->count(),
            'overdue' => (clone $base)->where('due_at', '<', now())->whereIn('status', $this->openStatuses())->count(),
            'upcoming' => (clone $base)->where('due_at', '>', now()->endOfDay())->whereIn('status', $this->openStatuses())->count(),
            'completed_today' => (clone $base)->whereDate('completed_at', today())->count(),
        ];
    }

    /** @return array<string, int> */
    public function workMetrics(User $user): array
    {
        $base = $this->queryForUser($user)->where('task_type', TaskType::Work->value);

        return [
            'today' => (clone $base)->whereDate('due_at', today())->whereIn('status', $this->openStatuses())->count(),
            'overdue' => (clone $base)->where('due_at', '<', now())->whereIn('status', $this->openStatuses())->count(),
            'upcoming' => (clone $base)->where('due_at', '>', now()->endOfDay())->whereIn('status', $this->openStatuses())->count(),
            'assigned_by_manager' => (clone $base)->where('assigned_user_id', $user->id)->where('created_by_user_id', '!=', $user->id)->whereIn('status', $this->openStatuses())->count(),
            'urgent' => (clone $base)->where('priority', 'urgent')->whereIn('status', $this->openStatuses())->count(),
            'completed_today' => (clone $base)->whereDate('completed_at', today())->count(),
            'lead_follow_ups' => (clone $base)->where('related_type', (new CrmLead)->getMorphClass())->whereIn('status', $this->openStatuses())->count(),
            'payment_follow_ups' => (clone $base)->where('related_type', (new CrmInvoice)->getMorphClass())->whereIn('status', $this->openStatuses())->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function teamMetrics(User $user): array
    {
        $base = $this->queryForUser($user)->where('task_type', TaskType::Work->value);
        $open = (clone $base)->whereIn('status', $this->openStatuses());

        return [
            'due_today' => (clone $open)->whereDate('due_at', today())->count(),
            'overdue' => (clone $open)->where('due_at', '<', now())->count(),
            'unassigned' => (clone $open)->whereNull('assigned_user_id')->count(),
            'completed_today' => (clone $base)->whereDate('completed_at', today())->count(),
            'by_outlet' => (clone $open)->selectRaw('outlet_id, count(*) as total')->with('outlet:id,name')->groupBy('outlet_id')->get(),
            'by_assignee' => (clone $open)->whereNotNull('assigned_user_id')->selectRaw('assigned_user_id, count(*) as total')->with('assignee:id,name')->groupBy('assigned_user_id')->get(),
            'by_priority' => (clone $open)->selectRaw('priority, count(*) as total')->groupBy('priority')->pluck('total', 'priority'),
        ];
    }

    /** @return array<int, string> */
    public function openStatuses(): array
    {
        return collect(TaskStatus::cases())->filter->isOpen()->map->value->all();
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $view = $filters['view'] ?? null;

        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $match) use ($search): void {
                    $match->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('assignee', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"))
                        ->orWhereHasMorph('related', [CrmLead::class, CrmCustomer::class, CrmQuotation::class, CrmInvoice::class, CrmSupportTicket::class], function (Builder $related, string $type) use ($search): void {
                            match ($type) {
                                CrmLead::class => $related->where('title', 'like', "%{$search}%")->orWhere('business_name', 'like', "%{$search}%")->orWhere('contact_name', 'like', "%{$search}%"),
                                CrmCustomer::class => $related->where('display_name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%")->orWhere('customer_code', 'like', "%{$search}%"),
                                CrmQuotation::class => $related->where('quotation_number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")->orWhere('customer_company', 'like', "%{$search}%"),
                                CrmInvoice::class => $related->where('invoice_number', 'like', "%{$search}%")->orWhere('billing_name', 'like', "%{$search}%")->orWhere('billing_company', 'like', "%{$search}%"),
                                CrmSupportTicket::class => $related->where('ticket_number', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%"),
                            };
                        });
                });
            })
            ->when($filters['task_type'] ?? null, fn (Builder $query, string $type) => $query->where('task_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['assigned_user_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('assigned_user_id', $id))
            ->when($filters['outlet_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('outlet_id', $id))
            ->when($filters['related_type'] ?? null, fn (Builder $query, string $type) => $query->where('related_type', $type))
            ->when($filters['source_type'] ?? null, fn (Builder $query, string $type) => $query->where('source_type', $type))
            ->when($filters['created_by_user_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('created_by_user_id', $id))
            ->when($filters['due_from'] ?? null, fn (Builder $query, string $date) => $query->where('due_at', '>=', $date))
            ->when($filters['due_to'] ?? null, fn (Builder $query, string $date) => $query->where('due_at', '<=', $date.' 23:59:59'))
            ->when($filters['completed_from'] ?? null, fn (Builder $query, string $date) => $query->where('completed_at', '>=', $date))
            ->when($filters['completed_to'] ?? null, fn (Builder $query, string $date) => $query->where('completed_at', '<=', $date.' 23:59:59'))
            ->when($view === 'today', fn (Builder $query) => $query->whereDate('due_at', today())->whereIn('status', $this->openStatuses()))
            ->when($view === 'upcoming', fn (Builder $query) => $query->where('due_at', '>', now()->endOfDay())->whereIn('status', $this->openStatuses()))
            ->when($view === 'overdue', fn (Builder $query) => $query->where('due_at', '<', now())->whereIn('status', $this->openStatuses()))
            ->when($view === 'completed', fn (Builder $query) => $query->where('status', TaskStatus::Completed->value))
            ->when($view === 'personal', fn (Builder $query) => $query->where('task_type', TaskType::Personal->value))
            ->when(in_array($view, ['work', 'team'], true), fn (Builder $query) => $query->where('task_type', TaskType::Work->value));
    }
}
