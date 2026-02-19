<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Scorecard candidate DTO.
 * Keeps backward compatibility with legacy watchlist payloads, but can also carry strict CONFIRM plan fields.
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

    // --- strict CONFIRM plan fields ---
    /** @var string */
    public $setupType;
    /** @var float|null */
    public $stopPrice;
    /** @var float|null */
    public $tp1Price;
    /** @var float|null */
    public $rrEst;
    /** @var array<int,array<string,mixed>> */
    public $executionSlices;

    public function __construct(
        $ticker,
        $hasPosition,
        $score,
        $rank,
        $entryTrigger,
        EntryBandDto $entryBand,
        CandidateGuardsDto $guards,
        CandidateTimingDto $timing,
        $slices,
        array $slicePct,
        array $reasonCodes,
        $setupType = '',
        $stopPrice = null,
        $tp1Price = null,
        $rrEst = null,
        array $executionSlices = []
    ) {
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

        $this->setupType = strtoupper(trim((string)$setupType));
        $this->stopPrice = ($stopPrice === null) ? null : (float)$stopPrice;
        $this->tp1Price = ($tp1Price === null) ? null : (float)$tp1Price;
        $this->rrEst = ($rrEst === null) ? null : (float)$rrEst;
        $this->executionSlices = array_values($executionSlices);
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

        // strict payload may wrap plan under ticker_plan
        $tickerPlan = (is_array($a['ticker_plan'] ?? null)) ? $a['ticker_plan'] : [];
        $planArr = (is_array($a['plan'] ?? null)) ? $a['plan'] : (is_array($tickerPlan['plan'] ?? null) ? $tickerPlan['plan'] : []);

        $entryTrigger = $a['entry_trigger'] ?? ($planArr['entry'] ?? ($levels['entry_trigger_price'] ?? null));
        $entryTrigger = is_numeric($entryTrigger) ? (int)$entryTrigger : null;

        $low = $bandArr['low'] ?? ($a['entry_limit_low'] ?? ($levels['entry_limit_low'] ?? null));
        $high = $bandArr['high'] ?? ($a['entry_limit_high'] ?? ($levels['entry_limit_high'] ?? null));
        // strict can also provide entry band inside plan
        if ($low === null && isset($planArr['entry_band']['low'])) $low = $planArr['entry_band']['low'];
        if ($high === null && isset($planArr['entry_band']['high'])) $high = $planArr['entry_band']['high'];

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

        $setupType = (string)($a['setup_type'] ?? ($planArr['setup_type'] ?? ($tickerPlan['setup_type'] ?? '')));
        $stop = isset($planArr['stop']) && is_numeric($planArr['stop']) ? (float)$planArr['stop'] : null;
        $tp1 = isset($planArr['tp1']) && is_numeric($planArr['tp1']) ? (float)$planArr['tp1'] : null;
        $rr = isset($planArr['rr_est']) && is_numeric($planArr['rr_est']) ? (float)$planArr['rr_est'] : null;

        $execSlices = $a['execution_slices'] ?? ($tickerPlan['execution_slices'] ?? null);
        if (!is_array($execSlices)) $execSlices = [];

        // Normalize execution_slices objects
        $normSlices = [];
        foreach ($execSlices as $s) {
            if (!is_array($s)) continue;
            $tranche = isset($s['tranche']) ? (int)$s['tranche'] : (count($normSlices) + 1);
            $lots = isset($s['lots']) && is_numeric($s['lots']) ? (int)$s['lots'] : null;
            $pl = isset($s['plan_limit_price']) && is_numeric($s['plan_limit_price']) ? (float)$s['plan_limit_price'] : null;
            $cap = isset($s['plan_price_cap']) && is_numeric($s['plan_price_cap']) ? (float)$s['plan_price_cap'] : null;
            $normSlices[] = [
                'tranche' => $tranche,
                'lots' => $lots,
                'plan_limit_price' => $pl,
                'plan_price_cap' => $cap,
            ];
        }

        $slicePct = array_map('floatval', array_values($slicePct));
        $reasonCodes = array_map('strval', $reasonCodes);

        return new self(
            $ticker,
            $hasPos,
            $score,
            $rank,
            $entryTrigger,
            $band,
            $guards,
            $timing,
            $slices,
            $slicePct,
            $reasonCodes,
            $setupType,
            $stop,
            $tp1,
            $rr,
            $normSlices
        );
    }

    
    /**
     * Return a copy with execution_slices replaced.
     * Keeps DTO immutable in practice (no mutation after construction).
     *
     * @param array<int,array<string,mixed>> $executionSlices
     * @return self
     */
    public function withExecutionSlices(array $executionSlices)
    {
        return new self(
            $this->ticker,
            $this->hasPosition,
            $this->score,
            $this->rank,
            $this->entryTrigger,
            $this->entryBand,
            $this->guards,
            $this->timing,
            $this->slices,
            $this->slicePct,
            $this->reasonCodes,
            $this->setupType,
            $this->stopPrice,
            $this->tp1Price,
            $this->rrEst,
            $executionSlices
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        $a = [
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

        if ($this->setupType !== '' || $this->stopPrice !== null || $this->tp1Price !== null || $this->rrEst !== null) {
            $a['plan'] = [
                'setup_type' => $this->setupType !== '' ? $this->setupType : null,
                'entry' => $this->entryTrigger,
                'stop' => $this->stopPrice,
                'tp1' => $this->tp1Price,
                'rr_est' => $this->rrEst,
                'entry_band' => $this->entryBand->toArray(),
            ];
        }

        if (!empty($this->executionSlices)) {
            $a['execution_slices'] = array_values($this->executionSlices);
        }

        return $a;
    }
}
