<?php

namespace App\Services\Tasks;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Tasks\TaskPriority;
use App\Enums\Tasks\TaskSourceType;
use App\Enums\Tasks\TaskStatus;
use App\Enums\Tasks\TaskType;
use App\Models\Branch;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Tasks\Task;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private readonly TaskAccessService $access,
        private readonly TaskRelatedRecordRegistry $records,
        private readonly TaskReminderService $reminders,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Task
    {
        $type = TaskType::from($data['task_type']);
        if (! empty($data['recurrence_type'])) {
            abort_unless($actor->can('tasks.manage_recurring'), 403);
        }
        if ($type === TaskType::Work) {
            $this->access->assertCanCreateWork($actor);
        }
        if ($type === TaskType::Personal && (! empty($data['related_type']) || ! empty($data['related_id']))) {
            throw ValidationException::withMessages(['related_type' => 'Personal tasks cannot be linked to company records.']);
        }

        return DB::transaction(function () use ($actor, $data, $type): Task {
            $related = $this->relatedRecord($actor, $data);
            $outletId = $type === TaskType::Personal ? null : ($related ? $this->records->outletId($related) : $this->validatedOutlet($actor, $data['outlet_id'] ?? null));

            if ($related) {
                $this->access->assertCanLinkRecord($actor, $related, $outletId);
            }

            $assignee = $type === TaskType::Personal
                ? $actor
                : $this->requestedAssignee($actor, $outletId, $data);

            $task = Task::create([
                'company_id' => $actor->company_id,
                'outlet_id' => $outletId,
                'owner_user_id' => $actor->id,
                'assigned_user_id' => $assignee?->id,
                'assigned_employee_id' => $assignee?->workforce_employee_id,
                'created_by_user_id' => $actor->id,
                'task_type' => $type,
                'source_type' => TaskSourceType::Manual,
                'related_type' => $related?->getMorphClass(),
                'related_id' => $related?->getKey(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? TaskPriority::Normal->value,
                'status' => TaskStatus::Todo,
                'due_at' => $data['due_at'] ?? null,
                'reminder_at' => $data['reminder_at'] ?? null,
                'recurrence_type' => $data['recurrence_type'] ?? null,
                'recurrence_interval' => $data['recurrence_interval'] ?? null,
                'recurrence_series_id' => ($data['recurrence_type'] ?? null) ? (string) Str::uuid() : null,
                'metadata' => ! empty($data['reason']) ? ['reason' => str((string) $data['reason'])->limit(500)->toString()] : null,
            ]);

            $this->record($task, 'created');
            if ($type === TaskType::Work && $assignee && $assignee->id !== $actor->id) {
                $this->reminders->assignment($task, $assignee);
            }

            return $task;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Task $task, User $actor, array $data): Task
    {
        $this->access->assertCanManage($actor, $task);
        if (! empty($data['recurrence_type']) || ! empty($data['cancel_series'])) {
            abort_unless($actor->can('tasks.manage_recurring'), 403);
        }

        return DB::transaction(function () use ($task, $actor, $data): Task {
            $beforeAssignee = $task->assigned_user_id;
            $outletId = $task->task_type === TaskType::Personal ? null : $this->validatedOutlet($actor, $data['outlet_id'] ?? $task->outlet_id);
            $changes = collect($data)->only(['title', 'description', 'priority', 'due_at', 'reminder_at', 'recurrence_type', 'recurrence_interval'])->all();

            if ($task->task_type === TaskType::Work && array_key_exists('assigned_user_id', $data)) {
                $requestedAssigneeId = filled($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null;
                if ($task->assigned_user_id !== $requestedAssigneeId) {
                    abort_unless($actor->can('tasks.reassign'), 403);
                }
                $assignee = $requestedAssigneeId ? $this->assignee($actor, $outletId, $requestedAssigneeId) : null;
                $changes['assigned_user_id'] = $assignee?->id;
                $changes['assigned_employee_id'] = $assignee?->workforce_employee_id;
            }
            if ($task->task_type === TaskType::Work) {
                $changes['outlet_id'] = $outletId;
            }
            if (($data['cancel_series'] ?? false) && $task->recurrence_series_id) {
                Task::query()->where('recurrence_series_id', $task->recurrence_series_id)->whereNull('completed_at')->update(['recurrence_cancelled_at' => now()]);
            }

            if (array_key_exists('recurrence_type', $data) && $data['recurrence_type'] && ! $task->recurrence_series_id) {
                $changes['recurrence_series_id'] = (string) Str::uuid();
            }

            $task->fill($changes)->save();
            $this->record($task, $beforeAssignee !== $task->assigned_user_id ? 'reassigned' : 'updated');

            if ($beforeAssignee !== $task->assigned_user_id) {
                $task->load('assignee');
                if ($task->assignee) {
                    $this->reminders->assignment($task, $task->assignee);
                }
            }

            return $task->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function transition(Task $task, User $actor, array $data): Task
    {
        $this->access->assertCanManage($actor, $task);
        $next = TaskStatus::from($data['status']);
        if ($next === TaskStatus::Todo && in_array($task->status, [TaskStatus::Completed, TaskStatus::Cancelled], true)) {
            abort_unless($actor->can('tasks.reopen'), 403);
        }
        if (! $task->status->canTransitionTo($next)) {
            throw ValidationException::withMessages(['status' => 'That task status change is not allowed.']);
        }

        return DB::transaction(function () use ($task, $actor, $data, $next): Task {
            $previousStatus = $task->status;
            $startedNow = $next === TaskStatus::InProgress && ! $task->started_at;
            $attributes = ['status' => $next];
            if ($next === TaskStatus::Completed) {
                $attributes += [
                    'completed_at' => now(),
                    'completed_by_user_id' => $actor->id,
                    'completion_note' => $data['completion_note'] ?? null,
                ];
            } elseif ($next === TaskStatus::Cancelled) {
                $attributes['cancelled_at'] = now();
            } elseif ($startedNow) {
                $attributes['started_at'] = now();
            } elseif ($next === TaskStatus::Todo) {
                $attributes += ['completed_at' => null, 'completed_by_user_id' => null, 'completion_note' => null, 'cancelled_at' => null];
            }

            $task->update($attributes);
            $action = match (true) {
                $next === TaskStatus::Completed => 'completed',
                $startedNow => 'started',
                $next === TaskStatus::Cancelled => 'cancelled',
                $next === TaskStatus::Todo && in_array($previousStatus, [TaskStatus::Completed, TaskStatus::Cancelled], true) => 'reopened',
                default => 'status_changed',
            };
            $this->record($task, $action);

            if ($next === TaskStatus::Completed) {
                $this->recordLeadHistory($task, $actor);
                $this->createNextOccurrence($task, $actor);
                if (! empty($data['next_follow_up_at']) && $task->related instanceof CrmLead) {
                    $this->create($actor, [
                        'task_type' => TaskType::Work->value,
                        'title' => $data['next_follow_up_title'],
                        'priority' => $task->priority->value,
                        'due_at' => $data['next_follow_up_at'],
                        'outlet_id' => $task->outlet_id,
                        'assigned_user_id' => $task->assigned_user_id,
                        'related_type' => 'lead',
                        'related_id' => $task->related_id,
                        'reason' => 'Explicit next follow-up after task completion.',
                    ]);
                }
            }

            return $task->refresh();
        });
    }

    public function archive(Task $task, User $actor): void
    {
        $this->access->assertCanManage($actor, $task);
        $task->update(['archived_at' => now()]);
        $this->record($task, 'archived');
    }

    /** @param array<string, mixed> $attributes */
    public function createRuleTask(int $companyId, User $assignee, Model $related, string $ruleKey, string $title, string $reason, \DateTimeInterface $dueAt, array $attributes = []): ?Task
    {
        $recordKey = $this->records->keyFor($related);
        if (! $recordKey || ! $this->access->canReceiveSystemTask($assignee, $companyId, $this->records->outletId($related))) {
            return null;
        }

        $outletId = $this->records->outletId($related);
        // V1 rules do not permit repeat cycles, so a stable key prevents a late
        // record from creating another task every time the scheduler runs.
        $idempotencyKey = 'task-rule:'.$ruleKey.':'.$recordKey.':'.$related->getKey();

        return DB::transaction(function () use ($companyId, $assignee, $related, $ruleKey, $title, $reason, $dueAt, $attributes, $outletId, $idempotencyKey): ?Task {
            $existing = Task::query()->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if (! $existing->auditLogs()->where('event', 'tasks.work.rule_skipped')->exists()) {
                    $this->record($existing, 'rule_skipped');
                }

                return null;
            }

            $task = Task::create([
                'company_id' => $companyId,
                'outlet_id' => $outletId,
                'owner_user_id' => $assignee->id,
                'assigned_user_id' => $assignee->id,
                'assigned_employee_id' => $assignee->workforce_employee_id,
                'created_by_user_id' => $assignee->id,
                'task_type' => TaskType::Work,
                'source_type' => TaskSourceType::SystemRule,
                'related_type' => $related->getMorphClass(),
                'related_id' => $related->getKey(),
                'title' => $title,
                'description' => $reason,
                'priority' => $attributes['priority'] ?? TaskPriority::High,
                'status' => TaskStatus::Todo,
                'due_at' => $dueAt,
                'system_rule_key' => $ruleKey,
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['reason' => $reason, 'generated_at' => now()->toISOString()],
            ]);
            $this->record($task, 'rule_generated');
            $this->reminders->assignment($task, $assignee);

            return $task;
        });
    }

    /** @param array<string, mixed> $data */
    private function relatedRecord(User $actor, array $data): ?Model
    {
        if (empty($data['related_type']) && empty($data['related_id'])) {
            return null;
        }

        if (! $this->records->supports($data['related_type'] ?? null)) {
            throw ValidationException::withMessages(['related_type' => 'Choose a supported record type.']);
        }

        $record = $this->records->findForUser($actor, $data['related_type'], (int) $data['related_id']);
        if (! $record) {
            throw ValidationException::withMessages(['related_id' => 'That record is unavailable.']);
        }

        return $record;
    }

    private function validatedOutlet(User $actor, mixed $outletId): ?int
    {
        if (! $outletId) {
            return null;
        }

        $outlet = Branch::query()->where('company_id', $actor->company_id)->find((int) $outletId);
        if (! $outlet) {
            throw ValidationException::withMessages(['outlet_id' => 'That outlet is unavailable.']);
        }

        $this->access->assertCanLinkRecord($actor, $outlet, $outlet->id);

        return $outlet->id;
    }

    /** @param array<string, mixed> $data */
    private function requestedAssignee(User $actor, ?int $outletId, array $data): ?User
    {
        if (! array_key_exists('assigned_user_id', $data)) {
            return $actor;
        }

        if (! filled($data['assigned_user_id'])) {
            abort_unless($actor->can('tasks.assign'), 403);

            return null;
        }

        return $this->assignee($actor, $outletId, $data['assigned_user_id']);
    }

    private function assignee(User $actor, ?int $outletId, mixed $userId): User
    {
        $assignee = User::query()->where('company_id', $actor->company_id)->findOrFail((int) $userId);
        if ($assignee->id !== $actor->id) {
            $this->access->assertCanAssign($actor, $outletId, $assignee);
        }

        return $assignee;
    }

    private function recordLeadHistory(Task $task, User $actor): void
    {
        if (! $task->related instanceof CrmLead || $task->task_type !== TaskType::Work) {
            return;
        }

        CrmActivity::create([
            'company_id' => $task->company_id,
            'crm_lead_id' => $task->related->id,
            'assigned_user_id' => $task->assigned_user_id,
            'created_by' => $actor->id,
            'type' => ActivityType::Note,
            'subject' => 'Task completed',
            'description' => 'A linked work task was completed.',
            'scheduled_at' => now(),
            'completed_at' => now(),
            'completed_by' => $actor->id,
            'follow_up_status' => 'completed',
            'priority' => $task->priority === TaskPriority::Urgent ? LeadPriority::Urgent : LeadPriority::Medium,
        ]);
    }

    private function createNextOccurrence(Task $task, User $actor): void
    {
        if (! $task->recurrence_type || ! $task->recurrence_series_id || $task->recurrence_cancelled_at || ! $task->due_at) {
            return;
        }

        $dueAt = match ($task->recurrence_type->value) {
            'daily' => $task->due_at->copy()->addDay(),
            'weekly' => $task->due_at->copy()->addWeek(),
            'monthly' => $task->due_at->copy()->addMonthNoOverflow(),
            'interval' => $task->due_at->copy()->addDays($task->recurrence_interval ?: 1),
        };
        $idempotency = 'task-recurrence:'.$task->recurrence_series_id.':'.$dueAt->format('YmdHi');
        if (Task::query()->where('company_id', $task->company_id)->where('idempotency_key', $idempotency)->exists()) {
            return;
        }

        $next = Task::create([
            'company_id' => $task->company_id,
            'outlet_id' => $task->outlet_id,
            'owner_user_id' => $task->owner_user_id,
            'assigned_user_id' => $task->assigned_user_id,
            'assigned_employee_id' => $task->assigned_employee_id,
            'created_by_user_id' => $actor->id,
            'task_type' => $task->task_type,
            'source_type' => $task->source_type,
            'related_type' => $task->related_type,
            'related_id' => $task->related_id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => TaskStatus::Todo,
            'due_at' => $dueAt,
            'reminder_at' => null,
            'recurrence_type' => $task->recurrence_type,
            'recurrence_interval' => $task->recurrence_interval,
            'recurrence_parent_id' => $task->id,
            'recurrence_series_id' => $task->recurrence_series_id,
            'idempotency_key' => $idempotency,
            'metadata' => ['reason' => 'Recurring task occurrence.'],
        ]);
        $this->record($next, 'recurrence_generated');
    }

    private function record(Task $task, string $action): void
    {
        $personal = $task->task_type === TaskType::Personal;
        $this->audit->record(
            $personal ? 'tasks.personal.'.$action : 'tasks.work.'.$action,
            $task,
            $personal ? 'Personal task activity recorded' : 'Work task '.$action,
            array_filter([
                'company_id' => $task->company_id,
                'task_type' => $task->task_type->value,
                'status' => $task->status->value,
                'outlet_id' => $personal ? null : $task->outlet_id,
                'assigned_user_id' => $personal ? null : $task->assigned_user_id,
                'related_type' => $personal ? null : $task->related_type,
                'related_id' => $personal ? null : $task->related_id,
                'system_rule_key' => $personal ? null : $task->system_rule_key,
            ], fn ($value) => $value !== null),
        );
    }
}
