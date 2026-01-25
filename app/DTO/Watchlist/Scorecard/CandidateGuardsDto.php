<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Candidate guards / thresholds.
 * PHP 7.3 compatible (no typed properties / constructor promotion).
 */
class CandidateGuardsDto
{
    /** @var float */
    public $maxChasePct;
    /** @var float */
    public $gapUpBlockPct;
    /** @var float */
    public $spreadMaxPct;

    public function __construct($maxChasePct, $gapUpBlockPct, $spreadMaxPct)
    {
        $this->maxChasePct = (float)$maxChasePct;
        $this->gapUpBlockPct = (float)$gapUpBlockPct;
        $this->spreadMaxPct = (float)$spreadMaxPct;
    }

    /**
     * @param array<string,mixed> $a
     * @param self|null $fallback
     * @return self
     */
    public static function fromArray(array $a, $fallback = null)
    {
        $fb = $fallback instanceof self ? $fallback : new self(0.01, 0.015, 0.004);
        $mc = (isset($a['max_chase_pct']) && is_numeric($a['max_chase_pct'])) ? (float)$a['max_chase_pct'] : $fb->maxChasePct;
        $gap = (isset($a['gap_up_block_pct']) && is_numeric($a['gap_up_block_pct'])) ? (float)$a['gap_up_block_pct'] : $fb->gapUpBlockPct;
        $spr = (isset($a['spread_max_pct']) && is_numeric($a['spread_max_pct'])) ? (float)$a['spread_max_pct'] : $fb->spreadMaxPct;
        return new self($mc, $gap, $spr);
    }

    /**
     * @return array{max_chase_pct:float,gap_up_block_pct:float,spread_max_pct:float}
     */
    public function toArray()
    {
        return [
            'max_chase_pct' => (float)$this->maxChasePct,
            'gap_up_block_pct' => (float)$this->gapUpBlockPct,
            'spread_max_pct' => (float)$this->spreadMaxPct,
        ];
    }
}
