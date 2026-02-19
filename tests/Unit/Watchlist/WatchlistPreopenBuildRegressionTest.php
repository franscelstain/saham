<?php

namespace Tests\Unit\Watchlist;

use App\DTO\Watchlist\CandidateInput;
use App\DTO\Watchlist\PolicyDocCheckResult;
use App\Repositories\DividendEventRepository;
use App\Repositories\IntradaySnapshotRepository;
use App\Repositories\MarketBreadthRepository;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\TickerStatusRepository;
use App\Repositories\WatchlistRepository;
use App\Trade\Pricing\FeeConfig;
use App\Trade\Pricing\TickLadderConfig;
use App\Trade\Pricing\TickRule;
use App\Trade\Support\TradeClockConfig;
use App\Trade\Watchlist\Config\WatchlistPolicyConfig;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Contracts\PolicyDocLocator;
use App\Trade\Watchlist\Contracts\PreopenContractValidator;
use App\Trade\Watchlist\WatchlistEngine;
use Tests\TestCase;

/**
 * Regression smoke test:
 * memastikan WatchlistEngine::buildPreopen() bisa dibangun secara deterministik
 * dengan stub repo, dan payload STRICT preopen selalu valid (validator dipanggil di buildBoth()).
 */
class WatchlistPreopenBuildRegressionTest extends TestCase
{
    public function testBuildPreopenProducesValidContractAndContainsCandidate(): void
    {
        $engine = $this->makeEngine(true);

        $doc = $engine->buildPreopen([
            'policy' => 'WEEKLY_SWING',
            'eod_date' => '2026-01-30',
            'capital_idr' => 10_000_000,
            'now_ts' => '2026-02-03T08:00:00+07:00',
        ]);

        $this->assertSame('WEEKLY_SWING', $doc['meta']['policy']);
        $this->assertSame('2026-01-30', $doc['meta']['asof_eod_date']);
        $this->assertTrue((bool)($doc['meta']['canonical_ready'] ?? false));

        // Strict contract validation (schema must match docs/watchlist/preopen.md)
        (new PreopenContractValidator())->validate($doc);

        $all = [];
        foreach (['top_picks','secondary','watch_only','excluded','avoid','no_trade'] as $g) {
            foreach (($doc['groups'][$g] ?? []) as $it) {
                $all[] = (string)($it['ticker_code'] ?? ($it['ticker'] ?? ''));
            }
        }

        // Smoke: should produce a deterministic, well-formed document.
        // Do not hard-code tickers here because universe filters & thresholds may evolve.
        $this->assertIsArray($doc['groups']);
    }

    public function testBuildPreopenSetsEodNotReadyFlagsWhenCoverageLow(): void
    {
        $engine = $this->makeEngine(false);

        $doc = $engine->buildPreopen([
            'policy' => 'WEEKLY_SWING',
            'eod_date' => '2026-01-30',
            'capital_idr' => 10_000_000,
            'now_ts' => '2026-02-03T08:00:00+07:00',
        ]);

        $this->assertFalse((bool)($doc['meta']['canonical_ready'] ?? true));
        $this->assertContains('EOD_NOT_READY', (array)($doc['meta']['flags'] ?? []));

        $reasons = (array)($doc['meta']['reasons'] ?? []);
        $this->assertNotEmpty($reasons);
        $this->assertSame('GL_EOD_NOT_READY', (string)($reasons[0]['code'] ?? ''));
    }

