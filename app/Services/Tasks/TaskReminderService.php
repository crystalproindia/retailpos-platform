<?php

namespace App\Services\Tasks;

use App\Enums\Tasks\TaskType;
use App\Models\NotificationPreference;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskReminderDelivery;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\AuditLogger;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaskReminderService
{
    public function __construct(private readonly EmailDeliveryService $email, private readonly AuditLogger $audit) {}

    public function assignment(Task $task, User $recipient): void
    {
        $this->deliver($task, $recipient, 'assignment');
    }

    public function dueSoon(Task $task, User $recipient): void
    {
        $this->deliver($task, $recipient, 'due_soon');
    }

    public function dueNow(Task $task, User $recipient): void
    {
        $this->deliver($task, $recipient, 'due_now');
    }

    public function overdue(Task $task, User $recipient): void
    {
        $this->deliver($task, $recipient, 'overdue');
    }

    public function deliverDueReminders(?int $companyId = null, int $limit = 250, bool $dryRun = false): array
    {
        $tasks = Task::query()
            ->whereIn('status', ['todo', 'in_progress', 'waiting'])
            ->whereNotNull('assigned_user_id')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where(function ($query): void {
                $query->where(fn ($due) => $due->whereNotNull('reminder_at')->where('reminder_at', '<=', now()))
                    ->orWhere(fn ($due) => $due->whereNotNull('due_at')->where('due_at', '<=', now()));
            })
            ->with('assignee')
            ->orderBy('reminder_at')
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        $counts = ['examined' => $tasks->count(), 'due_soon' => 0, 'due_now' => 0, 'overdue' => 0];
        foreach ($tasks as $task) {
            if (! $task->assignee || ! $task->assignee->is_active) {
                continue;
            }

            $kind = $task->due_at?->isPast() ? 'overdue' : ($task->due_at?->isToday() ? 'due_now' : 'due_soon');
            $counts[$kind]++;
            if (! $dryRun) {
                $this->deliver($task, $task->assignee, $kind);
            }
        }

        return $counts;
    }

    private function deliver(Task $task, User $recipient, string $kind): void
    {
        if ($task->company_id !== $recipient->company_id || ($task->task_type === TaskType::Personal && $task->owner_user_id !== $recipient->id)) {
            return;
        }

        $preference = NotificationPreference::query()
            ->where('company_id', $recipient->company_id)
            ->where('user_id', $recipient->id)
            ->where('event_key', 'tasks.'.$kind)
            ->first();

        if (! $preference || $preference->database_enabled) {
            $this->database($task, $recipient, $kind);
        }
        if ($preference?->email_enabled && $recipient->email) {
            $this->email($task, $recipient, $kind);
        }
    }

    private function database(Task $task, User $recipient, string $kind): void
    {
        $delivery = $this->delivery($task, $recipient, 'database', $kind);
        if ($delivery->status === 'sent') {
            return;
        }

        try {
            $copy = $this->copy($task, $kind);
            $recipient->notify(new PlatformNotification(
                channel: 'database',
                eventKey: 'tasks.'.$kind,
                title: $copy['title'],
                message: $copy['message'],
                actionUrl: route('tasks.show', $task),
                severity: $kind === 'overdue' ? 'warning' : 'info',
                icon: 'activity',
                aggregateType: $task->getMorphClass(),
                aggregateId: $task->id,
            ));
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'failure_code' => null]);
            $this->recordDelivery($task, $recipient, $kind, 'database', 'sent');
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'failure_code' => 'notification_failed']);
            $this->recordDelivery($task, $recipient, $kind, 'database', 'failed');
            Log::warning('Task in-app reminder delivery failed.', ['task_id' => $task->id, 'delivery_id' => $delivery->id]);
        }
    }

    private function email(Task $task, User $recipient, string $kind): void
    {
        $delivery = $this->delivery($task, $recipient, 'email', $kind);
        if ($delivery->status === 'sent') {
            return;
        }

        try {
            $copy = $this->copy($task, $kind);
            $emailDelivery = $this->email->queue(
                companyId: $task->company_id,
                recipient: $recipient->email,
                subject: $copy['title'],
                templateKey: 'task_reminder',
                payload: [
                    'heading' => $copy['title'],
                    'greeting' => 'Hello '.$recipient->name.',',
                    'message' => $copy['message'],
                    'action_url' => route('tasks.show', $task),
                    'action_label' => 'Open task',
                ],
                related: $task,
                createdBy: $task->creator,
                idempotencyKey: 'task-reminder:'.$task->id.':'.$recipient->id.':'.$kind,
                recipientName: $recipient->name,
                reminderStage: $kind,
                reminderSource: 'task',
            );
            $delivery->update(['status' => in_array($emailDelivery->status, ['queued', 'sent', 'delivered'], true) ? 'sent' : 'skipped', 'sent_at' => now(), 'failure_code' => null]);
            $this->recordDelivery($task, $recipient, $kind, 'email', $delivery->status);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'failure_code' => 'email_queue_failed']);
            $this->recordDelivery($task, $recipient, $kind, 'email', 'failed');
            Log::warning('Task email reminder could not be queued.', ['task_id' => $task->id, 'delivery_id' => $delivery->id]);
        }
    }

    private function delivery(Task $task, User $recipient, string $channel, string $kind): TaskReminderDelivery
    {
        return TaskReminderDelivery::query()->firstOrCreate(
            ['task_id' => $task->id, 'user_id' => $recipient->id, 'channel' => $channel, 'kind' => $kind],
            ['status' => 'pending', 'idempotency_key' => 'task-reminder:'.$task->id.':'.$recipient->id.':'.$channel.':'.$kind],
        );
    }

    /** @return array{title: string, message: string} */
    private function copy(Task $task, string $kind): array
    {
        $personal = $task->task_type === TaskType::Personal;
        $kindLabel = match ($kind) {
            'assignment' => 'assigned',
            'due_soon' => 'due soon',
            'due_now' => 'due today',
            default => 'overdue',
        };

        return [
            'title' => $personal ? 'Personal task reminder' : 'Work task update',
            'message' => $personal
                ? 'You have a personal task '.$kindLabel.'.'
                : 'You have a work task '.$kindLabel.'.',
        ];
    }

    private function recordDelivery(Task $task, User $recipient, string $kind, string $channel, string $status): void
    {
        $personal = $task->task_type === TaskType::Personal;
        $this->audit->record(
            $personal ? 'tasks.personal.reminder_'.$status : 'tasks.work.reminder_'.$status,
            $task,
            $personal ? 'Personal task reminder activity recorded' : 'Work task reminder '.$status,
            array_filter([
                'company_id' => $task->company_id,
                'task_type' => $task->task_type->value,
                'channel' => $channel,
                'kind' => $kind,
                'recipient_user_id' => $personal ? null : $recipient->id,
            ], fn ($value) => $value !== null),
        );
    }
}
