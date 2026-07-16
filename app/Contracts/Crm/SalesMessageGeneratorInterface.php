<?php

namespace App\Contracts\Crm;

use App\Data\Crm\CrmLeadScoreResult;
use App\Data\Crm\GeneratedSalesMessage;
use App\Models\Crm\CrmLead;

interface SalesMessageGeneratorInterface
{
    /** @param array<string, string> $options */
    public function generate(CrmLead $lead, CrmLeadScoreResult $score, array $options): GeneratedSalesMessage;
}
