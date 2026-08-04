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
            'remove_company_logo' => ['nullable', 'boolean'],
            'remove_invoice_logo' => ['nullable', 'boolean'],
        ];
    }
}
