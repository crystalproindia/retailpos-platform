<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\OnboardingDocumentStatus;
use App\Enums\Crm\OnboardingStatus;
use App\Enums\Crm\OnboardingTaskStatus;
use App\Events\Domain\Crm\OnboardingEvent;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmOnboardingDocument;
use App\Models\Crm\CrmOnboardingNote;
use App\Models\Crm\CrmOnboardingTask;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmActivity;
use App\Models\Company;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmOnboardingService
{
    public function __construct(private readonly CrmOnboardingTemplateService $template, private readonly AuditLogger $auditLogger, private readonly DomainEventDispatcher $events) {}

    /** @param array<string, mixed> $data */
    public function start(CrmCustomer $customer, User $actor, array $data = [], ?CrmProformaInvoice $proforma = null): CrmCustomerOnboarding
    {
        return DB::transaction(function () use ($customer, $actor, $data, $proforma): CrmCustomerOnboarding {
            $this->assertCustomerBelongsToCompany($customer, $actor);
            $this->assertUsersBelongToCompany($actor, Arr::only($data, ['assigned_to', 'implementation_owner_id']));
            Company::query()->whereKey($actor->company_id)->lockForUpdate()->firstOrFail();
            $existing = CrmCustomerOnboarding::query()->where('company_id', $actor->company_id)->where('customer_id', $customer->id)->whereNotIn('status', [OnboardingStatus::Live->value, OnboardingStatus::Cancelled->value])->lockForUpdate()->first();
            if ($existing) { throw ValidationException::withMessages(['customer' => 'This customer already has an active onboarding.']); }

            $contact = $customer->primaryContact;
            $onboarding = CrmCustomerOnboarding::create(array_merge([
                'company_id' => $actor->company_id, 'customer_id' => $customer->id, 'lead_id' => $customer->lead_id, 'quotation_id' => $customer->quotation_id, 'proforma_invoice_id' => $proforma?->id,
                'onboarding_number' => $this->nextNumber($actor->company_id), 'title' => 'Implementation: '.($customer->company_name ?: $customer->display_name), 'status' => OnboardingStatus::NotStarted, 'priority' => 'normal',
                'assigned_to' => $customer->lead?->assigned_user_id ?? $actor->id, 'implementation_owner_id' => $data['implementation_owner_id'] ?? null, 'start_date' => today(), 'progress_percent' => 0,
                'customer_contact_name' => $contact?->name ?? $customer->display_name, 'customer_contact_phone' => $contact?->phone ?? $customer->phone, 'customer_contact_email' => $contact?->email ?? $customer->email,
                'business_name' => $customer->company_name, 'store_count' => $customer->number_of_stores, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ], Arr::only($data, ['title', 'priority', 'assigned_to', 'implementation_owner_id', 'start_date', 'target_go_live_date', 'customer_contact_name', 'customer_contact_phone', 'customer_contact_email', 'business_name', 'store_count', 'notes', 'internal_remarks'])));

            foreach ($this->template->defaultTasks() as $index => $task) {
                $onboarding->tasks()->create($task + ['status' => OnboardingTaskStatus::Pending, 'sort_order' => ($index + 1) * 10, 'is_required' => $task['is_required'] ?? true]);
            }

            $this->activity($onboarding, $actor, 'Onboarding started', 'Customer implementation checklist created.');
            $this->auditLogger->record('crm.onboarding.started', $onboarding, 'Customer onboarding started');
            $this->dispatch('crm.onboarding.started', $onboarding, $actor);

            return $onboarding->load('tasks');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CrmCustomerOnboarding $onboarding, User $actor, array $data): CrmCustomerOnboarding
    {
        $this->assertUsersBelongToCompany($actor, Arr::only($data, ['assigned_to', 'implementation_owner_id']));
        $beforeOwner = $onboarding->implementation_owner_id ?: $onboarding->assigned_to;
        $newStatus = isset($data['status']) ? OnboardingStatus::from($data['status']) : $onboarding->status;
        $onboarding->update(Arr::except(Arr::only($data, ['title', 'status', 'priority', 'assigned_to', 'implementation_owner_id', 'start_date', 'target_go_live_date', 'customer_contact_name', 'customer_contact_phone', 'customer_contact_email', 'business_name', 'store_count', 'notes', 'internal_remarks']), 'status') + ['updated_by' => $actor->id]);
        if ($newStatus !== $onboarding->status) {
            $this->setStatus($onboarding, $actor, $newStatus);
        }
        $this->auditLogger->record('crm.onboarding.updated', $onboarding, 'Customer onboarding updated');
        if (($onboarding->implementation_owner_id ?: $onboarding->assigned_to) !== $beforeOwner) { $this->dispatch('crm.onboarding.task_assigned', $onboarding, $actor); }
        return $onboarding->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateTask(CrmCustomerOnboarding $onboarding, CrmOnboardingTask $task, User $actor, array $data): CrmOnboardingTask
    {
        $this->assertUsersBelongToCompany($actor, Arr::only($data, ['assigned_to']));
        $previousAssignee = $task->assigned_to;
        $newStatus = OnboardingTaskStatus::from($data['status']);
        $this->ensureTaskTransition($task->status, $newStatus);
        $payload = Arr::only($data, ['title', 'description', 'category', 'assigned_to', 'due_date', 'is_required']);
        $payload['status'] = $newStatus;
        $payload['completed_at'] = $newStatus === OnboardingTaskStatus::Completed ? now() : null;
        $payload['completed_by'] = $newStatus === OnboardingTaskStatus::Completed ? $actor->id : null;
        $task->update($payload);

        $message = match ($newStatus) { OnboardingTaskStatus::Completed => 'Onboarding task completed: '.$task->title.'.', OnboardingTaskStatus::Blocked => 'Onboarding task blocked: '.$task->title.'.', OnboardingTaskStatus::Skipped => 'Onboarding task skipped: '.$task->title.'.', default => 'Onboarding task updated: '.$task->title.'.' };
        if ($newStatus === OnboardingTaskStatus::Blocked && filled($data['reason'] ?? null)) { $this->addNote($onboarding, $actor, ['note' => 'Blocked task: '.$task->title."\n".$data['reason'], 'visibility' => 'internal']); }
        $this->activity($onboarding, $actor, $message, $data['reason'] ?? null);
        $this->auditLogger->record('crm.onboarding.task.'.$newStatus->value, $task, $message);
        if ($newStatus === OnboardingTaskStatus::Blocked) {
            $this->dispatch('crm.onboarding.blocked', $onboarding, $actor);
        }
        if ($task->assigned_to !== $previousAssignee) {
            $this->dispatch('crm.onboarding.task_assigned', $onboarding, $actor);
        }
        $this->recalculateProgress($onboarding, $actor);
        return $task->refresh();
    }

    /** @param array<string, mixed> $data */
    public function addTask(CrmCustomerOnboarding $onboarding, User $actor, array $data): CrmOnboardingTask
    {
        $this->assertUsersBelongToCompany($actor, Arr::only($data, ['assigned_to']));
        $task = $onboarding->tasks()->create(Arr::only($data, ['task_key', 'title', 'description', 'category', 'assigned_to', 'due_date', 'is_required']) + ['status' => OnboardingTaskStatus::Pending, 'sort_order' => ((int) $onboarding->tasks()->max('sort_order')) + 10]);
        $this->activity($onboarding, $actor, 'Onboarding task added: '.$task->title.'.', null);
        $this->auditLogger->record('crm.onboarding.task.created', $task, 'Onboarding task added');
        return $task;
    }

    /** @param array<string, mixed> $data */
    public function addNote(CrmCustomerOnboarding $onboarding, User $actor, array $data): CrmOnboardingNote
    {
        $note = $onboarding->onboardingNotes()->create(['note' => $data['note'], 'visibility' => $data['visibility'], 'created_by' => $actor->id]);
        $this->activity($onboarding, $actor, 'Onboarding note added.', null);
        return $note;
    }

    /** @param array<string, mixed> $data */
    public function addDocument(CrmCustomerOnboarding $onboarding, User $actor, array $data): CrmOnboardingDocument
    {
        $document = $onboarding->documents()->create(Arr::only($data, ['document_type', 'title', 'external_url', 'notes', 'status']) + ['uploaded_by' => $actor->id]);
        $this->activity($onboarding, $actor, 'Document requested: '.$document->title.'.', null);
        $this->auditLogger->record('crm.onboarding.document.requested', $document, 'Onboarding document requested');
        return $document;
    }

    /** @param array<string, mixed> $data */
    public function updateDocument(CrmCustomerOnboarding $onboarding, CrmOnboardingDocument $document, User $actor, array $data): CrmOnboardingDocument
    {
        $document->update(Arr::only($data, ['document_type', 'title', 'external_url', 'notes', 'status']) + ['uploaded_by' => $actor->id]);
        $verb = $document->status === OnboardingDocumentStatus::Received ? 'received' : $document->status->value;
        $this->activity($onboarding, $actor, 'Document '.$verb.': '.$document->title.'.', null);
        $this->auditLogger->record('crm.onboarding.document.'.$verb, $document, 'Onboarding document '.$verb);
        return $document->refresh();
    }

    public function setStatus(CrmCustomerOnboarding $onboarding, User $actor, OnboardingStatus $status): CrmCustomerOnboarding
    {
        $this->ensureOnboardingTransition($onboarding->status, $status);
        $onboarding->update(['status' => $status, 'actual_go_live_date' => $status === OnboardingStatus::Live ? ($onboarding->actual_go_live_date ?: today()) : $onboarding->actual_go_live_date, 'updated_by' => $actor->id]);
        $event = match ($status) { OnboardingStatus::GoLiveReady => 'crm.onboarding.go_live_ready', OnboardingStatus::Live => 'crm.onboarding.live', OnboardingStatus::OnHold => 'crm.onboarding.on_hold', OnboardingStatus::Cancelled => 'crm.onboarding.cancelled', default => 'crm.onboarding.updated' };
        $this->activity($onboarding, $actor, 'Onboarding status changed to '.$status->label().'.', null);
        $this->auditLogger->record($event, $onboarding, 'Onboarding status changed to '.$status->label());
        $this->dispatch($event, $onboarding, $actor);
        return $onboarding->refresh();
    }

    public function recalculateProgress(CrmCustomerOnboarding $onboarding, User $actor): void
    {
        $required = $onboarding->tasks()->where('is_required', true);
        $total = (clone $required)->count();
        $completed = (clone $required)->where('status', OnboardingTaskStatus::Completed->value)->count();
        $progress = $total === 0 ? 0 : (int) round(($completed / $total) * 100);
        $onboarding->update(['progress_percent' => $progress, 'updated_by' => $actor->id]);
        if ($progress === 100 && $onboarding->status !== OnboardingStatus::Live && $onboarding->status !== OnboardingStatus::Cancelled && $onboarding->status !== OnboardingStatus::GoLiveReady) { $this->setStatus($onboarding, $actor, OnboardingStatus::GoLiveReady); }
    }

    private function nextNumber(int $companyId): string
    {
        $prefix = 'ONB-'.now()->format('Y').'-';
        $last = CrmCustomerOnboarding::query()->where('company_id', $companyId)->where('onboarding_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('onboarding_number');
        $sequence = $last ? ((int) str($last)->afterLast('-')->toString()) + 1 : 1;
        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function ensureTaskTransition(OnboardingTaskStatus $from, OnboardingTaskStatus $to): void
    {
        $allowed = [OnboardingTaskStatus::Pending->value => ['in_progress', 'blocked', 'skipped', 'completed'], OnboardingTaskStatus::InProgress->value => ['completed', 'blocked', 'skipped'], OnboardingTaskStatus::Blocked->value => ['in_progress'], OnboardingTaskStatus::Completed->value => [], OnboardingTaskStatus::Skipped->value => []];
        if ($from !== $to && ! in_array($to->value, $allowed[$from->value], true)) { throw ValidationException::withMessages(['status' => 'This task status transition is not allowed.']); }
    }

    private function ensureOnboardingTransition(OnboardingStatus $from, OnboardingStatus $to): void
    {
        if ($from === OnboardingStatus::Live || $from === OnboardingStatus::Cancelled) { throw ValidationException::withMessages(['status' => 'Completed or cancelled onboarding cannot be changed.']); }
    }

    private function activity(CrmCustomerOnboarding $onboarding, User $actor, string $subject, ?string $description): void
    {
        if (! $onboarding->lead_id) { return; }
        CrmActivity::create(['company_id' => $onboarding->company_id, 'crm_lead_id' => $onboarding->lead_id, 'assigned_user_id' => $onboarding->implementation_owner_id ?: $onboarding->assigned_to, 'created_by' => $actor->id, 'type' => ActivityType::Task, 'subject' => $subject, 'description' => $description, 'completed_at' => now(), 'priority' => LeadPriority::Medium]);
    }

    private function dispatch(string $eventKey, CrmCustomerOnboarding $onboarding, User $actor): void
    {
        $this->events->dispatch(new OnboardingEvent($eventKey, $onboarding->company_id, $actor->id, CrmCustomerOnboarding::class, $onboarding->id, ['onboarding_id' => $onboarding->id, 'onboarding_number' => $onboarding->onboarding_number, 'customer_name' => $onboarding->business_name ?: $onboarding->customer?->display_name, 'assigned_user_id' => $onboarding->assigned_to, 'implementation_owner_id' => $onboarding->implementation_owner_id, 'status' => $onboarding->status->value, 'target_go_live_date' => $onboarding->target_go_live_date?->toDateString()]));
    }

    /** @param array<string, mixed> $assignments */
    private function assertUsersBelongToCompany(User $actor, array $assignments): void
    {
        $ids = collect($assignments)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isNotEmpty() && User::query()->where('company_id', $actor->company_id)->whereIn('id', $ids)->count() !== $ids->count()) {
            throw ValidationException::withMessages(['assigned_to' => 'Assigned users must belong to this company.']);
        }
    }

    private function assertCustomerBelongsToCompany(CrmCustomer $customer, User $actor): void
    {
        if ($customer->company_id !== $actor->company_id) {
            throw ValidationException::withMessages(['customer' => 'The selected customer does not belong to this company.']);
        }
    }
}
