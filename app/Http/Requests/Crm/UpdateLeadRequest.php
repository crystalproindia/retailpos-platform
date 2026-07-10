<?php

namespace App\Http\Requests\Crm;

class UpdateLeadRequest extends StoreLeadRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.leads.update');
    }
}
