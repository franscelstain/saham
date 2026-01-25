<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Scorecard candidate DTO.
 * PHP 7.3 compatible.
 */
class CandidateDto
{
    /** @var string */
    public $ticker;
    /** @var bool */
    public $hasPosition;
    /** @var int */
    public $score;
    /** @var int */
    public $rank;
    /** @var int|null */
    public $entryTrigger;
    /** @var EntryBandDto */
    public $entryBand;
    /** @var CandidateGuardsDto */
    public $guards;
    /** @var CandidateTimingDto */
    public $timing;
    /** @var int */
    public $slices;
    /** @var float[] */
    public $slicePct;
    /** @var string[] */
    public $reasonCodes;

    /**
     * @param string $ticker
     * @param bool $hasPosition
     * @param int $score
     * @param int $rank
     * @param int|null $entryTrigger
     * @param EntryBandDto $entryBand
     * @param CandidateGuardsDto $guards
     * @param CandidateTimingDto $timing
     * @param int $slices
     * @param float[] $slicePct
     * @param string[] $reasonCodes
     */
    public function __construct($ticker, $hasPosition, $score, $rank, $entryTrigger, EntryBandDto $entryBand, CandidateGuardsDto $guards, CandidateTimingDto $timing, $slices, array $slicePct, array $reasonCodes)
    {
        $this->ticker = (string)$ticker;
        $this->hasPosition = (bool)$hasPosition;
        $this->score = (int)$score;
        $this->rank = (int)$rank;
        $this->entryTrigger = ($entryTrigger === null) ? null : (int)$entryTrigger;
        $this->entryBand = $entryBand;
        $this->guards = $guards;
        $this->timing = $timing;
        $this->slices = (int)$slices;
        $this->slicePct = array_values($slicePct);
        $this->reasonCodes = array_values($reasonCodes);
    }

    /**
     * @param array<string,mixed> $a
     * @param CandidateGuardsDto|null $guardsFallback
     * @param int $fallbackRank
     * @return self
     */
    public static function fromArray(array $a, $guardsFallback = null, $fallbackRank = 0)
    {
        $ticker = strtoupper(trim((string)($a['ticker'] ?? ($a['ticker_code'] ?? ''))));

        $pos = (is_array($a['position'] ?? null)) ? $a['position'] : [];
        $levels = (is_array($a['levels'] ?? null)) ? $a['levels'] : [];
        $timingArr = (is_array($a['timing'] ?? null)) ? $a['timing'] : [];
        $guardsArr = (is_array($a['guards'] ?? null)) ? $a['guards'] : [];
        $bandArr = (is_array($a['entry_band'] ?? null)) ? $a['entry_band'] : [];

        $entryTrigger = $a['entry_trigger'] ?? ($levels['entry_trigger_price'] ?? null);
        $entryTrigger = is_numeric($entryTrigger) ? (int)$entryTrigger : null;

        $low = $bandArr['low'] ?? ($a['entry_limit_low'] ?? ($levels['entry_limit_low'] ?? null));
        $high = $bandArr['high'] ?? ($a['entry_limit_high'] ?? ($levels['entry_limit_high'] ?? null));
        $band = EntryBandDto::fromArray(['low' => $low, 'high' => $high]);

        $sizing = (is_array($a['sizing'] ?? null)) ? $a['sizing'] : [];
        $slices = (int)($a['slices'] ?? ($sizing['slices'] ?? 1));
        if ($slices < 1) $slices = 1;

        $slicePct = $a['slice_pct'] ?? null;
        if (!is_array($slicePct)) {
            $scalar = $sizing['slice_pct'] ?? null;
            $scalar = is_numeric($scalar) ? (float)$scalar : null;

            if ($slices === 1) {
                $slicePct = [1.0];
            } else {
                $each = 1.0 / (float)$slices;
                $slicePct = array_fill(0, $slices, $each);
                // Backward-compat: 2 slices where sizing.slice_pct is first-slice fraction.
                if ($scalar !== null && $slices === 2 && $scalar > 0 && $scalar < 1.0) {
                    $slicePct = [$scalar, 1.0 - $scalar];
                }
            }
        }

        $guards = CandidateGuardsDto::fromArray($guardsArr, ($guardsFallback instanceof CandidateGuardsDto) ? $guardsFallback : null);
        // Backward compat: max chase can be stored in levels.
        if (isset($levels['max_chase_from_close_pct']) && is_numeric($levels['max_chase_from_close_pct']) && !isset($guardsArr['max_chase_pct'])) {
            $guards = new CandidateGuardsDto((float)$levels['max_chase_from_close_pct'], $guards->gapUpBlockPct, $guards->spreadMaxPct);
        }

        $timing = CandidateTimingDto::fromArray($timingArr);

        $rank = (int)($a['rank'] ?? 0);
        if ($rank <= 0) $rank = ($fallbackRank > 0) ? (int)$fallbackRank : 0;

        $score = (int)round((float)($a['score'] ?? ($a['watchlist_score'] ?? 0)));
        $reasonCodes = (isset($a['reason_codes']) && is_array($a['reason_codes'])) ? array_values($a['reason_codes']) : [];

        $hasPos = (bool)($a['has_position'] ?? ($pos['has_position'] ?? false));

        $slicePct = array_map('floatval', array_values($slicePct));
        $reasonCodes = array_map('strval', $reasonCodes);

        return new self($ticker, $hasPos, $score, $rank, $entryTrigger, $band, $guards, $timing, $slices, $slicePct, $reasonCodes);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        return [
            'ticker' => $this->ticker,
            'has_position' => (bool)$this->hasPosition,
            'score' => (int)$this->score,
            'rank' => (int)$this->rank,
            'entry_trigger' => $this->entryTrigger,
            'entry_band' => $this->entryBand->toArray(),
            'guards' => $this->guards->toArray(),
            'timing' => $this->timing->toArray(),
            'slices' => (int)$this->slices,
            'slice_pct' => array_values($this->slicePct),
            'reason_codes' => array_values($this->reasonCodes),
        ];
    }
}
