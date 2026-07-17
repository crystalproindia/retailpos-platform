<?php

namespace App\Repositories\Crm;

use App\Enums\Crm\OnboardingStatus;
use App\Enums\UserRole;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmOnboardingTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CrmOnboardingRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->queryForUser($user)->with(['customer.primaryContact', 'assignee', 'implementationOwner'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void { $query->where(fn (Builder $q) => $q->where('onboarding_number', 'like', "%{$search}%")->orWhere('business_name', 'like', "%{$search}%")->orWhere('customer_contact_name', 'like', "%{$search}%")->orWhere('customer_contact_phone', 'like', "%{$search}%")->orWhere('customer_contact_email', 'like', "%{$search}%")->orWhereHas('customer', fn (Builder $customer) => $customer->where('company_name', 'like', "%{$search}%")->orWhere('display_name', 'like', "%{$search}%"))); })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, int $owner) => $query->where(fn (Builder $q) => $q->where('assigned_to', $owner)->orWhere('implementation_owner_id', $owner)))
            ->when($filters['overdue'] ?? null, fn (Builder $query) => $query->whereDate('target_go_live_date', '<', today())->whereNotIn('status', [OnboardingStatus::Live->value, OnboardingStatus::Cancelled->value]))
            ->when($filters['target_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('target_go_live_date', '>=', $date))
            ->when($filters['target_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('target_go_live_date', '<=', $date))
            ->latest()->paginate(15)->withQueryString();
    }

    public function find(User $user, int $id): CrmCustomerOnboarding
    {
        return $this->queryForUser($user)->with(['customer.primaryContact', 'lead.status', 'quotation', 'proforma', 'assignee', 'implementationOwner', 'tasks.assignee', 'tasks.completer', 'onboardingNotes.creator', 'documents.uploader', 'auditLogs.user'])->findOrFail($id);
    }

    public function task(CrmCustomerOnboarding $onboarding, int $task): CrmOnboardingTask { return $onboarding->tasks()->findOrFail($task); }

    /** @return array<string, mixed> */
    public function dashboard(User $user): array
    {
        $base = $this->queryForUser($user);
        $active = (clone $base)->whereNotIn('status', [OnboardingStatus::Live->value, OnboardingStatus::Cancelled->value]);
        $overdueTasks = CrmOnboardingTask::query()->whereHas('onboarding', fn (Builder $query) => $query->where('company_id', $user->company_id)->when($this->isSales($user), fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere('implementation_owner_id', $user->id)))->whereNotIn('status', ['completed', 'skipped'])->whereDate('due_date', '<', today())->count();

        return ['active' => (clone $active)->count(), 'waiting_for_customer' => (clone $base)->where('status', OnboardingStatus::WaitingForCustomer->value)->count(), 'overdue_tasks' => $overdueTasks, 'go_live_ready' => (clone $base)->where('status', OnboardingStatus::GoLiveReady->value)->count(), 'live_this_month' => (clone $base)->where('status', OnboardingStatus::Live->value)->whereBetween('actual_go_live_date', [now()->startOfMonth(), now()->endOfMonth()])->count(), 'upcoming' => (clone $active)->with(['customer', 'implementationOwner'])->whereNotNull('target_go_live_date')->whereDate('target_go_live_date', '>=', today())->orderBy('target_go_live_date')->limit(5)->get(), 'recent' => (clone $base)->with(['customer', 'implementationOwner'])->latest('created_at')->limit(5)->get()];
    }

    private function queryForUser(User $user): Builder { return CrmCustomerOnboarding::query()->where('company_id', $user->company_id)->when($this->isSales($user), fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere('implementation_owner_id', $user->id))); }
    private function isSales(User $user): bool { $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role); return $role === UserRole::Sales; }
}
