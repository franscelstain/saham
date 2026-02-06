<?php

namespace Tests\Unit\Regression;

use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Scorecard\ExecutionEligibilityEvaluator;
use App\Trade\Watchlist\Scorecard\ScorecardMetricsCalculator;
use App\Services\Watchlist\WatchlistScorecardService;
use App\Support\SystemClock;
use App\Trade\Watchlist\Scorecard\StrategyRunRepository;
use App\Trade\Watchlist\Scorecard\StrategyCheckRepository;
use App\Trade\Watchlist\Scorecard\ScorecardRepository;
use App\DTO\Watchlist\Scorecard\EligibilityCheckDto;
use App\DTO\Watchlist\Scorecard\ScorecardMetricsDto;
use App\DTO\Watchlist\Scorecard\StrategyCheckDto;
use App\Repositories\IntradaySnapshotRepository;
use App\Repositories\TickerRepository;
use App\Repositories\TickerOhlcDailyRepository;
use App\Repositories\WatchlistPersistenceRepository;
use Tests\TestCase;

class ScorecardCheckLiveGoldenMasterTest extends TestCase
{
    public function testGoldenMasterFixtureSnapshotMatchesExpected(): void
    {
        // Be explicit: avoid relying on helper factories across versions.
        $cfg = new ScorecardConfig(false, 0.01, 0.015, 0.004, '09:00', '15:50');
        $clock = new SystemClock();

        $runPayload = json_decode(file_get_contents(__DIR__ . '/../..' . '/Fixtures/scorecard/run_payload.json'), true);
        $snapArr = json_decode(file_get_contents(__DIR__ . '/../..' . '/Fixtures/scorecard/snapshot.json'), true);
        $expected = json_decode(file_get_contents(__DIR__ . '/../..' . '/Fixtures/scorecard/expected_check.json'), true);

        $this->assertIsArray($runPayload);
        $this->assertIsArray($snapArr);
        $this->assertIsArray($expected);

        $runDto = StrategyRunDto::fromPayloadArray($runPayload, 1, $cfg);
        $snapshot = LiveSnapshotDto::fromArray($snapArr, $cfg, $snapArr['checked_at'] ?? '');

        $tradeDate = (string)($runPayload['trade_date'] ?? '');
        $execDate = (string)(($runPayload['exec_trade_date'] ?? '') ?: ($runPayload['exec_date'] ?? ''));
        $policy = (string)(($runPayload['policy']['selected'] ?? '') ?: ($runPayload['policy'] ?? ''));
        $this->assertNotSame('', $tradeDate);
        $this->assertNotSame('', $execDate);
        $this->assertNotSame('', $policy);

        $service = new WatchlistScorecardService(
            // Repos
            new class($cfg, $runDto, $tradeDate, $execDate, $policy) extends StrategyRunRepository {
                private StrategyRunDto $dto;
                private string $tradeDate;
                private string $execDate;
                private string $policy;
                public function __construct(ScorecardConfig $cfg, StrategyRunDto $dto, string $tradeDate, string $execDate, string $policy) {
                    parent::__construct($cfg);
                    $this->dto = $dto;
                    $this->tradeDate = $tradeDate;
                    $this->execDate = $execDate;
                    $this->policy = $policy;
                }
                public function upsertFromDto(StrategyRunDto $dto, string $source = 'watchlist'): int { $this->dto = $dto; return 1; }
                public function getRunDto(string $tradeDate, string $execDate, string $policy, string $source = 'watchlist'): ?StrategyRunDto {
                    if ($tradeDate === $this->tradeDate && $execDate === $this->execDate && $policy === $this->policy) {
                        return $this->dto;
                    }
                    return null;
                }
            },
            new class extends StrategyCheckRepository {
                public function insertCheckFromDto(int $runId, LiveSnapshotDto $snapshot, EligibilityCheckDto $result): int { return 1; }
                public function getLatestCheckDto(int $runId): ?StrategyCheckDto { return null; }
            },
            new class extends ScorecardRepository {
                public function upsertScorecardFromDto(int $runId, ScorecardMetricsDto $dto): void { /* no-op */ }
            },
            // Evaluator + calculator
            new ExecutionEligibilityEvaluator($cfg),
            new ScorecardMetricsCalculator(),
            // OHLC repo (not needed for check-live)
            new class extends TickerOhlcDailyRepository {
                public function mapOhlcByTickerCodesForDate(string $tradeDate, array $tickerCodes): array { return []; }
            },
            // persist + intraday + ticker repos (not needed for this golden test)
            new class extends WatchlistPersistenceRepository {
                public function getDailySnapshot(string $tradeDate, string $policy, string $source = 'watchlist'): ?array { return null; }
            },
            new class extends IntradaySnapshotRepository {
                public function getLatestSnapshotDtoForTickers(array $tickerCodes, string $date): array { return []; }
            },
            new class extends TickerRepository {
                public function mapTickersByCodes(array $tickerCodes): array { return []; }
            },
            // config + clock
            $cfg,
            $clock
        );

        $dto = $service->checkLiveDto($tradeDate, $execDate, $policy, $snapshot);
        $out = $this->normalize($dto->toArray());
        $expected = $this->normalize($expected);

        $this->assertSame($expected, $out);
    }

