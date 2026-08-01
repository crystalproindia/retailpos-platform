<?php

namespace App\Http\Requests\Tasks;

use App\Enums\Tasks\TaskPriority;
use App\Enums\Tasks\TaskRecurrenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tasks.update_own') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'due_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date', 'before_or_equal:due_at'],
            'outlet_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'recurrence_type' => ['nullable', Rule::enum(TaskRecurrenceType::class)],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365', 'required_if:recurrence_type,interval'],
            'cancel_series' => ['nullable', 'boolean'],
        ];
    }
}
