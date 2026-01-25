<?php

namespace App\Trade\Watchlist\Scorecard;

use App\DTO\Watchlist\Scorecard\EligibilityCheckDto;
use App\DTO\Watchlist\Scorecard\EligibilityResultDto;
use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\Trade\Watchlist\Config\ScorecardConfig;
use Carbon\Carbon;

/**
 * Evaluate "can I execute now" eligibility per ticker based on:
 * - a stored strategy_run payload (docs/watchlist/scorecard.md)
 * - a user/job provided live snapshot (manual input from Ajaib)
 *
 * Deterministic + conservative:
 * - If required live fields are missing, eligible_now=false with reasons.
 * - Supports both the scorecard schema (ticker/entry_trigger/guards) and
 *   legacy watchlist-contract candidates (ticker_code/levels).
 */
class ExecutionEligibilityEvaluator
{
    /**
     * Pure evaluation based on:
     * - stored strategy_run payload (as DTO)
     * - live snapshot (as DTO)
     * - injected thresholds (as config object)
     */
    public function evaluate(StrategyRunDto $run, LiveSnapshotDto $snapshot, ScorecardConfig $cfg): EligibilityCheckDto
    {
        $policy = $run->policy;
        $tradeDate = $run->tradeDate;
        $execDate = $run->execDate;

        $now = Carbon::parse($snapshot->checkedAt);
        $nowTime = $now->format('H:i');

        // Recommendation mode (watchlist contract): BUY_*, CARRY_ONLY, NO_TRADE.
        // Used to allow CARRY_ONLY management checks without blocking on trade_disabled.
        $mode = $run->recommendationMode;

        $candidates = array_merge($run->topPicks, $run->secondary);
        if ($cfg->includeWatchOnly) {
            $candidates = array_merge($candidates, $run->watchOnly);
        }

        $results = [];
        foreach ($candidates as $cand) {
            $ticker = $cand->ticker;
            if ($ticker === '') continue;

            $flags = [];
            $reasons = [];
            $gapPct = null;
            $spreadPct = null;
            $chasePct = null;

            $snap = $snapshot->tickers[$ticker] ?? null;
            if (!$snap) {
                $reasons[] = 'SNAPSHOT_MISSING';
                $results[] = new EligibilityResultDto($ticker, false, $flags, $gapPct, $spreadPct, $chasePct, $reasons, 'Blocked: SNAPSHOT_MISSING');
                continue;
            }

            $hasPosition = $cand->hasPosition;

            // 1) hard disable
            // Exception (docs/watchlist/scorecard.md): when the run is in CARRY_ONLY mode
            // and the ticker already has a position, allow checks (management) without blocking.
            if ($cand->timing->tradeDisabled) {
                if ($mode === 'CARRY_ONLY' && $hasPosition) {
                    $flags[] = 'CARRY_ONLY_MANAGEMENT_OK';
                } else {
                    $reasons[] = 'TRADE_DISABLED';
                }
            }

            // 2) entry windows
            $entryWindows = $cand->timing->entryWindows;
            $avoidWindows = $cand->timing->avoidWindows;
            $inEntry = (count($entryWindows) === 0) ? true : $this->inAnyWindow($nowTime, $entryWindows, $snapshot, $cfg);
            $inAvoid = (count($avoidWindows) === 0) ? false : $this->inAnyWindow($nowTime, $avoidWindows, $snapshot, $cfg);
            if (!$inEntry) $reasons[] = 'OUTSIDE_ENTRY_WINDOW';
            if ($inAvoid) $reasons[] = 'IN_AVOID_WINDOW';

            // 3) chase guard
            $entryTrigger = $cand->entryTrigger !== null ? (float)$cand->entryTrigger : null;
            $maxChasePct = $cand->guards->maxChasePct;

            $last = $snap->last;
            if ($entryTrigger === null) {
                $reasons[] = 'ENTRY_TRIGGER_MISSING';
            } elseif ($last === null) {
                $reasons[] = 'LAST_PRICE_MISSING';
            } elseif ($entryTrigger <= 0) {
                $reasons[] = 'ENTRY_TRIGGER_INVALID';
            } else {
                $maxAllowed = $entryTrigger * (1.0 + (float)$maxChasePct);
                $chasePct = ($last - $entryTrigger) / $entryTrigger;
                if ($last > $maxAllowed) {
                    $reasons[] = 'CHASE_TOO_FAR';
                }
            }

            // 4) gap-up block (open vs prev_close)
            $prevClose = $snap->prevClose;
            $open = $snap->open;
            $gapUpBlockPct = $cand->guards->gapUpBlockPct;
            if ($prevClose !== null && $open !== null && $prevClose > 0) {
                $gapPct = ($open - $prevClose) / $prevClose;
                if ($gapPct > (float)$gapUpBlockPct) {
                    $reasons[] = 'GAP_UP_BLOCK';
                }
            } else {
                // Not always available on Ajaib depending on view.
                $flags[] = 'GAP_DATA_MISSING';
            }

            // 5) spread gate (proxy execution quality)
            $bid = $snap->bid;
            $ask = $snap->ask;
            $spreadMax = $cand->guards->spreadMaxPct;

            if ($bid === null || $ask === null || $last === null || $last <= 0) {
                $reasons[] = 'SPREAD_DATA_MISSING';
            } else {
                $spreadPct = ($ask - $bid) / $last;
                if ($spreadPct > (float)$spreadMax) {
                    $reasons[] = 'SPREAD_TOO_WIDE';
                }
            }

            $eligibleNow = empty($reasons);
            $notes = $eligibleNow ? 'In-window, chase OK, gap OK, spread OK' : ('Blocked: ' . implode(',', $reasons));

            $results[] = new EligibilityResultDto($ticker, $eligibleNow, $flags, $gapPct, $spreadPct, $chasePct, $reasons, $notes);
        }

        // Default recommendation: highest-rank eligible from top_picks, else secondary.
        $recommended = null;
        $why = null;
        foreach (['top_picks' => $run->topPicks, 'secondary' => $run->secondary] as $g => $rows) {
            $bestTicker = null;
            $bestRank = null;
            foreach ($rows as $cand) {
                $rowRes = $this->findResult($results, $cand->ticker);
                if (!$rowRes || !$rowRes->eligibleNow) continue;
                $rank = $cand->rank;
                if ($bestTicker === null || $bestRank === null || $rank < $bestRank) {
                    $bestTicker = $cand->ticker;
                    $bestRank = $rank;
                }
            }
            if ($bestTicker !== null) {
                $recommended = $bestTicker;
                $why = 'eligible_now && best_rank_in_' . $g;
                break;
            }
        }

        return new EligibilityCheckDto(
            $policy,
            $tradeDate,
            $execDate,
            $now->toRfc3339String(),
            $snapshot->checkpoint,
            $results,
            $recommended,
            $why,
        );
    }