    /**
     * Normalize output shape across minor refactors:
     * - computed.gap_pct/spread_pct/chase_pct (preferred) vs flat fields (legacy)
     * - default_recommendation{ticker,why} (preferred) vs recommended_ticker/recommended_why (legacy)
     * - cast numeric fields to float for stable diffs
     *
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function normalize(array $a): array
    {
        // Remove volatile top-level fields
        foreach (['plan_ref'] as $volatile) {
            if (array_key_exists($volatile, $a)) {
                unset($a[$volatile]);
            }
        }

        if (isset($a['results']) && is_array($a['results'])) {
            foreach ($a['results'] as $i => $r) {
                if (!is_array($r)) continue;

                // Drop volatile per-result fields
                foreach (['plan_ref'] as $volatile) {
                    if (array_key_exists($volatile, $r)) {
                        unset($a['results'][$i][$volatile]);
                    }
                }

                // Normalize reasons to codes only (stable)
                if (isset($r['reasons']) && is_array($r['reasons'])) {
                    $codes = [];
                    foreach ($r['reasons'] as $reason) {
                        if (is_array($reason) && isset($reason['code'])) {
                            $codes[] = (string)$reason['code'];
                        } elseif (is_string($reason)) {
                            $codes[] = $reason;
                        }
                    }
                    sort($codes);
                    $a['results'][$i]['reasons'] = $codes;
                }

                // Prefer nested computed; keep only stable keys.
                if (isset($r['computed']) && is_array($r['computed'])) {
                    // Cast stable numerics
                    foreach (['gap_pct', 'spread_pct'] as $k) {
                        if (array_key_exists($k, $r['computed'])) {
                            $a['results'][$i]['computed'][$k] = (float)$r['computed'][$k];
                        }
                    }
                    // Drop volatile/implementation-detail keys
                    foreach (['chase_pct','max_retry_windows','retry_count'] as $k) {
                        if (array_key_exists($k, $a['results'][$i]['computed'])) {
                            unset($a['results'][$i]['computed'][$k]);
                        }
                    }
                } else {
                    // Legacy flat keys -> lift into computed
                    $computed = [];
                    foreach (['gap_pct', 'spread_pct'] as $k) {
                        if (array_key_exists($k, $r)) {
                            $computed[$k] = (float)$r[$k];
                            unset($a['results'][$i][$k]);
                        }
                    }
                    // Drop legacy chase_pct if present
                    if (array_key_exists('chase_pct', $a['results'][$i] ?? [])) {
                        unset($a['results'][$i]['chase_pct']);
                    }
                    if (!empty($computed)) {
                        $a['results'][$i]['computed'] = $computed;
                    }
                }

                // Normalize plan numeric fields.
                if (isset($r['plan']) && is_array($r['plan'])) {
                    foreach (['entry_trigger', 'stop', 'tp1', 'rr_est'] as $k) {
                        if (array_key_exists($k, $r['plan']) && $r['plan'][$k] !== null) {
                            $a['results'][$i]['plan'][$k] = (float)$r['plan'][$k];
                        }
                    }

                    // Docs-strict: plan must not leak implementation details
                    foreach (['guards', 'execution_slices'] as $k) {
                        if (array_key_exists($k, $a['results'][$i]['plan'])) {
                            unset($a['results'][$i]['plan'][$k]);
                        }
                    }
                }

                // Normalize live numeric fields.
                if (isset($r['live']) && is_array($r['live'])) {
                    foreach (['last', 'bid', 'ask', 'open', 'prev_close_plan', 'prev_close_live'] as $k) {
                        if (array_key_exists($k, $r['live'])) {
                            $a['results'][$i]['live'][$k] = (float)$r['live'][$k];
                        }
                    }
                }

                // Normalize recommended orders: keep stable numeric + reason codes only.
                if (isset($r['recommended_orders']) && is_array($r['recommended_orders'])) {
                    foreach ($r['recommended_orders'] as $oi => $ord) {
                        if (!is_array($ord)) continue;

                        // Drop volatile keys
                        foreach (['time_window'] as $k) {
                            if (array_key_exists($k, $a['results'][$i]['recommended_orders'][$oi])) {
                                unset($a['results'][$i]['recommended_orders'][$oi][$k]);
                            }
                        }

                        foreach (['recommended_limit_price', 'plan_limit_price', 'plan_price_cap'] as $k) {
                            if (array_key_exists($k, $ord) && $ord[$k] !== null) {
                                $a['results'][$i]['recommended_orders'][$oi][$k] = (float)$ord[$k];
                            }
                        }

                        // Reasons -> codes only
                        if (isset($ord['reasons']) && is_array($ord['reasons'])) {
                            $codes = [];
                            foreach ($ord['reasons'] as $reason) {
                                if (is_array($reason) && isset($reason['code'])) {
                                    $codes[] = (string)$reason['code'];
                                } elseif (is_string($reason)) {
                                    $codes[] = $reason;
                                }
                            }
                            sort($codes);
                            $a['results'][$i]['recommended_orders'][$oi]['reasons'] = $codes;
                        }

                        // Inputs used: cast numerics and keep stable keys
                        if (isset($ord['inputs_used']) && is_array($ord['inputs_used'])) {
                            $in = $ord['inputs_used'];
                            foreach (['ask_best', 'bid_best'] as $k) {
                                if (array_key_exists($k, $in) && $in[$k] !== null) {
                                    $in[$k] = (float)$in[$k];
                                }
                            }
                            if (array_key_exists('spread_pct', $in)) {
                                $in['spread_pct'] = (float)$in['spread_pct'];
                            }
                            if (array_key_exists('snapshot_age_sec', $in)) {
                                $in['snapshot_age_sec'] = (int)$in['snapshot_age_sec'];
                            }

                            $in = $this->ksortAssocRecursive($in);
                            $a['results'][$i]['recommended_orders'][$oi]['inputs_used'] = $in;
                        }

                        // Canonical key order for assertSame (PHP arrays are order-sensitive).
                        $a['results'][$i]['recommended_orders'][$oi] = $this->ksortAssocRecursive($a['results'][$i]['recommended_orders'][$oi]);
                    }
                }

                // Drop volatile scheduling outputs
                foreach (['next_check_at'] as $k) {
                    if (array_key_exists($k, $a['results'][$i])) {
                        unset($a['results'][$i][$k]);
                    }
                }
            }
        }

        // Recommendation shape
        if (!isset($a['default_recommendation']) && (isset($a['recommended_ticker']) || isset($a['recommended_why']))) {
            $a['default_recommendation'] = [
                'ticker_code' => (string)($a['recommended_ticker'] ?? ''),
                'why' => (string)($a['recommended_why'] ?? ''),
            ];
            unset($a['recommended_ticker'], $a['recommended_why']);
        }

        // Normalize recommendation key naming
        if (isset($a['default_recommendation']) && is_array($a['default_recommendation'])) {
            $dr = $a['default_recommendation'];
            if (isset($dr['ticker']) && !isset($dr['ticker_code'])) {
                $dr['ticker_code'] = (string)$dr['ticker'];
                unset($dr['ticker']);
            }
            // 'why' string is not stable across copy changes; keep only ticker_code.
            if (array_key_exists('why', $dr)) {
                unset($dr['why']);
            }
            $a['default_recommendation'] = $dr;
        }

        return $this->ksortAssocRecursive($a);
    }

    /**
     * Recursively sort associative arrays by key. Keeps numeric-indexed arrays in original order.
     *
     * @param mixed $v
     * @return mixed
     */
    private function ksortAssocRecursive($v)
    {
        if (!is_array($v)) return $v;

        // Recurse first
        foreach ($v as $k => $vv) {
            $v[$k] = $this->ksortAssocRecursive($vv);
        }

        // Determine if associative
        $keys = array_keys($v);
        $isAssoc = array_keys($keys) !== $keys;
        if ($isAssoc) {
            ksort($v);
        }

        return $v;
    }
}
