<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\InvoiceReminderStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvoiceReminderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.reminders.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'automatic_enabled' => ['nullable', 'boolean'],
            'minimum_cooldown_hours' => ['required', 'integer', 'between:1,168'],
            'rules' => ['required', 'array', 'size:5'],
            'rules.*.stage' => ['required', Rule::enum(InvoiceReminderStage::class)],
            'rules.*.enabled' => ['nullable', 'boolean'],
            'rules.*.offset_days' => ['required', 'integer', 'between:-90,180'],
            'rules.*.attach_pdf' => ['nullable', 'boolean'],
            'rules.*.include_secure_link' => ['nullable', 'boolean'],
            'rules.*.subject' => ['required', 'string', 'max:180', $this->plainTextRule()],
            'rules.*.intro_message' => ['required', 'string', 'max:4000', $this->plainTextRule()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rules = collect($this->input('rules', []));
            $stages = $rules->pluck('stage');
            $expected = collect(InvoiceReminderStage::cases())->filter->isAutomatic()->pluck('value')->sort()->values();

            if ($stages->unique()->count() !== $stages->count() || $stages->sort()->values()->all() !== $expected->all()) {
                $validator->errors()->add('rules', 'Each automatic reminder stage must be configured exactly once.');
            }

            $enabledOffsets = $rules->filter(fn (array $rule): bool => filter_var($rule['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN))->pluck('offset_days');
            if ($enabledOffsets->unique()->count() !== $enabledOffsets->count()) {
                $validator->errors()->add('rules', 'Enabled reminder stages cannot share the same timing.');
            }
        });
    }

    /** @return \Closure(string, mixed, \Closure): void */
    private function plainTextRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (preg_match('/<[^>]+>|javascript:|data:text\/html/i', (string) $value)) {
                $fail('Reminder content must be plain text and cannot contain HTML or scripts.');
            }
        };
    }
}
