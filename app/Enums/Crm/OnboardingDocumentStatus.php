<?php

namespace App\Enums\Crm;

enum OnboardingDocumentStatus: string
{
    case Requested = 'requested';
    case Received = 'received';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string { return str($this->value)->headline()->toString(); }
}
