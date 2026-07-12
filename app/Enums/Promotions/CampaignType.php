<?php

namespace App\Enums\Promotions;

enum CampaignType: string
{
    case Seasonal = 'seasonal';
    case Festival = 'festival';
    case Clearance = 'clearance';
    case LoyaltyFuture = 'loyalty_future';
    case Channel = 'channel';
    case Branch = 'branch';
    case Manual = 'manual';
    case Other = 'other';
}
