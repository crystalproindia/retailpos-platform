<?php

namespace App\Http\Requests\Tasks;

use App\Enums\Tasks\TaskPriority;
use App\Enums\Tasks\TaskRecurrenceType;
use App\Enums\Tasks\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tasks.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'task_type' => ['required', Rule::enum(TaskType::class)],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'due_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date', 'before_or_equal:due_at'],
            'outlet_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'related_type' => ['nullable', 'string', 'max:80'],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
            'recurrence_type' => ['nullable', Rule::enum(TaskRecurrenceType::class)],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365', 'required_if:recurrence_type,interval'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
