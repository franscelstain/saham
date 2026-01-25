<?php

namespace App\Trade\Watchlist\Scorecard;

use App\DTO\Watchlist\Scorecard\EligibilityCheckDto;
use App\DTO\Watchlist\Scorecard\ScorecardMetricsDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;

/**
 * Pure calculator (no DB, no config(), no time).
 *
 * SRP_Performa.md: domain compute must be side-effect free.
 */
class ScorecardMetricsCalculator
{
    /**
     * @param array<string,array{low:?float,high:?float}> $ohlcByTicker
     */
    public function compute(StrategyRunDto $run, ?EligibilityCheckDto $latestCheck, array $ohlcByTicker): ScorecardMetricsDto
    {
        $feasible = null;
        $fill = null;
        $outcome = null;

        // Feasible rate from latest check (eligible_now true / evaluated)
        if ($latestCheck && count($latestCheck->results) > 0) {
            $eligible = 0;
            $eval = 0;
            foreach ($latestCheck->results as $r) {
                $eval++;
                if ($r->eligibleNow) $eligible++;
            }
            if ($eval > 0) $feasible = $eligible / $eval;
        }

        // Fill rate: use EOD high/low on exec_date and compare against entry levels.
        $candidates = array_merge($run->topPicks, $run->secondary);
        $filled = 0;
        $totalSlices = 0;
        $sliceDetails = [];

        foreach ($candidates as $cand) {
            $t = $cand->ticker;
            if ($t === '' || empty($ohlcByTicker[$t])) continue;

            $day = $ohlcByTicker[$t];
            $lo = $day['low'] ?? null;
            $hi = $day['high'] ?? null;
            if ($lo === null || $hi === null) continue;

            $prices = $this->deriveSlicePrices($cand);
            if (empty($prices)) continue;

            $hit = 0;
            foreach ($prices as $px) {
                $totalSlices++;
                if ($px >= $lo && $px <= $hi) {
                    $filled++;
                    $hit++;
                }
            }

            $sliceDetails[] = [
                'ticker' => $t,
                'slices' => $cand->slices,
                'slice_prices' => $prices,
                'day_low' => $lo,
                'day_high' => $hi,
                'filled_slices' => $hit,
            ];
        }

        if ($totalSlices > 0) $fill = $filled / $totalSlices;

        $payload = [
            'policy' => $run->policy,
            'exec_trade_date' => $run->execDate,
            'slice_details' => $sliceDetails,
        ];

        return new ScorecardMetricsDto($feasible, $fill, $outcome, $payload);
    }

    /**
     * Derive slice prices.
     * - Prefer entry_band.low/high when both exist.
     * - Otherwise fall back to entry_trigger.
     *
     * @return float[]
     */
    private function deriveSlicePrices($cand): array
    {
        $entry = $cand->entryTrigger !== null ? (float)$cand->entryTrigger : null;
        $low = $cand->entryBand->low !== null ? (float)$cand->entryBand->low : null;
        $high = $cand->entryBand->high !== null ? (float)$cand->entryBand->high : null;

        $slices = $cand->slices;
        if ($slices <= 1) {
            return $entry !== null ? [$entry] : [];
        }

        if ($low !== null && $high !== null && $high >= $low) {
            if ($slices === 2) return [$low, $high];
            $step = ($high - $low) / (float)($slices - 1);
            $out = [];
            for ($i = 0; $i < $slices; $i++) {
                $out[] = $low + ($i * $step);
            }
            return $out;
        }

        return $entry !== null ? [$entry] : [];
    }
}
