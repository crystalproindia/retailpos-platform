<?php

namespace App\Support\Crm;

final readonly class LeadConversationAssessment
{
    /**
     * @param  array<int, int|null>  $ratings
     */
    private function __construct(
        public ?float $average,
        public string $qualification,
        public int $answeredCount,
        public array $ratings,
    ) {}

    public static function fromRatings(?int $clientReceptiveness, ?int $buyingInterest, ?int $followUpUrgency): self
    {
        $ratings = [$clientReceptiveness, $buyingInterest, $followUpUrgency];
        $answered = array_values(array_filter($ratings, static fn (?int $rating): bool => $rating !== null));

        if ($answered === []) {
            return new self(null, 'Not Rated', 0, $ratings);
        }

        $average = round(array_sum($answered) / count($answered), 1);

        return new self(
            $average,
            match (true) {
                $average >= 4.0 => 'Hot Lead',
                $average >= 2.5 => 'Warm Lead',
                default => 'Cold Lead',
            },
            count($answered),
            $ratings,
        );
    }
}
