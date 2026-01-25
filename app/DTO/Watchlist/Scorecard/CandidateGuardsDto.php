<?php

namespace App\DTO\Watchlist\Scorecard;

class CandidateGuardsDto
{
    public function __construct(
        public float $maxChasePct,
        public float $gapUpBlockPct,
        public float $spreadMaxPct,
    ) {
    }

    /**
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a, ?self $fallback = null): self
    {
        $fb = $fallback ?? new self(0.01, 0.015, 0.004);
        $mc = isset($a['max_chase_pct']) && is_numeric($a['max_chase_pct']) ? (float)$a['max_chase_pct'] : $fb->maxChasePct;
        $gap = isset($a['gap_up_block_pct']) && is_numeric($a['gap_up_block_pct']) ? (float)$a['gap_up_block_pct'] : $fb->gapUpBlockPct;
        $spr = isset($a['spread_max_pct']) && is_numeric($a['spread_max_pct']) ? (float)$a['spread_max_pct'] : $fb->spreadMaxPct;
        return new self($mc, $gap, $spr);
    }

    /**
     * @return array{max_chase_pct:float,gap_up_block_pct:float,spread_max_pct:float}
     */
    public function toArray(): array
    {
        return [
            'max_chase_pct' => $this->maxChasePct,
            'gap_up_block_pct' => $this->gapUpBlockPct,
            'spread_max_pct' => $this->spreadMaxPct,
        ];
    }
}
