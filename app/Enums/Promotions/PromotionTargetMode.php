<?php

namespace App\Enums\Promotions;

enum PromotionTargetMode: string
{
    case Include = 'include';
    case Exclude = 'exclude';
}
