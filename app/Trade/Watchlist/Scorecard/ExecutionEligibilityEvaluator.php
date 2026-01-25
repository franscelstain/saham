<?php

namespace App\Trade\Watchlist\Scorecard;

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
     * @param array<string,mixed> $runPayload Strategy run payload.
     * @param array<string,mixed> $snapshot Live snapshot JSON.
     * @param array<string,mixed> $cfg config('trade.watchlist.scorecard')
     * @return array<string,mixed> result JSON.
     */
    public function evaluate(array $runPayload, array $snapshot, array $cfg): array
    {
        $policy = (string)(($runPayload['policy']['selected'] ?? '') ?: ($runPayload['policy'] ?? ''));
        $tradeDate = (string)($runPayload['trade_date'] ?? '');
        $execDate = (string)($runPayload['exec_trade_date'] ?? ($runPayload['exec_date'] ?? ''));

        $checkedAt = (string)($snapshot['checked_at'] ?? '');
        $now = $checkedAt !== '' ? Carbon::parse($checkedAt) : Carbon::now();
        $nowTime = $now->format('H:i');

        // Recommendation mode (watchlist contract): BUY_*, CARRY_ONLY, NO_TRADE.
        // Used to allow CARRY_ONLY management checks without blocking on trade_disabled.
        $mode = strtoupper(trim((string)($runPayload['recommendation']['mode'] ?? ($runPayload['mode'] ?? ''))));

        $includeWatchOnly = (bool)($cfg['include_watch_only'] ?? false);
        $groups = (array)($runPayload['groups'] ?? []);

        $candidates = [];
        foreach (['top_picks', 'secondary'] as $g) {
            if (!empty($groups[$g]) && is_array($groups[$g])) {
                foreach ($groups[$g] as $cand) {
                    if (is_array($cand)) $candidates[] = $cand;
                }
            }
        }
        if ($includeWatchOnly && !empty($groups['watch_only']) && is_array($groups['watch_only'])) {
            foreach ($groups['watch_only'] as $cand) {
                if (is_array($cand)) $candidates[] = $cand;
            }
        }

        // Snapshot tickers map by code
        $snapTickers = [];
        $snapArr = $snapshot['tickers'] ?? [];
        if (is_array($snapArr)) {
            foreach ($snapArr as $row) {
                if (!is_array($row)) continue;
                $code = strtoupper(trim((string)($row['ticker'] ?? ($row['ticker_code'] ?? ''))));
                if ($code === '') continue;
                $snapTickers[$code] = $row;
            }
        }

        $maxChaseDefault = (float)($cfg['max_chase_pct_default'] ?? 0.01);
        $gapUpBlockDefault = (float)($cfg['gap_up_block_pct_default'] ?? 0.015);
        $spreadMaxDefault = (float)($cfg['spread_max_pct_default'] ?? 0.004);

        $results = [];
        foreach ($candidates as $cand) {
            $ticker = strtoupper(trim((string)($cand['ticker'] ?? ($cand['ticker_code'] ?? ''))));
            if ($ticker === '') continue;

            $r = [
                'ticker' => $ticker,
                'eligible_now' => false,
                'flags' => [],
                'computed' => [
                    'gap_pct' => null,
                    'spread_pct' => null,
                    'chase_pct' => null,
                ],
                'reasons' => [],
                'notes' => '',
            ];

            $snap = $snapTickers[$ticker] ?? null;
            if (!$snap) {
                $r['reasons'][] = 'SNAPSHOT_MISSING';
                $results[] = $r;
                continue;
            }

            $timing = is_array($cand['timing'] ?? null) ? $cand['timing'] : [];
            $levels = is_array($cand['levels'] ?? null) ? $cand['levels'] : [];
            $guards = is_array($cand['guards'] ?? null) ? $cand['guards'] : [];
            $posObj = is_array($cand['position'] ?? null) ? $cand['position'] : [];
            $hasPosition = (bool)($cand['has_position'] ?? (bool)($posObj['has_position'] ?? false));

            // 1) hard disable
            // Exception (docs/watchlist/scorecard.md): when the run is in CARRY_ONLY mode
            // and the ticker already has a position, allow checks (management) without blocking.
            if (!empty($timing['trade_disabled'])) {
                if ($mode === 'CARRY_ONLY' && $hasPosition) {
                    $r['flags'][] = 'CARRY_ONLY_MANAGEMENT_OK';
                } else {
                    $r['reasons'][] = 'TRADE_DISABLED';
                }
            }

            // 2) entry windows
            $entryWindows = (array)($timing['entry_windows'] ?? []);
            $avoidWindows = (array)($timing['avoid_windows'] ?? []);
            $inEntry = (count($entryWindows) === 0) ? true : $this->inAnyWindow($nowTime, $entryWindows, $snapshot, $cfg);
            $inAvoid = (count($avoidWindows) === 0) ? false : $this->inAnyWindow($nowTime, $avoidWindows, $snapshot, $cfg);
            if (!$inEntry) $r['reasons'][] = 'OUTSIDE_ENTRY_WINDOW';
            if ($inAvoid) $r['reasons'][] = 'IN_AVOID_WINDOW';

            // 3) chase guard
            $entryTrigger = $this->toFloatOrNull($cand['entry_trigger'] ?? ($cand['entry_trigger_price'] ?? ($levels['entry_trigger_price'] ?? null)));
            $maxChasePct = $this->toFloatOrNull($guards['max_chase_pct'] ?? ($levels['max_chase_from_close_pct'] ?? null));
            if ($maxChasePct === null) $maxChasePct = $maxChaseDefault;

            $last = $this->toFloatOrNull($snap['last'] ?? ($snap['open_or_last'] ?? null));
            if ($entryTrigger === null) {
                $r['reasons'][] = 'ENTRY_TRIGGER_MISSING';
            } elseif ($last === null) {
                $r['reasons'][] = 'LAST_PRICE_MISSING';
            } elseif ($entryTrigger <= 0) {
                $r['reasons'][] = 'ENTRY_TRIGGER_INVALID';
            } else {
                $maxAllowed = $entryTrigger * (1.0 + (float)$maxChasePct);
                $chasePct = ($last - $entryTrigger) / $entryTrigger;
                $r['computed']['chase_pct'] = $chasePct;
                if ($last > $maxAllowed) {
                    $r['reasons'][] = 'CHASE_TOO_FAR';
                }
            }

            // 4) gap-up block (open vs prev_close)
            $prevClose = $this->toFloatOrNull($snap['prev_close'] ?? null);
            $open = $this->toFloatOrNull($snap['open'] ?? null);
            $gapUpBlockPct = $this->toFloatOrNull($guards['gap_up_block_pct'] ?? null);
            if ($gapUpBlockPct === null) $gapUpBlockPct = $gapUpBlockDefault;
            if ($prevClose !== null && $open !== null && $prevClose > 0) {
                $gapPct = ($open - $prevClose) / $prevClose;
                $r['computed']['gap_pct'] = $gapPct;
                if ($gapPct > (float)$gapUpBlockPct) {
                    $r['reasons'][] = 'GAP_UP_BLOCK';
                }
            } else {
                // Not always available on Ajaib depending on view.
                $r['flags'][] = 'GAP_DATA_MISSING';
            }

            // 5) spread gate (proxy execution quality)
            $bid = $this->toFloatOrNull($snap['bid'] ?? null);
            $ask = $this->toFloatOrNull($snap['ask'] ?? null);
            $spreadMax = $this->toFloatOrNull($guards['spread_max_pct'] ?? null);
            if ($spreadMax === null) $spreadMax = $spreadMaxDefault;

            if ($bid === null || $ask === null || $last === null || $last <= 0) {
                $r['reasons'][] = 'SPREAD_DATA_MISSING';
            } else {
                $spreadPct = ($ask - $bid) / $last;
                $r['computed']['spread_pct'] = $spreadPct;
                if ($spreadPct > (float)$spreadMax) {
                    $r['reasons'][] = 'SPREAD_TOO_WIDE';
                }
            }

            $r['eligible_now'] = empty($r['reasons']);
            $r['notes'] = $r['eligible_now']
                ? 'In-window, chase OK, gap OK, spread OK'
                : ('Blocked: ' . implode(',', $r['reasons']));

            $results[] = $r;
        }

        // Default recommendation: highest-rank eligible from top_picks, else secondary.
        $recommended = null;
        $why = '';
        foreach (['top_picks', 'secondary'] as $g) {
            $rows = $groups[$g] ?? [];
            if (!is_array($rows)) continue;
            $best = null;
            $bestRank = null;
            foreach ($rows as $cand) {
                if (!is_array($cand)) continue;
                $t = strtoupper(trim((string)($cand['ticker'] ?? ($cand['ticker_code'] ?? ''))));
                if ($t === '') continue;
                $rowRes = $this->findResult($results, $t);
                if (!$rowRes || empty($rowRes['eligible_now'])) continue;
                $rank = (int)($cand['rank'] ?? 0);
                if ($best === null || $rank < $bestRank || $bestRank === null) {
                    $best = $t;
                    $bestRank = $rank;
                }
            }
            if ($best !== null) {
                $recommended = $best;
                $why = 'eligible_now && best_rank_in_' . $g;
                break;
            }
        }

        return [
            'policy' => $policy,
            'trade_date' => $tradeDate,
            'exec_trade_date' => $execDate,
            'checked_at' => $now->toRfc3339String(),
            'checkpoint' => (string)($snapshot['checkpoint'] ?? ''),
            'results' => $results,
            'default_recommendation' => $recommended ? ['ticker' => $recommended, 'why' => $why] : null,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $results
     */
    private function findResult(array $results, string $ticker): ?array
    {
        foreach ($results as $r) {
            if (is_array($r) && strtoupper((string)($r['ticker'] ?? '')) === $ticker) return $r;
        }
        return null;
    }

    /**
     * @param string[] $windows
     */
    private function inAnyWindow(string $timeHHMM, array $windows, array $snapshot, array $cfg): bool
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
    private function toMinutesToken(string $token, array $snapshot, array $cfg): ?int
    {
        $t = strtolower(trim($token));
        if ($t === 'open' || $t === 'close') {
            // Allow snapshot to override session times.
            $key = $t === 'open' ? 'session_open_time' : 'session_close_time';
            $fallback = $t === 'open'
                ? (string)($cfg['session_open_time_default'] ?? '09:00')
                : (string)($cfg['session_close_time_default'] ?? '15:50');

            $raw = (string)($snapshot[$key] ?? $fallback);
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

    private function toFloatOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_int($v) || is_float($v)) return (float)$v;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') return null;
            if (!is_numeric($v)) return null;
            return (float)$v;
        }
        return null;
    }
}
