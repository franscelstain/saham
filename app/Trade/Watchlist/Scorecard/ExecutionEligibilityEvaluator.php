<?php

namespace App\Trade\Watchlist\Scorecard;

use App\DTO\Watchlist\Scorecard\EligibilityCheckDto;
use App\DTO\Watchlist\Scorecard\EligibilityResultDto;
use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\DTO\Watchlist\Scorecard\LiveTickerDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\Trade\Watchlist\Config\ScorecardConfig;

/**
 * Strict CONFIRM evaluator (docs/watchlist/scorecard.md).
 *
 * Goals:
 * - Deterministic, policy-driven guards
 * - Single source of truth for CONFIRM decisions
 * - Retry budget end-to-end (max_retry_windows, retry_count, CF_MAX_RETRY_REACHED, next_check_at)
 */
class ExecutionEligibilityEvaluator
{
    /** @var ScorecardConfig */
    private $cfg;

    public function __construct(ScorecardConfig $cfg)
    {
        $this->cfg = $cfg;
    }

    /**
     * Compatibility wrapper used by WatchlistEngine & WatchlistScorecardService.
     */
    public function evaluate(StrategyRunDto $run, LiveSnapshotDto $snapshot, ScorecardConfig $cfg): EligibilityCheckDto
    {
        // Build plan map from run candidates (single source of truth stays here).
        $planByTicker = [];

        $lists = [
            'top' => $run->topPicks,
            'secondary' => $run->secondary,
            'watch' => $run->watchOnly,
        ];

        foreach ($lists as $k => $arr) {
            if ($k === 'watch' && !$cfg->includeWatchOnly) continue;
            foreach ((array)$arr as $cand) {
                if (!$cand || !($cand instanceof \App\DTO\Watchlist\Scorecard\CandidateDto)) continue;
                $t = strtoupper(trim((string)$cand->ticker));
                if ($t === '') continue;
                $planByTicker[$t] = [
                    'setup_type' => $cand->setupType,
                    'entry_trigger' => ($cand->entryTrigger === null) ? null : (float)$cand->entryTrigger,
                    'stop_price' => $cand->stopPrice,
                    'tp1_price' => $cand->tp1Price,
                    'rr_est' => $cand->rrEst,
                    'execution_slices' => (array)$cand->executionSlices,
                ];
            }
        }

        return $this->evaluateSnapshot($run->tradeDate, $run->execDate, $run->policy, $snapshot, $planByTicker);
    }

    /**
     * Evaluate all tickers in a snapshot.
     *
     * @param string $tradeDate
     * @param string $execDate
     * @param string $policy
     * @param array<string,array<string,mixed>> $planByTicker plan slices keyed by ticker_code
     */
    public function evaluateSnapshot(string $tradeDate, string $execDate, string $policy, LiveSnapshotDto $snapshot, array $planByTicker): EligibilityCheckDto
    {
        $results = [];

        foreach ($planByTicker as $ticker => $plan) {
            $ticker = strtoupper(trim((string)$ticker));
            if ($ticker === '') continue;
            $live = isset($snapshot->tickers[$ticker]) ? $snapshot->tickers[$ticker] : null;
            $results[] = $this->evaluateTicker($tradeDate, $execDate, $policy, $snapshot, $ticker, $plan, $live);
        }

        // Default recommendation: first APPROVE ticker, else first ticker.
        $recTicker = null;
        $recWhy = null;
        foreach ($results as $r) {
            if (!$r instanceof EligibilityResultDto) continue;
            if ($r->decision === 'APPROVE') {
                $recTicker = $r->tickerCode;
                $recWhy = 'APPROVE: ada tranche yang bisa dieksekusi sekarang.';
                break;
            }
        }
        if ($recTicker === null && isset($results[0]) && $results[0] instanceof EligibilityResultDto) {
            $recTicker = $results[0]->tickerCode;
            $recWhy = 'Tidak ada APPROVE; fokus ke ticker pertama untuk review.';
        }

        return new EligibilityCheckDto(
            $policy,
            $tradeDate,
            $execDate,
            $snapshot->checkedAt,
            $results,
            $recTicker,
            $recWhy,
            null
        );
    }

