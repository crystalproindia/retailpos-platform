<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $logo = [
            'nullable',
            'file',
            File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb'),
            'dimensions:max_width=5000,max_height=5000',
        ];
        $signature = [
            'nullable',
            'file',
            File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('1mb'),
            'dimensions:max_width=3000,max_height=1500',
        ];

        return [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'max:80'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'company_logo' => $logo,
            'invoice_logo' => $logo,
            'authorized_signature' => $signature,
            'authorized_signatory_name' => ['nullable', 'string', 'max:120'],
            'authorized_signatory_designation' => ['nullable', 'string', 'max:120'],
            'remove_company_logo' => ['nullable', 'boolean'],
            'remove_invoice_logo' => ['nullable', 'boolean'],
            'remove_authorized_signature' => ['nullable', 'boolean'],
        ];
    }
}
