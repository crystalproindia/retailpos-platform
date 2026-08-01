<?php

namespace App\Http\Requests\Tasks;

use App\Enums\Tasks\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tasks.update_own') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'completion_note' => ['nullable', 'string', 'max:5000'],
            'next_follow_up_at' => ['nullable', 'date', 'after:now'],
            'next_follow_up_title' => ['nullable', 'string', 'max:255', 'required_with:next_follow_up_at'],
        ];
    }
}
