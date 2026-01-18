<?php

namespace App\Services\MarketData;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\TickerOhlcDailyRepository;
use App\Repositories\MarketData\CanonicalEodRepository;

final class SoftQualityRulesService
{
    private $can;
    private $cal;
    private $ohlc;

    public function __construct(
        CanonicalEodRepository $can,
        MarketCalendarRepository $cal,
        TickerOhlcDailyRepository $ohlc
    ) {
        $this->can = $can;
        $this->cal = $cal;
        $this->ohlc = $ohlc;
    }

    /**
     * @return array{
     *   soft_flags:int,
     *   rule_counts:array<string,int>,
     *   hold:bool,
     *   hold_reason:?string,
     *   samples:array<string,string>  // rule => "sample text"
     * }
     */
    public function evaluate(
        int $runId,
        string $from,
        string $to,
        int $expectedTickers,
        float $gapExtremePct = 0.25
    ): array {
        $softFlags = 0;
        $counts = [
            'ohlc_inconsistent' => 0,
            'volume_missing' => 0,
            'gap_extreme' => 0,
            'flatline_with_volume' => 0,
            'price_out_of_bounds' => 0,
        ];
        $samples = [];

        $hold = false;
        $holdReason = null;

        $dates = $this->cal->tradingDatesBetween($from, $to);
        if (!$dates) {
            return [
                'soft_flags' => 0,
                'rule_counts' => $counts,
                'hold' => false,
                'hold_reason' => null,
                'samples' => [],
            ];
        }

        foreach ($dates as $d) {
            // Pull canonical rows for the day (need a repo method for this)
            $rows = $this->can->listByRunAndDate($runId, $d); // you add this method (select minimal cols)

            if (!$rows) continue;

            // prev context
            $prev = $this->cal->previousTradingDate($d);
            $prevMap = [];
            if ($prev) {
                $tickerIds = array_map(function($r){ return (int)$r->ticker_id; }, $rows);
                $prevMap = $this->ohlc->mapPrevCloseVolume($prev, $tickerIds);
            }

            $missingVolDay = 0;
            $gapExtremeDay = 0;

            foreach ($rows as $r) {
                $tid = (int) $r->ticker_id;

                $o = $r->open  !== null ? (float) $r->open  : null;
                $h = $r->high  !== null ? (float) $r->high  : null;
                $l = $r->low   !== null ? (float) $r->low   : null;
                $c = $r->close !== null ? (float) $r->close : null;
                $v = $r->volume!== null ? (int) $r->volume : null;

                // Rule 1: OHLC inconsistent (critical)
                if ($h !== null && $l !== null && $h < $l) {
                    $counts['ohlc_inconsistent']++; $softFlags++;
                    if (!isset($samples['ohlc_inconsistent'])) $samples['ohlc_inconsistent'] = "{$tid}@{$d}: high<low";
                    $hold = true; $holdReason = 'ohlc_inconsistent';
                    continue;
                }
                if (($o !== null && $o < 0) || ($h !== null && $h < 0) || ($l !== null && $l < 0) || ($c !== null && $c < 0)) {
                    $counts['ohlc_inconsistent']++; $softFlags++;
                    if (!isset($samples['ohlc_inconsistent'])) $samples['ohlc_inconsistent'] = "{$tid}@{$d}: negative_price";
                    $hold = true; $holdReason = 'ohlc_inconsistent';
                    continue;
                }
                if ($h !== null && $c !== null && $c > $h) {
                    $counts['ohlc_inconsistent']++; $softFlags++;
                    if (!isset($samples['ohlc_inconsistent'])) $samples['ohlc_inconsistent'] = "{$tid}@{$d}: close>high";
                    $hold = true; $holdReason = 'ohlc_inconsistent';
                    continue;
                }
                if ($l !== null && $c !== null && $c < $l) {
                    $counts['ohlc_inconsistent']++; $softFlags++;
                    if (!isset($samples['ohlc_inconsistent'])) $samples['ohlc_inconsistent'] = "{$tid}@{$d}: close<low";
                    $hold = true; $holdReason = 'ohlc_inconsistent';
                    continue;
                }

                // Rule 2: volume missing
                if (($o !== null || $h !== null || $l !== null || $c !== null) && ($v === null || $v === 0)) {
                    $counts['volume_missing']++; $softFlags++;
                    $missingVolDay++;
                    if (!isset($samples['volume_missing'])) $samples['volume_missing'] = "{$tid}@{$d}: volume_null_or_zero";
                }

                // Rule 3: gap extreme vs prev close
                $prevClose = $prevMap[$tid]['close'] ?? null;
                if ($prevClose !== null && $prevClose > 0 && $c !== null) {
                    $gap = abs($c - $prevClose) / $prevClose;
                    if ($gap >= $gapExtremePct) {
                        $counts['gap_extreme']++; $softFlags++;
                        $gapExtremeDay++;
                        if (!isset($samples['gap_extreme'])) $samples['gap_extreme'] = "{$tid}@{$d}: gap=" . round($gap*100,2) . "%";
                    }
                }

                // Rule 4: flatline with volume
                if ($o !== null && $h !== null && $l !== null && $c !== null && $o == $h && $h == $l && $l == $c && $v !== null && $v > 0) {
                    $counts['flatline_with_volume']++; $softFlags++;
                    if (!isset($samples['flatline_with_volume'])) $samples['flatline_with_volume'] = "{$tid}@{$d}: flatline vol={$v}";
                }

                // Rule 5: price out of bounds
                if ($c !== null && ($c < 10 || $c > 500000)) {
                    $counts['price_out_of_bounds']++; $softFlags++;
                    if (!isset($samples['price_out_of_bounds'])) $samples['price_out_of_bounds'] = "{$tid}@{$d}: close={$c}";
                }
            }

            // Soft HOLD heuristics (mass issues)
            if (!$hold && $expectedTickers > 0) {
                if ($missingVolDay / $expectedTickers >= 0.30) {
                    $hold = true; $holdReason = "volume_missing_mass@{$d}";
                } elseif ($gapExtremeDay / $expectedTickers >= 0.05) {
                    $hold = true; $holdReason = "gap_extreme_mass@{$d}";
                }
            }

            if ($hold && $holdReason) break;
        }

        return [
            'soft_flags' => $softFlags,
            'rule_counts' => $counts,
            'hold' => $hold,
            'hold_reason' => $holdReason,
            'samples' => $samples,
        ];
    }
}
