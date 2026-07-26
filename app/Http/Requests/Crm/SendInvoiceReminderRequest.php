<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\InvoiceReminderStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInvoiceReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.reminders.send') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(InvoiceReminderStage::class), Rule::notIn([InvoiceReminderStage::Manual->value])],
            'attach_pdf' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (preg_match('/<[^>]+>|javascript:|data:text\/html/i', (string) $value)) {
                    $fail('The optional message must be plain text and cannot contain HTML or scripts.');
                }
            }],
        ];
    }
}
