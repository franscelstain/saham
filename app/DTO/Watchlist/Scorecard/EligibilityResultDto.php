<?php

namespace App\DTO\Watchlist\Scorecard;

class EligibilityResultDto
{
    /** @param string[] $flags @param string[] $reasons */
    public function __construct(
        public string $ticker,
        public bool $eligibleNow,
        public array $flags,
        public ?float $gapPct,
        public ?float $spreadPct,
        public ?float $chasePct,
        public array $reasons,
        public string $notes,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'ticker' => $this->ticker,
            'eligible_now' => $this->eligibleNow,
            'flags' => array_values($this->flags),
            'computed' => [
                'gap_pct' => $this->gapPct,
                'spread_pct' => $this->spreadPct,
                'chase_pct' => $this->chasePct,
            ],
            'reasons' => array_values($this->reasons),
            'notes' => $this->notes,
        ];
    }
}
