<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class PublicLeadIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:50', 'required_without:email'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'requirement' => ['nullable', 'string', 'max:10000'],
            'source' => ['required', 'string', Rule::in(['contact', 'book_demo', 'pricing_enquiry', 'landing_page'])],
            'page_url' => ['nullable', 'url', 'max:2048'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array', 'max:20'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if (filled($this->input('website'))) {
            throw new HttpResponseException(response()->json([
                'success' => true,
                'message' => 'Lead received successfully.',
            ]));
        }

        parent::failedValidation($validator);
    }
}