    /**
     * @param array<string,mixed> $plan
     */
    private function evaluateTicker(string $tradeDate, string $execDate, string $policy, LiveSnapshotDto $snapshot, string $ticker, array $plan, ?LiveTickerDto $live): EligibilityResultDto
    {
        $guards = $this->cfg->guardsForPolicy($policy);
        $windows = $this->cfg->windowsForPolicy($policy);

        $maxRetryWindows = (int)($guards['max_retry_windows'] ?? 0);
        if ($maxRetryWindows <= 0) $maxRetryWindows = (int)$this->cfg->maxRetryWindowsDefault;

        $computed = [
            'gap_pct' => null,
            'spread_pct' => null,
            'chase_pct' => null,
            'snapshot_age_sec' => 0,
            'max_retry_windows' => $maxRetryWindows,
            'retry_count' => 0,
        ];

        $planBlock = $this->buildPlanBlock($plan, $guards);

        // --- PLAN minimum ---
        $slices = isset($planBlock['execution_slices']) && is_array($planBlock['execution_slices']) ? $planBlock['execution_slices'] : [];
        if (empty($slices)) {
            return $this->resultReject($ticker, ['CF_PLAN_INPUT_MISSING'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']));
        }

        // --- LIVE minimum ---
        if (!$live || $live->ticker === '') {
            return $this->resultDelayWithRetry($ticker, ['CF_LIVE_INPUT_MISSING'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']), $snapshot, $live, $maxRetryWindows);
        }

        $bid = $live->bid;
        $ask = $live->ask;
        $last = $live->last;
        $open = $live->open;
        $prevPlan = $live->prevClosePlan;

        if ($bid === null || $ask === null || $last === null || $bid <= 0 || $ask <= 0) {
            return $this->resultDelayWithRetry($ticker, ['CF_LIVE_INPUT_MISSING'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']), $snapshot, $live, $maxRetryWindows);
        }

        if ($bid > $ask) {
            return $this->resultDelayWithRetry($ticker, ['CF_LIVE_BOOK_INVALID'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']), $snapshot, $live, $maxRetryWindows);
        }

        // Stale detector (tolerance based on bid/ask vs last)
        if ($this->isStaleBook($bid, $ask, $last, $this->cfg->staleTolPct)) {
            return $this->resultDelayWithRetry($ticker, ['CF_LIVE_SNAPSHOT_STALE'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']), $snapshot, $live, $maxRetryWindows);
        }

        // Entry/avoid window
        $checkedAt = $snapshot->checkedAt;
        if (!$this->isInAnyWindow($checkedAt, $snapshot->sessionOpenTime, $snapshot->sessionCloseTime, (array)($windows['entry_windows'] ?? []))) {
            return $this->resultDelayWithRetry($ticker, ['CF_NOT_IN_ENTRY_WINDOW'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']), $snapshot, $live, $maxRetryWindows);
        }
        if ($this->isInAnyWindow($checkedAt, $snapshot->sessionOpenTime, $snapshot->sessionCloseTime, (array)($windows['avoid_windows'] ?? []))) {
            return $this->resultDelayWithRetry($ticker, ['CF_IN_AVOID_WINDOW'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']), $snapshot, $live, $maxRetryWindows);
        }

        // Compute guard metrics
        $entryTrigger = isset($planBlock['entry_trigger']) ? (float)$planBlock['entry_trigger'] : 0.0;
        if ($entryTrigger <= 0) {
            return $this->resultReject($ticker, ['CF_PLAN_INPUT_MISSING'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']));
        }

        // --- Price reference (open vs last) locked by window ---
        $priceRef = $this->pickPriceRef($policy, $snapshot, $live);

        // gap_pct & chase_pct use price_ref consistently
        if ($priceRef !== null && $prevPlan !== null && $prevPlan > 0) {
            $computed['gap_pct'] = ($priceRef - $prevPlan) / $prevPlan;
        }

        // spread_pct uses mid (bid+ask)/2
        $mid = ($bid + $ask) / 2.0;
        if ($mid > 0) {
            $computed['spread_pct'] = ($ask - $bid) / $mid;
        }

        if ($priceRef !== null && $entryTrigger > 0) {
            $raw = ($priceRef - $entryTrigger) / $entryTrigger;
            $computed['chase_pct'] = ($raw > 0) ? $raw : 0.0;
        }

        // Hard guards
        $gapBlock = (float)($guards['gap_up_block_pct'] ?? 0.0);
        if ($gapBlock > 0 && $computed['gap_pct'] !== null && $computed['gap_pct'] > $gapBlock) {
            return $this->resultReject($ticker, ['CF_GAP_UP_BLOCK'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']));
        }

        $spreadMax = (float)($guards['spread_max_pct'] ?? 0.0);
        if ($spreadMax > 0 && $computed['spread_pct'] !== null && $computed['spread_pct'] > $spreadMax) {
            return $this->resultReject($ticker, ['CF_SPREAD_TOO_WIDE'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']));
        }

        
        // Optional order book depth guard (if live provides depth and policy enables it).
        $minDepthLots = (int)($guards['min_depth_lots'] ?? $this->cfg->minDepthLotsDefault);
        if ($minDepthLots > 0 && $live) {
            $bidDepth = $live->bidDepthLots;
            $askDepth = $live->askDepthLots;
            if ($bidDepth !== null && $askDepth !== null) {
                $minSide = min((int)$bidDepth, (int)$askDepth);
                if ($minSide < $minDepthLots) {
                    return $this->resultReject($ticker, ['CF_BOOK_TOO_THIN'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']));
                }
            }
        }

// BREAKOUT strict rule: lower bound (last >= entry) + upper band (last <= entry*(1+band))
        $setup = strtoupper(trim((string)($planBlock['setup_type'] ?? '')));
        $bandPct = (float)($guards['breakout_band_pct'] ?? 0.0);
        if ($setup === 'BREAKOUT') {
            if ($last < $entryTrigger) {
                return $this->resultDelayWithRetry($ticker, ['CF_BREAKOUT_BELOW_ENTRY'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']), $snapshot, $live, $maxRetryWindows);
            }
            if ($bandPct > 0 && $last > ($entryTrigger * (1.0 + $bandPct))) {
                return $this->resultReject($ticker, ['CF_BREAKOUT_TOO_EXTENDED'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']));
            }
        }

        // Build recommended orders per slice
        $orders = [];
        $hasPlace = false;
        $hasWait = false;
        $waitReason = null;

        foreach ($slices as $slice) {
            if (!is_array($slice)) continue;

            $n = (int)($slice['tranche'] ?? ($slice['n'] ?? 0));
            $lots = (int)($slice['lots'] ?? 0);
            $planLimit = isset($slice['plan_limit_price']) ? (float)$slice['plan_limit_price'] : (isset($slice['plan_limit']) ? (float)$slice['plan_limit'] : 0.0);
            $planCap = isset($slice['plan_price_cap']) ? (float)$slice['plan_price_cap'] : (isset($slice['plan_cap']) ? (float)$slice['plan_cap'] : 0.0);

            if ($n <= 0 || $lots <= 0 || $planLimit <= 0) {
                $orders[] = [
                    'n' => max(1, $n),
                    'action' => 'SKIP',
                    'recommended_limit_price' => null,
                    'plan_limit_price' => $planLimit > 0 ? $planLimit : null,
                    'plan_price_cap' => $planCap > 0 ? $planCap : null,
                    'lots' => $lots > 0 ? $lots : null,
                    'reasons' => $this->reasonsFromCodes(['CF_TRANCHE_SKIPPED']),
                    'inputs_used' => [
                        'ask_best' => $ask,
                        'spread_pct' => $computed['spread_pct'],
                        'snapshot_age_sec' => $computed['snapshot_age_sec'],
                    ],
                ];
                continue;
            }

            $cap = $planCap > 0 ? $planCap : $planLimit;

            if ($ask > $cap) {
                $hasWait = true;
                $waitReason = $waitReason ?: 'CF_CHASE_BLOCK';
                $orders[] = [
                    'n' => $n,
                    'action' => 'WAIT',
                    'recommended_limit_price' => null,
                    'plan_limit_price' => $planLimit,
                    'plan_price_cap' => $cap,
                    'lots' => $lots,
                    'reasons' => $this->reasonsFromCodes(['CF_CHASE_BLOCK']),
                    'inputs_used' => [
                        'ask_best' => $ask,
                        'plan_price_cap' => $cap,
                        'plan_limit_price' => $planLimit,
                        'spread_pct' => $computed['spread_pct'],
                        'snapshot_age_sec' => $computed['snapshot_age_sec'],
                    ],
                ];
                continue;
            }

            $limit = $this->clampLimitPrice($ask, $cap, $planLimit);

            $reasonCodes = [];
            if (abs($limit - $ask) < 0.0000001) {
                $reasonCodes[] = 'CF_PRICE_AT_ASK1_WITHIN_CAP';
            } else {
                // audit clamp reasons
                if ($cap > 0 && $cap < $ask && abs($limit - $cap) < 0.0000001) $reasonCodes[] = 'CF_PRICE_CLAMPED_TO_CAP';
                if ($planLimit > 0 && $planLimit < $ask && abs($limit - $planLimit) < 0.0000001) $reasonCodes[] = 'CF_PRICE_CLAMPED_TO_PLAN_LIMIT';
                if (empty($reasonCodes)) $reasonCodes[] = 'CF_PRICE_AT_ASK1_WITHIN_CAP';
            }

            $hasPlace = true;
            $orders[] = [
                'n' => $n,
                'action' => 'PLACE_LIMIT',
                'recommended_limit_price' => $limit,
                'plan_limit_price' => $planLimit,
                'plan_price_cap' => $cap,
                'lots' => $lots,
                'reasons' => $this->reasonsFromCodes($reasonCodes),
                'inputs_used' => [
                    'ask_best' => $ask,
                    'plan_price_cap' => $cap,
                    'plan_limit_price' => $planLimit,
                    'spread_pct' => $computed['spread_pct'],
                    'snapshot_age_sec' => $computed['snapshot_age_sec'],
                ],
            ];
        }

        if ($hasPlace) {
            // APPROVE
            $computed['retry_count'] = $this->retryCountFromLive($live);
            return new EligibilityResultDto(
                $ticker,
                true,
                [],
                $computed['gap_pct'],
                $computed['spread_pct'],
                $computed['chase_pct'],
                ['CF_OK'],
                '',
                'APPROVE',
                null,
                $planBlock,
                $computed,
                $this->liveBlockFromDto($live, $computed['snapshot_age_sec']),
                $orders
            );
        }

        if ($hasWait) {
            // DELAY (retry budget)
            return $this->resultDelayWithRetry($ticker, [$waitReason ?: 'CF_CHASE_BLOCK'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']), $snapshot, $live, $maxRetryWindows, $orders);
        }

        // Nothing placeable and nothing waitable -> reject
        $computed['retry_count'] = $this->retryCountFromLive($live);
        return $this->resultReject($ticker, ['CF_NO_SLICES'], $planBlock, $computed, $this->liveBlockFromDto($live, $computed['snapshot_age_sec']), $orders);
    }

    /**
     * Hard guarantee: decision != APPROVE must not emit PLACE_LIMIT.
     *
     * @param string $decision
     * @param array<int,array<string,mixed>> $orders
     * @return array<int,array<string,mixed>>
     */
    private function sanitizeOrders(string $decision, array $orders): array
    {
        $d = strtoupper(trim($decision));
        if ($d === 'APPROVE') return $orders;
        $out = [];
        foreach ($orders as $o) {
            if (!is_array($o)) continue;
            $act = strtoupper((string)($o['action'] ?? ''));
            if ($act === 'PLACE_LIMIT') {
                $o['action'] = 'WAIT';
                $o['recommended_limit_price'] = null;
                $o['reasons'] = $this->reasonsFromCodes(['CF_DECISION_NOT_APPROVE']);
            }
            $out[] = $o;
        }
        return $out;
    }


    /**
     * @param array<int,string> $reasonCodes
     * @param array<string,mixed> $planBlock
     * @param array<string,mixed> $computed
     * @param array<string,mixed> $liveBlock
     * @param array<int,array<string,mixed>> $orders
     */
    private function resultReject(string $ticker, array $reasonCodes, array $planBlock, array $computed, array $liveBlock, array $orders = []): EligibilityResultDto
    {
        $computed['retry_count'] = isset($computed['retry_count']) ? (int)$computed['retry_count'] : 0;
        $orders = $this->sanitizeOrders('REJECT', $orders);
        return new EligibilityResultDto(
            $ticker,
            false,
            [],
            $computed['gap_pct'] ?? null,
            $computed['spread_pct'] ?? null,
            $computed['chase_pct'] ?? null,
            $reasonCodes,
            '',
            'REJECT',
            null,
            $planBlock,
            $computed,
            $liveBlock,
            $orders
        );
    }

    /**
     * Apply retry budget on top of a DELAY reason.
     *
     * @param array<int,string> $reasonCodes
     * @param array<string,mixed> $planBlock
     * @param array<string,mixed> $computed
     * @param array<string,mixed> $liveBlock
     * @param LiveSnapshotDto $snapshot
     * @param LiveTickerDto|null $live
     * @param int $maxRetryWindows
     * @param array<int,array<string,mixed>> $orders
     */
    private function resultDelayWithRetry(string $ticker, array $reasonCodes, array $planBlock, array $computed, array $liveBlock, LiveSnapshotDto $snapshot, ?LiveTickerDto $live, int $maxRetryWindows, array $orders = []): EligibilityResultDto
    {
        $orders = $this->sanitizeOrders('DELAY', $orders);

        $prev = $this->retryCountFromLive($live);
        $lastTs = $live ? $live->retryLastCheckedAt : null;
        $checkedAt = $snapshot->checkedAt;

        $inc = $this->shouldIncrementRetry($checkedAt, $lastTs);
        $new = $prev + ($inc ? 1 : 0);

        $computed['retry_count'] = $new;
        $computed['max_retry_windows'] = $maxRetryWindows;

        if ($maxRetryWindows > 0 && $new > $maxRetryWindows) {
            // Turn into REJECT
            $reasonCodes[] = 'CF_MAX_RETRY_REACHED';
            return new EligibilityResultDto(
                $ticker,
                false,
                [],
                $computed['gap_pct'] ?? null,
                $computed['spread_pct'] ?? null,
                $computed['chase_pct'] ?? null,
                $reasonCodes,
                '',
                'REJECT',
                null,
                $planBlock,
                $computed,
                $liveBlock,
                $orders
            );
        }

        $next = $this->addSeconds($checkedAt, (int)$this->cfg->retryCooldownSec);

        return new EligibilityResultDto(
            $ticker,
            false,
            [],
            $computed['gap_pct'] ?? null,
            $computed['spread_pct'] ?? null,
            $computed['chase_pct'] ?? null,
            $reasonCodes,
            '',
            'DELAY',
            $next,
            $planBlock,
            $computed,
            $liveBlock,
            $orders
        );
    }

    private function retryCountFromLive(?LiveTickerDto $live): int
    {
        if (!$live) return 0;
        return ($live->retryCount === null) ? 0 : (int)$live->retryCount;
    }

    private function shouldIncrementRetry(string $checkedAt, ?string $lastCheckedAt): bool
    {
        $checkedAt = (string)$checkedAt;
        if ($checkedAt === '') return false;
        if ($lastCheckedAt === null || $lastCheckedAt === '') return true;

        $a = strtotime($checkedAt);
        $b = strtotime($lastCheckedAt);
        if ($a !== false && $b !== false) {
            return $a > $b;
        }

        // Fallback: different string means newer attempt.
        return $checkedAt !== $lastCheckedAt;
    }

    /**
     * price_ref per window: use open during early-open window, otherwise last.
     */
    private function pickPriceRef(string $policy, LiveSnapshotDto $snapshot, LiveTickerDto $live): ?float
    {
        $last = $live->last;
        if ($last === null || $last <= 0) return null;

        $open = $live->open;
        $openRefMin = (int)$this->cfg->policyValue($policy, 'open_ref_minutes', 10);
        if ($openRefMin < 0) $openRefMin = 0;

        if ($open !== null && $open > 0 && $openRefMin > 0) {
            $checkedTs = strtotime((string)$snapshot->checkedAt);
            $openTs = $this->sessionOpenTs((string)$snapshot->checkedAt, (string)$snapshot->sessionOpenTime);
            if ($checkedTs !== false && $openTs !== null) {
                if ($checkedTs <= ($openTs + ($openRefMin * 60))) {
                    return (float)$open;
                }
            }
        }

        return (float)$last;
    }

    /**
     * Build session open timestamp based on checkedAt date and sessionOpenTime HH:MM.
     */
    private function sessionOpenTs(string $checkedAtRfc3339, string $sessionOpenTime): ?int
    {
        $d = substr($checkedAtRfc3339, 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
        $t = trim($sessionOpenTime);
        if ($t === '') return null;
        if (preg_match('/^\d{2}:\d{2}$/', $t)) {
            $dt = $d . ' ' . $t . ':00';
        } elseif (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
            $dt = $d . ' ' . $t;
        } else {
            return null;
        }
        $ts = strtotime($dt);
        return ($ts === false) ? null : (int)$ts;
    }


    private function addSeconds(string $rfc3339, int $sec): ?string
    {
        $rfc3339 = (string)$rfc3339;
        if ($rfc3339 === '') return null;
        try {
            $dt = new \DateTime($rfc3339);
            $dt->modify('+' . max(0, $sec) . ' seconds');
            return $dt->format('c');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isStaleBook(float $bid, float $ask, float $last, float $tolPct): bool
    {
        if ($bid <= 0 || $ask <= 0) return true;
        if ($last <= 0) return true;
        $tol = max(0.0, (float)$tolPct);
        // last should be within [bid*(1-tol), ask*(1+tol)]
        if ($last < ($bid * (1.0 - $tol))) return true;
        if ($last > ($ask * (1.0 + $tol))) return true;
        return false;
    }

    /**
     * @param array<string,mixed> $plan
     * @param array{max_chase_pct:float,gap_up_block_pct:float,spread_max_pct:float,breakout_band_pct:float,max_retry_windows:int} $guards
     * @return array<string,mixed>
     */
    private function buildPlanBlock(array $plan, array $guards): array
    {
        $slices = [];
        $exec = isset($plan['execution_slices']) && is_array($plan['execution_slices']) ? $plan['execution_slices'] : [];
        foreach ($exec as $s) {
            if (!is_array($s)) continue;
            $slices[] = [
                'tranche' => (int)($s['tranche'] ?? 0),
                'lots' => (int)($s['lots'] ?? 0),
                'plan_limit_price' => isset($s['plan_limit_price']) ? (float)$s['plan_limit_price'] : null,
                'plan_price_cap' => isset($s['plan_price_cap']) ? (float)$s['plan_price_cap'] : null,
            ];
        }

        return [
            'setup_type' => (string)($plan['setup_type'] ?? ''),
            'entry_trigger' => isset($plan['entry_trigger']) ? (float)$plan['entry_trigger'] : (isset($plan['entry_trigger_price']) ? (float)$plan['entry_trigger_price'] : null),
            'stop_price' => isset($plan['stop_price']) ? (float)$plan['stop_price'] : null,
            'tp1_price' => isset($plan['tp1_price']) ? (float)$plan['tp1_price'] : null,
            'rr_est' => isset($plan['rr_est']) ? (float)$plan['rr_est'] : null,
            'guards' => [
                'max_chase_pct' => (float)($guards['max_chase_pct'] ?? 0),
                'gap_up_block_pct' => (float)($guards['gap_up_block_pct'] ?? 0),
                'spread_max_pct' => (float)($guards['spread_max_pct'] ?? 0),
                'breakout_band_pct' => (float)($guards['breakout_band_pct'] ?? 0),
                'max_retry_windows' => (int)($guards['max_retry_windows'] ?? 0),
            ],
            'execution_slices' => array_values($slices),
        ];
    }

    private function clampLimitPrice(float $ask1, float $cap, float $planLimit): float
    {
        $v = $ask1;
        if ($cap > 0) $v = min($v, $cap);
        if ($planLimit > 0) $v = min($v, $planLimit);
        return $v;
    }

    /**
     * @return array<string,mixed>
     */
    private function liveBlockFromDto(?LiveTickerDto $live, int $ageSec): array
    {
        if (!$live) {
            return [
                'last' => null,
                'bid1' => null,
                'ask1' => null,
                'open' => null,
                'prev_close_plan' => null,
                'prev_close_live' => null,
                'snapshot_age_sec' => $ageSec,
            ];
        }

        return [
            'last' => $live->last,
            'bid1' => $live->bid,
            'ask1' => $live->ask,
            'open' => $live->open,
            'prev_close_plan' => $live->prevClosePlan,
            'prev_close_live' => $live->prevCloseLive,
            'snapshot_age_sec' => $ageSec,
        ];
    }

    /**
     * Window parsing intentionally small: supports "open-close" and "HH:MM-HH:MM".
     *
     * @param string $checkedAt RFC3339
     * @param string $sessionOpenTime HH:MM
     * @param string $sessionCloseTime HH:MM
     * @param array<int,string> $windows
     */
    private function isInAnyWindow(string $checkedAt, string $sessionOpenTime, string $sessionCloseTime, array $windows): bool
    {
        $ts = strtotime($checkedAt);
        if ($ts === false) return false;

        $t = date('H:i', $ts);

        foreach ($windows as $w) {
            $w = strtolower(trim((string)$w));
            if ($w === '') continue;

            if ($w === 'open-close') {
                if ($this->isTimeBetween($t, $sessionOpenTime, $sessionCloseTime)) return true;
                continue;
            }

            // HH:MM-HH:MM
            if (strpos($w, '-') !== false) {
                [$a, $b] = array_map('trim', explode('-', $w, 2));
                if ($a !== '' && $b !== '' && $this->isTimeBetween($t, $a, $b)) return true;
            }
        }

        return false;
    }

    private function isTimeBetween(string $t, string $start, string $end): bool
    {
        // naive lexicographic compare works for HH:MM format
        if ($start === '' || $end === '') return false;
        if ($start <= $end) {
            return ($t >= $start && $t <= $end);
        }
        // wrap-around window (rare)
        return ($t >= $start || $t <= $end);
    }
}
