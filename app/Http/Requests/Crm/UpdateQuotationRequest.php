<?php

namespace App\Http\Requests\Crm;

class UpdateQuotationRequest extends StoreQuotationRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.quotations.update');
    }
}