    /**
     * @param EligibilityResultDto[] $results
     */
    private function findResult(array $results, string $ticker): ?EligibilityResultDto
    {
        foreach ($results as $r) {
            if ($r instanceof EligibilityResultDto && $r->ticker === $ticker) return $r;
        }
        return null;
    }

    /**
     * @param string[] $windows
     */
    private function inAnyWindow(string $timeHHMM, array $windows, LiveSnapshotDto $snapshot, ScorecardConfig $cfg): bool
    {
        $t = $this->toMinutesToken($timeHHMM, $snapshot, $cfg);
        if ($t === null) return false;

        foreach ($windows as $w) {
            if (!is_string($w) || strpos($w, '-') === false) continue;
            [$a, $b] = explode('-', $w, 2);
            $a = trim($a);
            $b = trim($b);
            $ma = $this->toMinutesToken($a, $snapshot, $cfg);
            $mb = $this->toMinutesToken($b, $snapshot, $cfg);
            if ($ma === null || $mb === null) continue;
            if ($t >= $ma && $t <= $mb) return true;
        }
        return false;
    }

    /**
     * Convert a time token to minutes.
     * Supported tokens:
     * - "HH:MM" (mandatory)
     * - "open" / "close" (resolved via snapshot or config defaults)
     */
    private function toMinutesToken(string $token, LiveSnapshotDto $snapshot, ScorecardConfig $cfg): ?int
    {
        $t = strtolower(trim($token));
        if ($t === 'open' || $t === 'close') {
            $raw = $t === 'open' ? $snapshot->sessionOpenTime : $snapshot->sessionCloseTime;
            return $this->toMinutesHHMM($raw);
        }

        return $this->toMinutesHHMM($token);
    }

    private function toMinutesHHMM(string $hhmm): ?int
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', trim($hhmm), $m)) return null;
        $h = (int)$m[1];
        $mi = (int)$m[2];
        if ($h < 0 || $h > 23 || $mi < 0 || $mi > 59) return null;
        return $h * 60 + $mi;
    }

}
