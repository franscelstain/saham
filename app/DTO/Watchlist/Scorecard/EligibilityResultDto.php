<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Eligibility result per ticker.
 * PHP 7.3 compatible.
 */
class EligibilityResultDto
{
    /** @var string */
    public $ticker;
    /** @var bool */
    public $eligibleNow;
    /** @var string[] */
    public $flags;
    /** @var float|null */
    public $gapPct;
    /** @var float|null */
    public $spreadPct;
    /** @var float|null */
    public $chasePct;
    /** @var string[] */
    public $reasons;
    /** @var string */
    public $notes;

    /**
     * @param string $ticker
     * @param bool $eligibleNow
     * @param string[] $flags
     * @param float|null $gapPct
     * @param float|null $spreadPct
     * @param float|null $chasePct
     * @param string[] $reasons
     * @param string $notes
     */
    public function __construct($ticker, $eligibleNow, array $flags, $gapPct, $spreadPct, $chasePct, array $reasons, $notes)
    {
        $this->ticker = (string)$ticker;
        $this->eligibleNow = (bool)$eligibleNow;
        $this->flags = array_values($flags);
        $this->gapPct = ($gapPct === null) ? null : (float)$gapPct;
        $this->spreadPct = ($spreadPct === null) ? null : (float)$spreadPct;
        $this->chasePct = ($chasePct === null) ? null : (float)$chasePct;
        $this->reasons = array_values($reasons);
        $this->notes = (string)$notes;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        return [
            'ticker' => $this->ticker,
            'eligible_now' => (bool)$this->eligibleNow,
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
