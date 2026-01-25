<?php

namespace App\DTO\Watchlist\Scorecard;

class ScorecardMetricsDto
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public ?float $feasibleRate,
        public ?float $fillRate,
        public ?float $outcomeRate,
        public array $payload,
    ) {
    }
}
