<?php

namespace App\DTO\Watchlist\Scorecard;

use App\DTO\BaseDto;

/**
 * Scorecard candidate DTO.
 *
 * Phase 2A (PHP 7.4):
 * - Keep legacy array payload compatibility.
 * - Enforce immutability by making fields private.
 * - Preserve read-only "$dto->field" access via BaseDto::__get.
 */
class CandidateDto extends BaseDto
{
    private string $ticker;
    private bool $hasPosition;
    private int $score;
    private int $rank;
    private ?int $entryTrigger;
    private EntryBandDto $entryBand;
    private CandidateTimingDto $timing;
    private int $slices;
    /** @var float[] */
    private array $slicePct;
    /** @var string[] */
    private array $reasonCodes;

    // --- strict CONFIRM plan fields ---
    private string $setupType;
    private ?float $stopPrice;
    private ?float $tp1Price;
    private ?float $rrEst;
    /** @var array<int,array<string,mixed>> */
    private array $executionSlices;

    /**
     * @param string $ticker
     * @param bool $hasPosition
     * @param int $score
     * @param int $rank
     * @param int|null $entryTrigger
     * @param EntryBandDto $entryBand
     * @param CandidateTimingDto $timing
     * @param int $slices
     * @param float[] $slicePct
     * @param string[] $reasonCodes
     * @param string $setupType
     * @param float|null $stopPrice
     * @param float|null $tp1Price
     * @param float|null $rrEst
     * @param array<int,array<string,mixed>> $executionSlices
     */
    public function __construct(
        $ticker,
        $hasPosition,
        $score,
        $rank,
        $entryTrigger,
        EntryBandDto $entryBand,
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
     * @param int $fallbackRank
     * @return self
     */
    public static function fromArray(array $a, $fallbackRank = 0)
    {
        $ticker = strtoupper(trim((string)($a['ticker'] ?? ($a['ticker_code'] ?? ''))));

        $pos = (is_array($a['position'] ?? null)) ? $a['position'] : [];
        $levels = (is_array($a['levels'] ?? null)) ? $a['levels'] : [];
        $timingArr = (is_array($a['timing'] ?? null)) ? $a['timing'] : [];
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
    /**
     * Phase 2B.2: forbid legacy property access (use getters).
     */
    public function __get($name)
    {
        throw new \LogicException('CandidateDto is immutable and does not expose public properties. Use getters like ticker(), score(), rank(), entryTrigger(), executionSlices(), etc.');
    }

    public function __isset($name): bool
    {
        return false;
    }

    public function ticker(): string { return $this->ticker; }
    public function hasPosition(): bool { return (bool)$this->hasPosition; }
    public function score(): int { return (int)$this->score; }
    public function rank(): int { return (int)$this->rank; }
    public function entryTrigger(): ?int { return $this->entryTrigger; }
    public function entryBand(): EntryBandDto { return $this->entryBand; }
    public function timing(): CandidateTimingDto { return $this->timing; }
    public function slices(): int { return (int)$this->slices; }
    public function slicePct(): array { return array_values($this->slicePct); }
    public function reasonCodes(): array { return array_values($this->reasonCodes); }

    public function setupType(): string { return $this->setupType; }
    public function stopPrice(): ?float { return $this->stopPrice; }
    public function tp1Price(): ?float { return $this->tp1Price; }
    public function rrEst(): ?float { return $this->rrEst; }
    public function executionSlices(): array { return array_values($this->executionSlices); }

    public function toArray(): array
    {
        $a = [
            'ticker' => $this->ticker,
            'has_position' => (bool)$this->hasPosition,
            'score' => (int)$this->score,
            'rank' => (int)$this->rank,
            'entry_trigger' => $this->entryTrigger,
            'entry_band' => $this->entryBand->toArray(),
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
