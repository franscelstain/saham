<?php

namespace App\DTO\Watchlist\Scorecard;

class CandidateDto
{
    /** @param float[] $slicePct @param string[] $reasonCodes */
    public function __construct(
        public string $ticker,
        public bool $hasPosition,
        public int $score,
        public int $rank,
        public ?int $entryTrigger,
        public EntryBandDto $entryBand,
        public CandidateGuardsDto $guards,
        public CandidateTimingDto $timing,
        public int $slices,
        public array $slicePct,
        public array $reasonCodes,
    ) {
    }

    /**
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a, ?CandidateGuardsDto $guardsFallback = null, int $fallbackRank = 0): self
    {
        $ticker = strtoupper(trim((string)($a['ticker'] ?? ($a['ticker_code'] ?? ''))));
        $pos = is_array($a['position'] ?? null) ? $a['position'] : [];
        $levels = is_array($a['levels'] ?? null) ? $a['levels'] : [];
        $timingArr = is_array($a['timing'] ?? null) ? $a['timing'] : [];
        $guardsArr = is_array($a['guards'] ?? null) ? $a['guards'] : [];
        $bandArr = is_array($a['entry_band'] ?? null) ? $a['entry_band'] : [];

        $entryTrigger = $a['entry_trigger'] ?? ($levels['entry_trigger_price'] ?? null);
        $entryTrigger = is_numeric($entryTrigger) ? (int)$entryTrigger : null;

        $low = $bandArr['low'] ?? ($a['entry_limit_low'] ?? ($levels['entry_limit_low'] ?? null));
        $high = $bandArr['high'] ?? ($a['entry_limit_high'] ?? ($levels['entry_limit_high'] ?? null));
        $band = EntryBandDto::fromArray(['low' => $low, 'high' => $high]);

        $sizing = is_array($a['sizing'] ?? null) ? $a['sizing'] : [];
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
                if ($scalar !== null && $slices === 2 && $scalar > 0 && $scalar < 1.0) {
                    $slicePct = [$scalar, 1.0 - $scalar];
                }
            }
        }

        $guards = CandidateGuardsDto::fromArray($guardsArr, $guardsFallback);
        // Backward compat: max chase can be stored in levels.
        if (isset($levels['max_chase_from_close_pct']) && is_numeric($levels['max_chase_from_close_pct']) && !isset($guardsArr['max_chase_pct'])) {
            $guards = new CandidateGuardsDto((float)$levels['max_chase_from_close_pct'], $guards->gapUpBlockPct, $guards->spreadMaxPct);
        }

        $timing = CandidateTimingDto::fromArray($timingArr);

        $rank = (int)($a['rank'] ?? 0);
        if ($rank <= 0) $rank = $fallbackRank > 0 ? $fallbackRank : 0;

        $score = (int)round((float)($a['score'] ?? ($a['watchlist_score'] ?? 0)));

        $reasonCodes = isset($a['reason_codes']) && is_array($a['reason_codes']) ? array_values($a['reason_codes']) : [];

        return new self(
            $ticker,
            (bool)($a['has_position'] ?? ($pos['has_position'] ?? false)),
            $score,
            $rank,
            $entryTrigger,
            $band,
            $guards,
            $timing,
            $slices,
            array_map('floatval', array_values($slicePct)),
            array_map('strval', $reasonCodes),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'ticker' => $this->ticker,
            'has_position' => $this->hasPosition,
            'score' => $this->score,
            'rank' => $this->rank,
            'entry_trigger' => $this->entryTrigger,
            'entry_band' => $this->entryBand->toArray(),
            'guards' => $this->guards->toArray(),
            'timing' => $this->timing->toArray(),
            'slices' => $this->slices,
            'slice_pct' => array_values($this->slicePct),
            'reason_codes' => array_values($this->reasonCodes),
        ];
    }
}
