<?php

namespace Tests\Unit\Regression;

use App\DTO\Watchlist\PolicyDocCheckResult;
use App\Repositories\DividendEventRepository;
use App\Repositories\MarketBreadthRepository;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\TickerStatusRepository;
use App\Repositories\IntradaySnapshotRepository;
use App\Repositories\WatchlistRepository;
use App\Trade\Pricing\FeeConfig;
use App\Trade\Pricing\TickLadderConfig;
use App\Trade\Pricing\TickRule;
use App\Trade\Support\TradeClockConfig;
use App\Trade\Watchlist\CandidateDerivedMetricsBuilder;
use App\Trade\Watchlist\Config\WatchlistPolicyConfig;
use App\Trade\Watchlist\Contracts\PolicyDocLocator;
use App\Trade\Watchlist\WatchlistEngine;
use Tests\TestCase;

/**
 * Golden master for contract-candidate mapping.
 *
 * This deliberately tests the normalization layer (toContractCandidate)
 * without requiring DB or full WatchlistEngine::build() wiring.
 */
class WatchlistContractCandidateGoldenMasterTest extends TestCase
{
    public function testContractCandidateMappingMatchesFixture(): void
    {
        $engine = $this->makeEngine();

        $row = [
            'ticker_id' => 1,
            'ticker_code' => 'BBCA',
            'rank' => 1,
            'watchlist_score' => 77.5,
            'confidence' => 'High',
            'setup_type' => 'Pullback',

            // Intentionally provide legacy (un-prefixed) reason codes that ARE mappable.
            'reason_codes' => ['GAP_UP_BLOCK', 'EOD_NOT_READY'],
            'debug' => ['rank_reason_codes' => ['WS_RANK_BASE']],
            'ticker_flags' => [],

            'timing' => [
                'entry_windows' => ['open-close'],
                'avoid_windows' => [],
                // empty => should default based on setup_type
                'entry_style' => '',
                'size_multiplier' => 1.0,
                'trade_disabled' => false,
                'trade_disabled_reason' => null,
                'trade_disabled_reason_codes' => [],
            ],

            'levels' => [
                'tick_size' => 10,
                'entry_type' => 'LIMIT',
                'entry_trigger_price' => 1000,
                'be_price' => 1000,
                'stop_loss_price' => 900,
                'tp1_price' => 1200,
                'tp2_price' => 1300,
                'max_chase_from_close_pct' => null,
            ],
            'sizing' => [
                'lot_size' => 100,
                'risk_per_share' => 100,
                'rr_tp2' => 3.0,
                'profit_tp2_net' => 25000,
                'rr_tp2_net' => 2.5,
                'lots_recommended' => 1,
                'estimated_cost' => 1000000,
            ],
            'position' => [
                'has_position' => false,
                'position_avg_price' => null,
                'position_lots' => null,
                'days_held' => null,
                'position_state' => null,
                'action_windows' => [],
                'updated_stop_loss_price' => null,
            ],

            // Empty checklist => engine should inject default 3 items.
            'checklist' => [],
        ];

        $actual = $this->callPrivate($engine, 'toContractCandidate', [$row, 'WEEKLY_SWING']);

        $expected = json_decode(file_get_contents(base_path('tests/Fixtures/watchlist/expected_contract_candidate_ws.json')), true);
        $this->assertSame($expected, $actual);
    }

    public function testContractCandidateMappingIsDeterministic(): void
    {
        $engine = $this->makeEngine();

        $row = [
            'ticker_id' => 1,
            'ticker_code' => 'BBCA',
            'rank' => 1,
            'watchlist_score' => 10.0,
            'confidence' => 'Low',
            'setup_type' => 'Breakout',
            'reason_codes' => ['GAP_UP_BLOCK'],
            'debug' => ['rank_reason_codes' => ['WS_RANK_BASE']],
            'ticker_flags' => [],
            'timing' => ['entry_windows' => ['open-close'], 'avoid_windows' => [], 'entry_style' => '', 'size_multiplier' => 1.0, 'trade_disabled' => false, 'trade_disabled_reason' => null, 'trade_disabled_reason_codes' => []],
            'levels' => ['tick_size' => 10, 'entry_type' => 'LIMIT', 'entry_trigger_price' => 1000, 'be_price' => 1000, 'stop_loss_price' => 900, 'tp1_price' => 1200, 'tp2_price' => 1300, 'max_chase_from_close_pct' => null],
            'sizing' => ['lot_size' => 100, 'risk_per_share' => 100, 'rr_tp2' => 3.0, 'profit_tp2_net' => 25000, 'rr_tp2_net' => 2.5, 'lots_recommended' => 1, 'estimated_cost' => 1000000],
            'position' => ['has_position' => false, 'position_avg_price' => null, 'position_lots' => null, 'days_held' => null, 'position_state' => null, 'action_windows' => [], 'updated_stop_loss_price' => null],
            'checklist' => [],
        ];

        $a = $this->callPrivate($engine, 'toContractCandidate', [$row, 'WEEKLY_SWING']);
        $b = $this->callPrivate($engine, 'toContractCandidate', [$row, 'WEEKLY_SWING']);
        $this->assertSame($a, $b);
    }

    private function makeEngine(): WatchlistEngine
    {
        // Align with actual WatchlistPolicyConfig signature.
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
            0.75
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
        $feeCfg = new FeeConfig(0.0015, 0.0025, 0.0, 0.0, 0.0);
        $clockCfg = new TradeClockConfig('Asia/Jakarta', 16, 0);

        $metricsBuilder = new CandidateDerivedMetricsBuilder($cfg);

        // Minimal stubs (not used by toContractCandidate, but required by constructor).
        $watchRepo = new class extends WatchlistRepository {};
        $breadthRepo = new class extends MarketBreadthRepository {};
        $calRepo = new class extends MarketCalendarRepository {};
        $divRepo = new class extends DividendEventRepository {};
        $intraRepo = new class extends IntradaySnapshotRepository {
            public function snapshotsByTicker(string $tradeDate): array { return []; }
        };
        $statusRepo = new class extends TickerStatusRepository {
            public function statusByTickerAsOf(string $tradeDate): array { return []; }
        };
        $posRepo = new class extends PortfolioPositionRepository {};

        $policyDocs = new class implements PolicyDocLocator {
            public function check(string $policyCode): PolicyDocCheckResult
            {
                return PolicyDocCheckResult::ok($policyCode, null, null);
            }
        };

        // WatchlistEngine constructor ordering differs across versions; reflect to map args.
        $engineClass = WatchlistEngine::class;
        $ctor = new \ReflectionMethod($engineClass, '__construct');
        $params = $ctor->getParameters();
        $args = [];
        foreach ($params as $p) {
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
                case PolicyDocLocator::class: $args[] = $policyDocs; break;
                case CandidateDerivedMetricsBuilder::class: $args[] = $metricsBuilder; break;
                default:
                    // If constructor adds new deps, use defaults where possible.
                    $args[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
                    break;
            }
        }

        /** @var WatchlistEngine */
        return new WatchlistEngine(...$args);
    }

    private function callPrivate(object $obj, string $method, array $args = [])
    {
        $m = new \ReflectionMethod(get_class($obj), $method);
        $m->setAccessible(true);
        return $m->invokeArgs($obj, $args);
    }
}
