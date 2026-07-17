<?php

namespace App\Enums\Crm;

enum SupportTicketCategory: string
{
    case General = 'general'; case Billing = 'billing'; case Training = 'training'; case Setup = 'setup'; case Bug = 'bug'; case FeatureRequest = 'feature_request'; case DataImport = 'data_import'; case Hardware = 'hardware'; case Integration = 'integration'; case AccountAccess = 'account_access'; case Other = 'other';
    public function label(): string { return str($this->value)->replace('_', ' ')->headline()->toString(); }
}
