<?php

namespace App\Models\Tasks;

use App\Enums\Tasks\TaskPriority;
use App\Enums\Tasks\TaskRecurrenceType;
use App\Enums\Tasks\TaskSourceType;
use App\Enums\Tasks\TaskStatus;
use App\Enums\Tasks\TaskType;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkforceEmployee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'outlet_id', 'owner_user_id', 'assigned_user_id', 'assigned_employee_id',
    'created_by_user_id', 'completed_by_user_id', 'task_type', 'source_type', 'related_type',
    'related_id', 'title', 'description', 'priority', 'status', 'due_at', 'reminder_at',
    'started_at', 'completed_at', 'cancelled_at', 'completion_note', 'recurrence_type',
    'recurrence_interval', 'recurrence_parent_id', 'recurrence_series_id', 'recurrence_cancelled_at',
    'system_rule_key', 'idempotency_key', 'reminder_delivery_state', 'metadata', 'archived_at',
])]
class Task extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'source_type' => TaskSourceType::class,
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'recurrence_type' => TaskRecurrenceType::class,
            'due_at' => 'datetime',
            'reminder_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'recurrence_cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Branch::class, 'outlet_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function assignedEmployee(): BelongsTo { return $this->belongsTo(WorkforceEmployee::class, 'assigned_employee_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by_user_id'); }
    public function related(): MorphTo { return $this->morphTo(); }
    public function recurrenceParent(): BelongsTo { return $this->belongsTo(self::class, 'recurrence_parent_id'); }
    public function recurrenceChildren(): HasMany { return $this->hasMany(self::class, 'recurrence_parent_id')->orderBy('due_at'); }
    public function reminderDeliveries(): HasMany { return $this->hasMany(TaskReminderDelivery::class); }
    public function auditLogs(): MorphMany { return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at'); }

    public function isOpen(): bool
    {
        return $this->status->isOpen() && $this->archived_at === null;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }
}
