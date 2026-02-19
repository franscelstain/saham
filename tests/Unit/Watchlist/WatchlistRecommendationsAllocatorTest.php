<?php

namespace Tests\Unit\Watchlist;

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
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Config\WatchlistPolicyConfig;
use App\Trade\Watchlist\Contracts\PolicyDocLocator;
use App\Trade\Watchlist\WatchlistEngine;
use Tests\TestCase;

/**
 * Locked allocator invariants for recommendations (docs/watchlist/watchlist.md).
 *
 * Why this exists:
 * - Fixtures validate contract shape, but do not lock allocator math.
 * - This test locks: weight clamp+renormalize, no-overspend guard, backfill, determinism.
 */
class WatchlistRecommendationsAllocatorTest extends TestCase
{
    public function testWeightsAreClampedAndRenormalized(): void
    {
        $engine = $this->makeEngine();

        $candidates = $this->makeCandidates([
            // score_total is clamped to [0.10..0.60]
            ['code' => 'AAA', 'score' => 0.05, 'entry' => 1000],
            ['code' => 'BBB', 'score' => 0.20, 'entry' => 1000],
            ['code' => 'CCC', 'score' => 0.90, 'entry' => 1000],
        ]);

        $res = $this->invokeBuildRecommendations($engine, $candidates, [0, 1, 2], 10_000_000, 3);
        $allocs = (array)($res['allocations'] ?? []);

        $this->assertCount(3, $allocs);

        // expected clamped raw = [0.10, 0.20, 0.60] => sum 0.90
        $w1 = (float)$allocs[0]['alloc_pct'];
        $w2 = (float)$allocs[1]['alloc_pct'];
        $w3 = (float)$allocs[2]['alloc_pct'];

        $this->assertEqualsWithDelta(0.1111, $w1, 0.001);
        $this->assertEqualsWithDelta(0.2222, $w2, 0.001);
        $this->assertEqualsWithDelta(0.6667, $w3, 0.001);
        $this->assertEqualsWithDelta(1.0, $w1 + $w2 + $w3, 0.0001);
    }

    public function testAllocatorNeverOverspendsAndRemainingIsMonotonic(): void
    {
        $engine = $this->makeEngine();

        // High entry to make fees non-trivial.
        $candidates = $this->makeCandidates([
            ['code' => 'AAA', 'score' => 0.60, 'entry' => 9000],
            ['code' => 'BBB', 'score' => 0.50, 'entry' => 8500],
        ]);

        $capital = 2_000_000;
        $res = $this->invokeBuildRecommendations($engine, $candidates, [0, 1], $capital, 2);
        $allocs = (array)($res['allocations'] ?? []);

        $this->assertNotEmpty($allocs);

        $remaining = $capital;
        foreach ($allocs as $a) {
            $est = (int)($a['estimated_cost'] ?? 0);
            $after = (int)($a['remaining_cash'] ?? 0);

            $this->assertGreaterThan(0, $est);
            // PHPUnit: assertLessThanOrEqual($expected, $actual) asserts $actual <= $expected.
            // We want: est <= remaining.
            $this->assertLessThanOrEqual($remaining, $est, 'estimated_cost must not exceed remaining cash');

            $this->assertSame($remaining - $est, $after);
            $this->assertGreaterThanOrEqual(0, $after);
            $remaining = $after;
        }

        // Top-level cash_remaining must match the last allocation remaining_cash.
        $this->assertSame($remaining, (int)($res['cash_remaining_idr'] ?? ($res['cash_remaining'] ?? -1)));
    }

    public function testBackfillDropsInfeasibleTopPickAndUsesNextRanked(): void
    {
        $engine = $this->makeEngine();

        // AAA is infeasible for min 1 lot under this capital.
        $candidates = $this->makeCandidates([
            ['code' => 'AAA', 'score' => 0.60, 'entry' => 100_000],
            ['code' => 'BBB', 'score' => 0.50, 'entry' => 1000],
            ['code' => 'CCC', 'score' => 0.40, 'entry' => 1000],
        ]);

        $res = $this->invokeBuildRecommendations($engine, $candidates, [0, 1, 2], 250_000, 2);
        $allocs = (array)($res['allocations'] ?? []);

        $this->assertCount(2, $allocs);
        $codes = array_map(fn($x) => (string)($x['ticker_code'] ?? ''), $allocs);

        $this->assertSame(['BBB', 'CCC'], $codes, 'Allocator must backfill with next ranked feasible tickers');
    }

    public function testAllocatorIsDeterministicGivenSameInputs(): void
    {
        $engine = $this->makeEngine();

        $candidates = $this->makeCandidates([
            ['code' => 'AAA', 'score' => 0.55, 'entry' => 5000],
            ['code' => 'BBB', 'score' => 0.45, 'entry' => 4000],
            ['code' => 'CCC', 'score' => 0.35, 'entry' => 3000],
        ]);

        $r1 = $this->invokeBuildRecommendations($engine, $candidates, [0, 1, 2], 3_000_000, 2);
        $r2 = $this->invokeBuildRecommendations($engine, $candidates, [0, 1, 2], 3_000_000, 2);

        $this->assertSame($r1['mode'], $r2['mode']);
        $this->assertSame($r1['allocations'], $r2['allocations']);
        $this->assertSame($r1['cash_remaining_idr'], $r2['cash_remaining_idr']);
    }



