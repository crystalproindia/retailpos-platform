<?php

namespace App\Enums\Crm;

enum OnboardingTaskCategory: string
{
    case BusinessDetails = 'business_details';
    case StoreSetup = 'store_setup';
    case DataCollection = 'data_collection';
    case UserSetup = 'user_setup';
    case Training = 'training';
    case GoLive = 'go_live';
    case Documentation = 'documentation';
    case Custom = 'custom';

    public function label(): string { return str($this->value)->headline()->toString(); }
}
