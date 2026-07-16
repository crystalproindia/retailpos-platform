<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendProformaEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.proformas.send');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to_email' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:255'],
            'message_body' => ['required', 'string', 'max:10000'],
            'attach_pdf' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->ccRecipients() as $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('cc', 'Each CC address must be a valid email address.');

                    return;
                }
            }
        }];
    }

    /** @return array<int, string> */
    public function ccRecipients(): array
    {
        return collect(preg_split('/[;,]+/', (string) $this->input('cc'), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email): string => trim($email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
