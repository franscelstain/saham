<?php

namespace App\DTO\Watchlist\Scorecard;

class StrategyCheckDto
{
    public function __construct(
        public int $checkId,
        public string $checkedAt,
        public array $snapshot,
        public array $result,
    ) {
    }
}