    public function testLeftoverDistributionAddsOneLotInRankingOrderWhenFeasible(): void
    {
        $engine = $this->makeEngine();

        $candidates = $this->makeCandidates([
            ['code' => 'AAA', 'score' => 0.60, 'entry' => 1000],
            ['code' => 'BBB', 'score' => 0.50, 'entry' => 1000],
        ]);

        // With these inputs, initial floor should buy 1 lot each, leaving enough cash for +1 lot.
        $capital = 310_000;
        $res = $this->invokeBuildRecommendations($engine, $candidates, [0, 1], $capital, 2);
        $allocs = (array)($res['allocations'] ?? []);

        $this->assertCount(2, $allocs);
        $this->assertSame('AAA', (string)($allocs[0]['ticker_code'] ?? ''));
        $this->assertSame('BBB', (string)($allocs[1]['ticker_code'] ?? ''));

        // Leftover distribution must allocate the extra lot to the top-ranked ticker first.
        $this->assertSame(2, (int)($allocs[0]['lots_recommended'] ?? 0));
        $this->assertSame(1, (int)($allocs[1]['lots_recommended'] ?? 0));

        // remaining cash must be consistent with the last allocation.
        $this->assertSame((int)($allocs[1]['remaining_cash'] ?? -1), (int)($res['cash_remaining_idr'] ?? ($res['cash_remaining'] ?? -2)));
    }
    /**
     * @param array<int,array{code:string,score:float,entry:int}> $rows
     * @return array<int,array<string,mixed>>
     */
    private function makeCandidates(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'ticker_code' => (string)$r['code'],
                'score_total' => (float)$r['score'],
                'setup_type' => 'BREAKOUT',
                'levels' => [
                    'entry_trigger_price' => (int)$r['entry'],
                    'stop_loss_price' => max(1, (int)$r['entry'] - 50),
                    'tp1_price' => (int)$r['entry'] + 50,
                ],
                'derived' => [
                    'atr_pct' => 0.08,
                    'tick_pct' => 0.008,
                ],
                'basis' => [
                    'ca_event' => null,
                    'ca_hint' => null,
                ],
                'plan' => [
                    'hard_lock_codes' => [],
                    'is_eligible_new_entry' => true,
                    'block_codes' => [],
                    'trade_viability' => [
                        'evaluated' => false,
                        'is_viable' => true,
                        'reason_codes' => [],
                    ],
                ],
            ];
        }
        return $out;
    }

    /**
     * Invoke private buildRecommendations() with reflection.
     *
     * @param array<int,array<string,mixed>> $candidates
     * @param int[] $topPickIndices
     * @return array<string,mixed>
     */
    private function invokeBuildRecommendations(
        WatchlistEngine $engine,
        array $candidates,
        array $topPickIndices,
        int $capital,
        int $target
    ): array {
        $policy = 'WEEKLY_SWING';
        $policyMeta = [
            'risk_per_trade_pct' => 0.02,
            'max_positions' => 10,
            'max_positions_today' => $target,
            'size_multiplier' => 1.0,
            'min_lots' => 1,
            'min_alloc_idr' => 0,
        ];

        $globalLocks = [];
        $openPositions = [];

        $m = new \ReflectionMethod(WatchlistEngine::class, 'buildRecommendations');
        $m->setAccessible(true);

        $candRef = $candidates;
        /** @var array<string,mixed> $res */
        $res = $m->invokeArgs($engine, [
            $policy,
            $policyMeta,
            $globalLocks,
            $openPositions,
            $capital,
            &$candRef,
            $topPickIndices,
        ]);

        $this->assertIsArray($res);
        return $res;
    }

    private function makeEngine(): WatchlistEngine
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

        // fees used by estimateBuyTotalCost/maxAffordableLots
        $feeCfg = new FeeConfig(0.0015, 0.0025, 0.0, 0.0, 0.0005);
        $clockCfg = new TradeClockConfig('Asia/Jakarta', 16, 0);
        $scorecardCfg = new ScorecardConfig(false, 0.01, 0.015, 0.004, '09:00', '15:50');
        // Bare stubs; allocator test does not hit DB repos.
        $watchRepo = new class extends WatchlistRepository {
            public function __construct() {}
        };
        $breadthRepo = new class extends MarketBreadthRepository {
            public function __construct() {}
        };
        $calRepo = new class extends MarketCalendarRepository {
            public function __construct() {}
        };
        $divRepo = new class extends DividendEventRepository {
            public function __construct() {}
        };
        $intraRepo = new class extends IntradaySnapshotRepository {
            public function __construct() {}
        };
        $statusRepo = new class extends TickerStatusRepository {
            public function __construct() {}
        };
        $posRepo = new class extends PortfolioPositionRepository {
            public function __construct() {}
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
