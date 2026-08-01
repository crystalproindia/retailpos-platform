<?php

namespace App\Services\Tasks;

use App\Models\Branch;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\CrmSupportTicket;
use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Repositories\Crm\CrmOnboardingRepository;
use App\Repositories\Crm\CrmSupportTicketRepository;
use App\Repositories\Crm\InvoiceRepository;
use App\Repositories\Crm\QuotationRepository;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;

class TaskRelatedRecordRegistry
{
    public function __construct(
        private readonly CrmCustomerRepository $customers,
        private readonly QuotationRepository $quotations,
        private readonly InvoiceRepository $invoices,
        private readonly CrmOnboardingRepository $onboardings,
        private readonly CrmSupportTicketRepository $supportTickets,
        private readonly OutletAccessService $outlets,
    ) {}

    /**
     * These are intentionally explicit keys, rather than model class names supplied by a request.
     * More record types can be added only after their tenant and outlet authorization is understood.
     *
     * @return array<string, array{label: string, model: class-string<Model>, route: ?string, permission: string}>
     */
    public function definitions(): array
    {
        return [
            'lead' => ['label' => 'Lead', 'model' => CrmLead::class, 'route' => 'crm.leads.show', 'permission' => 'crm.leads.view'],
            'customer' => ['label' => 'CRM customer', 'model' => CrmCustomer::class, 'route' => 'crm.customers.show', 'permission' => 'crm.customers.view'],
            'quotation' => ['label' => 'Quotation', 'model' => CrmQuotation::class, 'route' => 'crm.quotations.show', 'permission' => 'crm.quotations.view'],
            'invoice' => ['label' => 'CRM invoice', 'model' => CrmInvoice::class, 'route' => 'sales.invoices.show', 'permission' => 'sales.invoices.view'],
            'onboarding' => ['label' => 'Customer onboarding', 'model' => CrmCustomerOnboarding::class, 'route' => 'crm.onboarding.show', 'permission' => 'crm.onboarding.view'],
            'support_ticket' => ['label' => 'Support ticket', 'model' => CrmSupportTicket::class, 'route' => 'crm.support.tickets.show', 'permission' => 'crm.support.view'],
            'employee' => ['label' => 'Employee', 'model' => WorkforceEmployee::class, 'route' => 'workforce.employees.show', 'permission' => 'workforce.view'],
            'outlet' => ['label' => 'Outlet', 'model' => Branch::class, 'route' => 'settings.outlets.show', 'permission' => 'outlets.view'],
        ];
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return collect($this->definitions())->mapWithKeys(fn (array $definition, string $key) => [$key => $definition['label']])->all();
    }

    public function supports(?string $key): bool
    {
        return $key !== null && array_key_exists($key, $this->definitions());
    }

    public function findForCompany(string $key, int $recordId, int $companyId): ?Model
    {
        $definition = $this->definitions()[$key] ?? null;
        if (! $definition) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = $definition['model'];

        return $model::query()->where('company_id', $companyId)->find($recordId);
    }

    /**
     * Resolves a related record through the same tenant-scoped read path used by
     * its native module. A task link therefore cannot grant access to a record.
     */
    public function findForUser(User $user, string $key, int $recordId): ?Model
    {
        $permission = $this->definitions()[$key]['permission'] ?? null;
        if (! $permission || ! $user->can($permission)) {
            return null;
        }

        try {
            return match ($key) {
                'lead' => $this->leadForUser($user, $recordId),
                'customer' => $this->customers->findForUser($user, $recordId),
                'quotation' => $this->quotations->findForUser($user, $recordId),
                'invoice' => $this->invoices->find($user, $recordId),
                'onboarding' => $this->onboardings->find($user, $recordId),
                'support_ticket' => $this->supportTickets->find($user, $recordId),
                'employee' => $this->employeeForUser($user, $recordId),
                'outlet' => $this->outletForUser($user, $recordId),
                default => null,
            };
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    public function isVisibleTo(User $user, Model $record): bool
    {
        $key = $this->keyFor($record);

        return $key !== null && $this->findForUser($user, $key, (int) $record->getKey()) !== null;
    }

    public function keyFor(Model $record): ?string
    {
        foreach ($this->definitions() as $key => $definition) {
            if ($record instanceof $definition['model']) {
                return $key;
            }
        }

        return null;
    }

    public function outletId(Model $record): ?int
    {
        if ($record instanceof Branch) {
            return $record->id;
        }

        if (isset($record->branch_id) && $record->branch_id) {
            return (int) $record->branch_id;
        }

        if ($record instanceof CrmQuotation && $record->lead) {
            return $record->lead->branch_id;
        }

        if ($record instanceof CrmCustomer && $record->lead) {
            return $record->lead->branch_id;
        }

        if ($record instanceof CrmCustomerOnboarding && $record->lead) {
            return $record->lead->branch_id;
        }

        if ($record instanceof CrmSupportTicket && $record->lead) {
            return $record->lead->branch_id;
        }

        return null;
    }

    public function label(Model $record): string
    {
        return (string) (Arr::first([
            $record->title ?? null,
            $record->subject ?? null,
            $record->invoice_number ?? null,
            $record->quotation_number ?? null,
            $record->ticket_number ?? null,
            $record->display_name ?? null,
            $record->company_name ?? null,
            $record->name ?? null,
        ]) ?? 'Record #'.$record->getKey());
    }

    public function routeFor(Model $record): ?string
    {
        $key = $this->keyFor($record);
        $route = $key ? ($this->definitions()[$key]['route'] ?? null) : null;

        return $route && \Illuminate\Support\Facades\Route::has($route) ? route($route, $record) : null;
    }

    private function leadForUser(User $user, int $recordId): ?CrmLead
    {
        $lead = CrmLead::query()->where('company_id', $user->company_id)->find($recordId);

        return $lead && $user->can('view', $lead) ? $lead : null;
    }

    private function employeeForUser(User $user, int $recordId): ?WorkforceEmployee
    {
        if (! $user->can('workforce.view')) {
            return null;
        }

        $employee = WorkforceEmployee::query()->where('company_id', $user->company_id)->find($recordId);
        if (! $employee) {
            return null;
        }

        $outletId = $employee->primary_branch_id;

        return ! $outletId || $this->outlets->hasCompanyWideAccess($user) || $this->outlets->accessibleOutlets($user)->contains('id', $outletId)
            ? $employee
            : null;
    }

    private function outletForUser(User $user, int $recordId): ?Branch
    {
        $outlet = Branch::query()->where('company_id', $user->company_id)->find($recordId);

        return $outlet && $this->outlets->canAccess($user, $outlet) ? $outlet : null;
    }
}
