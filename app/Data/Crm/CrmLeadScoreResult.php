<?php

namespace App\Data\Crm;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadScoreCategory;
use App\Enums\Crm\LeadScoreConfidence;

readonly class CrmLeadScoreResult
{
    /**
     * @param  array<int, string>  $reasons
     * @param  array<int, string>  $risks
     * @param  array<int, string>  $opportunities
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $score,
        public LeadScoreCategory $category,
        public LeadScoreConfidence $confidence,
        public LeadPriority $priority,
        public string $nextBestAction,
        public array $reasons,
        public array $risks,
        public array $opportunities,
        public array $metadata,
    ) {}
}
