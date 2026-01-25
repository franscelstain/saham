<?php

namespace App\Services\Watchlist;

use App\Trade\Watchlist\Scorecard\ExecutionEligibilityEvaluator;
use App\Trade\Watchlist\Scorecard\ScorecardCalculator;
use App\Trade\Watchlist\Scorecard\ScorecardRepository;
use App\Trade\Watchlist\Scorecard\StrategyCheckRepository;
use App\Trade\Watchlist\Scorecard\StrategyRunRepository;

class WatchlistScorecardService
{
    public function __construct(
        private StrategyRunRepository $runRepo,
        private StrategyCheckRepository $checkRepo,
        private ScorecardRepository $scoreRepo,
        private ExecutionEligibilityEvaluator $evaluator,
        private ScorecardCalculator $calculator
    ) {
    }

    /**
     * Save / upsert strategy run (plan) from watchlist contract payload.
     */
    public function saveStrategyRun(array $payload, string $source = 'watchlist'): int
    {
        // Persist a scorecard-oriented strategy_run payload aligned with
        // docs/watchlist/scorecard.md (different from the watchlist contract schema).
        $normalized = $this->normalizeStrategyRunPayload($payload);
        return $this->runRepo->upsertFromPayload($normalized, $source);
    }

    /**
     * Normalize watchlist contract payload into the scorecard strategy_run schema.
     *
     * The watchlist contract uses keys like: ticker_code, watchlist_score, levels.entry_trigger_price,
     * sizing.slices, sizing.slice_pct (float). Scorecard expects: ticker, score, entry_trigger,
     * guards (max_chase_pct, gap_up_block_pct, spread_max_pct), slices, slice_pct (array).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalizeStrategyRunPayload(array $payload): array
    {
        $tradeDate = (string)($payload['trade_date'] ?? '');
        $execDate = (string)($payload['exec_trade_date'] ?? ($payload['exec_date'] ?? ''));
        $policy = (string)(($payload['policy']['selected'] ?? '') ?: ($payload['policy'] ?? ''));

        $cfg = (array) config('trade.watchlist.scorecard');
        $guardsDefault = [
            'max_chase_pct' => (float)($cfg['max_chase_pct_default'] ?? 0.01),
            'gap_up_block_pct' => (float)($cfg['gap_up_block_pct_default'] ?? 0.015),
            'spread_max_pct' => (float)($cfg['spread_max_pct_default'] ?? 0.004),
        ];

        // Prefer an explicit generated timestamp if present; otherwise derive from now.
        $generatedAt = null;
        if (isset($payload['meta']['generated_at']) && is_string($payload['meta']['generated_at']) && $payload['meta']['generated_at'] !== '') {
            $generatedAt = $payload['meta']['generated_at'];
        } elseif (isset($payload['generated_at']) && is_string($payload['generated_at']) && $payload['generated_at'] !== '') {
            $generatedAt = $payload['generated_at'];
        } else {
            $generatedAt = now()->toRfc3339String();
        }

        $out = [
            'trade_date' => $tradeDate,
            'exec_trade_date' => $execDate,
            'exec_date' => $execDate,
            'policy' => $policy,
            // Recommendation mode from watchlist contract (e.g. BUY_1, BUY_2_SPLIT, CARRY_ONLY, NO_TRADE).
            // Scorecard uses this to apply CARRY_ONLY exceptions during live eligibility checks.
            'recommendation' => [
                'mode' => (string)($payload['recommendation']['mode'] ?? ($payload['mode'] ?? '')),
            ],
            'meta' => [
                'generated_at' => $generatedAt,
            ],
            // Keep a top-level alias for DB convenience.
            'generated_at' => $generatedAt,
            'groups' => [
                'top_picks' => [],
                'secondary' => [],
                'watch_only' => [],
            ],
        ];

        $groups = (array)($payload['groups'] ?? []);
        foreach (['top_picks', 'secondary', 'watch_only'] as $g) {
            $rows = $groups[$g] ?? [];
            if (!is_array($rows)) continue;

            // Scorecard.md expects deterministic rank ordering. If the contract payload
            // doesn't carry rank, derive it from the group ordering.
            $rank = 1;

            foreach ($rows as $cand) {
                if (!is_array($cand)) continue;
                $norm = $this->normalizeCandidateForScorecard($cand, $guardsDefault, $rank);

                // Drop invalid candidates early (no ticker).
                if (!empty($norm['ticker'])) {
                    $out['groups'][$g][] = $norm;
                    $rank++;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $cand
     * @param array<string,float> $guardsDefault
     * @return array<string,mixed>
     */
    private function normalizeCandidateForScorecard(array $cand, array $guardsDefault, int $fallbackRank = 0): array
    {
        $ticker = strtoupper(trim((string)($cand['ticker'] ?? ($cand['ticker_code'] ?? ''))));

        $levels = is_array($cand['levels'] ?? null) ? $cand['levels'] : [];
        $timing = is_array($cand['timing'] ?? null) ? $cand['timing'] : [];
        $sizing = is_array($cand['sizing'] ?? null) ? $cand['sizing'] : [];
        $position = is_array($cand['position'] ?? null) ? $cand['position'] : [];

        $entryTrigger = $cand['entry_trigger'] ?? null;
        if ($entryTrigger === null) $entryTrigger = ($levels['entry_trigger_price'] ?? null);

        $entryLow = $cand['entry_limit_low'] ?? null;
        if ($entryLow === null) $entryLow = ($levels['entry_limit_low'] ?? null);
        $entryHigh = $cand['entry_limit_high'] ?? null;
        if ($entryHigh === null) $entryHigh = ($levels['entry_limit_high'] ?? null);

        $slices = (int)($cand['slices'] ?? ($sizing['slices'] ?? 1));
        if ($slices < 1) $slices = 1;

        // Scorecard.md expects slice_pct array. Watchlist contract has a float.
        $slicePct = $cand['slice_pct'] ?? null;
        if (!is_array($slicePct)) {
            // If the contract provided a scalar pct, treat it as "each slice" only when it sums <= 1.
            $scalar = $sizing['slice_pct'] ?? null;
            if (is_numeric($scalar)) {
                $scalar = (float)$scalar;
            } else {
                $scalar = null;
            }

            if ($slices === 1) {
                $slicePct = [1.0];
            } else {
                // Default: equal slices.
                $each = 1.0 / (float)$slices;
                $slicePct = array_fill(0, $slices, $each);

                // If scalar looks like a first-slice weight (e.g. 0.6), build 2-slice distribution.
                if ($scalar !== null && $slices === 2 && $scalar > 0 && $scalar < 1.0) {
                    $slicePct = [$scalar, 1.0 - $scalar];
                }
            }
        }

        $guards = is_array($cand['guards'] ?? null) ? $cand['guards'] : [];
        // Backward compat: some contracts store max chase pct in levels.
        $maxChase = $guards['max_chase_pct'] ?? ($levels['max_chase_from_close_pct'] ?? null);
        $gapUp = $guards['gap_up_block_pct'] ?? null;
        $spreadMax = $guards['spread_max_pct'] ?? null;

        $guardsOut = [
            'max_chase_pct' => is_numeric($maxChase) ? (float)$maxChase : (float)$guardsDefault['max_chase_pct'],
            'gap_up_block_pct' => is_numeric($gapUp) ? (float)$gapUp : (float)$guardsDefault['gap_up_block_pct'],
            'spread_max_pct' => is_numeric($spreadMax) ? (float)$spreadMax : (float)$guardsDefault['spread_max_pct'],
        ];

        return [
            'ticker' => $ticker,
            'has_position' => (bool)($cand['has_position'] ?? ($position['has_position'] ?? false)),
            'score' => (int)round((float)($cand['score'] ?? ($cand['watchlist_score'] ?? 0))),
            'rank' => (int)(is_numeric($cand['rank'] ?? null) && (int)$cand['rank'] > 0 ? (int)$cand['rank'] : ($fallbackRank > 0 ? $fallbackRank : 0)),
            'entry_trigger' => is_numeric($entryTrigger) ? (int)$entryTrigger : null,
            'entry_band' => [
                'low' => is_numeric($entryLow) ? (int)$entryLow : null,
                'high' => is_numeric($entryHigh) ? (int)$entryHigh : null,
            ],
            'guards' => $guardsOut,
            'timing' => [
                'trade_disabled' => (bool)($timing['trade_disabled'] ?? false),
                'entry_windows' => array_values((array)($timing['entry_windows'] ?? [])),
                'avoid_windows' => array_values((array)($timing['avoid_windows'] ?? [])),
                'trade_disabled_reason' => $timing['trade_disabled_reason'] ?? null,
                'trade_disabled_reason_codes' => array_values((array)($timing['trade_disabled_reason_codes'] ?? [])),
            ],
            'slices' => $slices,
            'slice_pct' => array_values($slicePct),
            'reason_codes' => array_values((array)($cand['reason_codes'] ?? [])),
        ];
    }

