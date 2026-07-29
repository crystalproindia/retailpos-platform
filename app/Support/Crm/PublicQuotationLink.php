<?php

namespace App\Support\Crm;

use App\Models\Crm\CrmQuotation;

final readonly class PublicQuotationLink
{
    public function __construct(
        public CrmQuotation $quotation,
        public string $url,
    ) {}
}
