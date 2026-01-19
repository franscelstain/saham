<?php

namespace App\Services\MarketData;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\TickerOhlcDailyRepository;
use App\Repositories\MarketData\CanonicalEodRepository;
use App\Trade\Support\TradeClock;

/**
 * Phase 4 (soft rules): flag common anomalies without stopping the pipeline by default.
 * Can optionally HOLD the run if the anomaly is severe/massive.
 */
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
     *   samples:array<string,string>
     * }
     */
    public function evaluate(
        int $runId,
        string $from,
        string $to,
        int $expectedTickers,
        float $gapExtremePct = 0.25
    ): array {
        $today = TradeClock::today();

        $softFlags = 0;
        $counts = [
            // Mandatory per MANIFEST
            'future_date' => 0,
            'gap_extreme' => 0,
            'stale_bar' => 0,
            'flat_bar' => 0,
            'flat_repeat' => 0,

            // Keep useful safety flags (still soft unless massive / corrupt)
            'ohlc_inconsistent' => 0,
            'volume_missing' => 0,
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
            // Hard-ish rule (requested): future trade_date should never happen.
            if ($d > $today) {
                $counts['future_date']++; $softFlags++;
                if (!isset($samples['future_date'])) $samples['future_date'] = "date={$d} > today={$today}";
                $hold = true;
                $holdReason = 'future_date';
                break;
            }

            // Canonical rows for that day
            $rows = $this->can->listByRunAndDate($runId, $d);
            if (!$rows) continue;

            // prev context (from published table)
            $prev = $this->cal->previousTradingDate($d);
            $prevPrev = $prev ? $this->cal->previousTradingDate($prev) : null;

            $tickerIds = array_map(function ($r) { return (int) $r->ticker_id; }, $rows);
            // perf: load prev+prevPrev in one query instead of two
            $ctx = $this->ohlc->mapCloseVolumeByDates(array_values(array_filter([$prev, $prevPrev])), $tickerIds);
            $prevMap = $prev ? ($ctx[$prev] ?? []) : [];
            $prevPrevMap = $prevPrev ? ($ctx[$prevPrev] ?? []) : [];

            $gapExtremeDay = 0;
            $staleDay = 0;
            $flatRepeatDay = 0;

            foreach ($rows as $r) {
                $tid = (int) $r->ticker_id;

                $o = $r->open  !== null ? (float) $r->open  : null;
                $h = $r->high  !== null ? (float) $r->high  : null;
                $l = $r->low   !== null ? (float) $r->low   : null;
                $c = $r->close !== null ? (float) $r->close : null;
                $v = $r->volume !== null ? (int) $r->volume : null;

                // OHLC inconsistent (corrupt) => HOLD immediately.
                if ($h !== null && $l !== null && $h < $l) {
                    $counts['ohlc_inconsistent']++; $softFlags++;
                    if (!isset($samples['ohlc_inconsistent'])) $samples['ohlc_inconsistent'] = "{$tid}@{$d}: high<low";
                    $hold = true; $holdReason = 'ohlc_inconsistent';
                    break;
                }
                if (($o !== null && $o < 0) || ($h !== null && $h < 0) || ($l !== null && $l < 0) || ($c !== null && $c < 0)) {
                    $counts['ohlc_inconsistent']++; $softFlags++;
                    if (!isset($samples['ohlc_inconsistent'])) $samples['ohlc_inconsistent'] = "{$tid}@{$d}: negative_price";
                    $hold = true; $holdReason = 'ohlc_inconsistent';
                    break;
                }
                if ($h !== null && $c !== null && $c > $h) {
                    $counts['ohlc_inconsistent']++; $softFlags++;
                    if (!isset($samples['ohlc_inconsistent'])) $samples['ohlc_inconsistent'] = "{$tid}@{$d}: close>high";
                    $hold = true; $holdReason = 'ohlc_inconsistent';
                    break;
                }
                if ($l !== null && $c !== null && $c < $l) {
                    $counts['ohlc_inconsistent']++; $softFlags++;
                    if (!isset($samples['ohlc_inconsistent'])) $samples['ohlc_inconsistent'] = "{$tid}@{$d}: close<low";
                    $hold = true; $holdReason = 'ohlc_inconsistent';
                    break;
                }

                // volume missing (soft)
                if (($o !== null || $h !== null || $l !== null || $c !== null) && ($v === null || $v === 0)) {
                    $counts['volume_missing']++; $softFlags++;
                    if (!isset($samples['volume_missing'])) $samples['volume_missing'] = "{$tid}@{$d}: volume_null_or_zero";
                }

                // Rule: gap extreme vs previous close
                $prevClose = $prevMap[$tid]['close'] ?? null;
                if ($prevClose !== null && $prevClose > 0 && $c !== null) {
                    $gap = abs($c - $prevClose) / $prevClose;
                    if ($gap >= $gapExtremePct) {
                        $counts['gap_extreme']++; $softFlags++;
                        $gapExtremeDay++;
                        if (!isset($samples['gap_extreme'])) $samples['gap_extreme'] = "{$tid}@{$d}: gap=" . round($gap * 100, 2) . '%';
                    }
                }

                // Rule: stale bar (close + volume same as previous trading day)
                // NOTE:
                // - Do NOT count volume==0 as "stale". Many IDX tickers legitimately have no trades
                //   (volume 0) and will repeat last close across days; that's not a feed-stuck signal.
                $prevVol = $prevMap[$tid]['volume'] ?? null;
                if ($prevClose !== null && $prevVol !== null && $c !== null && $v !== null) {
                    if ($v > 0 && $prevVol > 0 && $c == $prevClose && $v == $prevVol) {
                        $counts['stale_bar']++; $softFlags++;
                        $staleDay++;
                        if (!isset($samples['stale_bar'])) $samples['stale_bar'] = "{$tid}@{$d}: same close+vol as {$prev}";
                    }
                }

                // Rule: flat bar
                $isFlat = ($o !== null && $h !== null && $l !== null && $c !== null && $o == $h && $h == $l && $l == $c);
                if ($isFlat) {
                    $counts['flat_bar']++; $softFlags++;
                    if (!isset($samples['flat_bar'])) $samples['flat_bar'] = "{$tid}@{$d}: flat";

                    // Repeat heuristic: flat today AND (close == prev close) AND prevPrev close == prev close
                    // -> indicates "berkep" (flat streak).
                    $ppClose = $prevPrevMap[$tid]['close'] ?? null;
                    $ppVol = $prevPrevMap[$tid]['volume'] ?? null;
                    // Only count flat-repeat if there were real trades (volume>0) across the streak.
                    // If volume is 0/null, treat it as illiquid/no-trade, not a feed-stuck signal.
                    if (
                        $v !== null && $v > 0 &&
                        $prevVol !== null && $prevVol > 0 &&
                        $ppVol !== null && $ppVol > 0 &&
                        $prevClose !== null && $c == $prevClose &&
                        $ppClose !== null && $ppClose == $prevClose
                    ) {
                        $counts['flat_repeat']++; $softFlags++;
                        $flatRepeatDay++;
                        if (!isset($samples['flat_repeat'])) $samples['flat_repeat'] = "{$tid}@{$d}: flat streak (>=3 days)";
                    }
                }
            }

            if ($hold) break;

            // Optional HOLD if massive anomaly in 1 day
            if (!$hold && $expectedTickers > 0) {
                $gapRatio = $gapExtremeDay / $expectedTickers;
                $staleRatio = $staleDay / $expectedTickers;
                $flatRepeatRatio = $flatRepeatDay / $expectedTickers;

                // Gap extreme mass -> suspicious feed/split not handled
                if ($gapRatio >= 0.05) {
                    $hold = true;
                    $holdReason = "gap_extreme_mass@{$d}";
                }
                // Stale mass -> data stuck
                elseif ($staleRatio >= 0.30) {
                    $hold = true;
                    $holdReason = "stale_mass@{$d}";
                }
                // Flat repeat mass -> data stuck
                // Tolerate higher ratio because many tickers can be illiquid and show flat bars.
                elseif ($flatRepeatRatio >= 0.15) {
                    $hold = true;
                    $holdReason = "flat_repeat_mass@{$d}";
                }
            }

            if ($hold && $holdReason) break;
        }

        return [
            'soft_flags' => (int) $softFlags,
            'rule_counts' => $counts,
            'hold' => (bool) $hold,
            'hold_reason' => $holdReason,
            'samples' => $samples,
        ];
    }
}