    /**
     * Evaluate and persist a live check.
     *
     * @return array<string,mixed> result JSON
     */
    public function checkLive(string $tradeDate, string $execDate, string $policy, array $snapshot, string $source = 'watchlist'): array
    {
        $run = $this->runRepo->getRun($tradeDate, $execDate, $policy, $source);
        if (!$run) {
            throw new \RuntimeException("strategy run not found: $tradeDate/$execDate/$policy (source=$source)");
        }

        $cfg = (array) config('trade.watchlist.scorecard');
        $result = $this->evaluator->evaluate($run, $snapshot, $cfg);

        $runId = (int) ($run['_run_id'] ?? 0);
        if ($runId > 0) {
            $checkedAt = (string) ($result['checked_at'] ?? now()->toRfc3339String());
            $this->checkRepo->insertCheck($runId, $checkedAt, $snapshot, $result);
        }

        return $result;
    }

    /**
     * Compute and persist scorecard for the latest check.
     *
     * @return array<string,mixed>
     */
    public function computeScorecard(string $tradeDate, string $execDate, string $policy, string $source = 'watchlist'): array
    {
        $run = $this->runRepo->getRun($tradeDate, $execDate, $policy, $source);
        if (!$run) {
            throw new \RuntimeException("strategy run not found: $tradeDate/$execDate/$policy (source=$source)");
        }
        $runId = (int) ($run['_run_id'] ?? 0);
        if ($runId <= 0) {
            throw new \RuntimeException('invalid run_id');
        }

        $latest = $this->checkRepo->getLatestCheck($runId);
        $calc = $this->calculator->compute($run, $latest);

        $this->scoreRepo->upsertScorecard(
            $runId,
            $calc['feasible_rate'],
            $calc['fill_rate'],
            $calc['outcome_rate'],
            $calc['payload']
        );

        return [
            'run_id' => $runId,
            'trade_date' => $tradeDate,
            'exec_trade_date' => $execDate,
            'exec_date' => $execDate,
            'policy' => $policy,
            'feasible_rate' => $calc['feasible_rate'],
            'fill_rate' => $calc['fill_rate'],
            'outcome_rate' => $calc['outcome_rate'],
            'details' => $calc['payload'],
            'latest_check' => $latest,
        ];
    }
}