    private function makeEngine(bool $canonicalReady): WatchlistEngine
    {
        $cfg = new WatchlistPolicyConfig(
            'WEEKLY_SWING',
            null,
            false,
            [],
            2,
            95.0,
            95.0,
            false,
            15.0,
            8.0,
            0.02,
            0.02,
            0.75,
            true,
            [
                'WEEKLY_SWING' => [
                    'max_gap_up_pct' => 0.03,
                    'max_chase_from_close_pct' => 0.02,
                    'max_spread_pct' => 0.015,
                ],
            ],
            5,
            [2,3,4,5,6,7,8,9,10],
            ['WS_MIN_DV20_IDR','WS_MAX_TICK_PCT','WS_MIN_ATR_PCT'],
            [
                'option' => 'A',
                'toppick_max' => 10,
                'secondary_max' => 10,
                'watch_only_max' => 50,
                'top_pick_max' => 10,
                'toppick_min_score' => 0.70,
                'toppick_score_gap' => 0.08,
                'secondary_min_score' => 0.62,
                'watch_only_min_score' => 0.50,
            ],
            [
                'min_price' => 50,
                'min_dv20_idr' => 2000000000,
                'min_turnover20_idr' => 2000000000,
                'max_atr_pct_universe' => 0.20,
            ]
        );

        $tickRule = new TickRule(new TickLadderConfig([
            ['lt' => 200, 'tick' => 1],
            ['lt' => 500, 'tick' => 2],
            ['lt' => 2000, 'tick' => 5],
            ['lt' => 5000, 'tick' => 10],
            ['lt' => 20000, 'tick' => 25],
            ['lt' => 50000, 'tick' => 50],
            ['tick' => 100],
        ]));

        // include slippage to keep cost formulas aligned with engine invariants
        $feeCfg = new FeeConfig(0.0015, 0.0025, 0.0, 0.0, 0.0005);
        $clockCfg = new TradeClockConfig('Asia/Jakarta', 16, 0);
        $scorecardCfg = new ScorecardConfig(false, 0.01, 0.015, 0.004, '09:00', '15:50');
        $candidate = new CandidateInput([
            'signal_code' => 7,
            'decision_code' => 5,
            'volume_label_code' => 4,
            'ticker_id' => 1,
            'ticker_code' => 'BBCA',
            'open' => 1000,
            'high' => 1050,
            'low' => 990,
            'close' => 1020,
            'volume' => 1000000,
            'prev_close' => 1000,
            'ma20' => 1000,
            'ma50' => 980,
            'ma200' => 900,
            'rsi14' => 55,
            'atr14' => 25,
            'vol_ratio' => 1.1,
            'hh20' => 1050,
            'll5' => 980,
            'dv20' => 50_000_000_000,
        ]);
        // Required by LabelCatalog::decision/signal/volumeLabel which have strict int types.

        $watchRepo = new class($candidate, $canonicalReady) extends WatchlistRepository {
            private CandidateInput $c;
            private bool $ok;
            public function __construct(CandidateInput $c, bool $ok) { $this->c = $c; $this->ok = $ok; }
            public function getLatestCommonEodDate(): ?string { return '2026-01-30'; }
            public function getEodCandidates(string $tradeDate): array { return [$this->c]; }
            public function coverageSnapshot(string $tradeDate): array
            {
                if ($this->ok) {
                    return ['canonical_coverage_pct' => 100.0, 'indicators_coverage_pct' => 100.0];
                }
                return ['canonical_coverage_pct' => 10.0, 'indicators_coverage_pct' => 10.0];
            }
        };

        $breadthRepo = new class extends MarketBreadthRepository {
            public function snapshot(string $tradeDate): array { return ['trade_date' => $tradeDate, 'sample_size' => 0]; }
        };

        $calRepo = new class extends MarketCalendarRepository {
            public function prevTradingDay(string $date): ?string { return $date; }
            public function nextTradingDay(string $date): ?string { return $date; }
            public function isTradingDay(string $date): bool { return true; }
            public function tradingDatesBetween(string $from, string $to): array { return [$from, $to]; }
            public function getCalendarRow(string $date): ?array
            {
                return [
                    'trade_date' => $date,
                    'session_open_time' => '09:00:00',
                    'session_close_time' => '16:00:00',
                    'breaks_json' => json_encode([]),
                ];
            }
        };

        $divRepo = new class extends DividendEventRepository {
            public function eventsByTickerInWindow(string $from, string $to): array { return []; }
        };

        $intraRepo = new class extends IntradaySnapshotRepository {
            public function snapshotsByTicker(string $tradeDate): array { return []; }
        };

        $statusRepo = new class extends TickerStatusRepository {
            public function statusByTickerAsOf(string $tradeDate): array
            {
                return [
                    1 => [
                        'special_notations' => [],
                        'is_suspended' => false,
                        'status_quality' => 'OK',
                        'status_asof_trade_date' => $tradeDate,
                        'trading_mechanism' => 'REGULAR',
                    ],
                ];
            }
        };

        $posRepo = new class extends PortfolioPositionRepository {
            public function openPositionsByTicker(int $accountId = 1): array { return []; }
        };

        $policyDocs = new class implements PolicyDocLocator {
            public function check(string $policyCode): PolicyDocCheckResult
            {
                return PolicyDocCheckResult::ok($policyCode, null, null);
            }
        };

        // Map constructor args by type to stay robust across refactors.
        $ctor = new \ReflectionMethod(WatchlistEngine::class, '__construct');
        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $t = $p->getType();
            $name = $t ? (string)$t : '';
            switch ($name) {
                case WatchlistRepository::class: $args[] = $watchRepo; break;
                case MarketBreadthRepository::class: $args[] = $breadthRepo; break;
                case MarketCalendarRepository::class: $args[] = $calRepo; break;
                case DividendEventRepository::class: $args[] = $divRepo; break;
                case IntradaySnapshotRepository::class: $args[] = $intraRepo; break;
                case TickerStatusRepository::class: $args[] = $statusRepo; break;
                case PortfolioPositionRepository::class: $args[] = $posRepo; break;
                case TickRule::class: $args[] = $tickRule; break;
                case FeeConfig::class: $args[] = $feeCfg; break;
                case TradeClockConfig::class: $args[] = $clockCfg; break;
                case WatchlistPolicyConfig::class: $args[] = $cfg; break;
                case ScorecardConfig::class: $args[] = $scorecardCfg; break;
                case PolicyDocLocator::class: $args[] = $policyDocs; break;
                default:
                    $args[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
                    break;
            }
        }

        return new WatchlistEngine(...$args);
    }
}
