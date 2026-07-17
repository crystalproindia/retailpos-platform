<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmOnboardingDocumentRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('crm.onboarding.manage_documents'); }
    public function rules(): array { return ['document_type' => ['required', Rule::in(['business_details','product_master','customer_list','supplier_list','barcode_list','logo','gst_certificate','training_material','other'])], 'title' => ['required', 'string', 'max:255'], 'external_url' => ['nullable', 'url', 'max:1000'], 'status' => ['required', 'in:requested,received,verified,rejected'], 'notes' => ['nullable', 'string', 'max:5000']]; }
}
