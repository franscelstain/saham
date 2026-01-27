<?php

namespace Tests\Unit\Contracts;

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
 * Invariant-level contract checks for a single watchlist candidate.
 *
 * These tests are intentionally resilient to minor copy changes: they validate
 * shape and invariants rather than hard-coding full golden outputs.
 */
class WatchlistContractCandidateInvariantsTest extends TestCase
{
    public function testReasonCodesArePrefixedAndDebugKeepsRawCodes(): void
    {
        $engine = $this->makeEngine();

        $row = $this->baseRow();
        $row['reason_codes'] = ['GAP_UP_BLOCK', 'EOD_NOT_READY'];

        $c = $this->callPrivate($engine, 'toContractCandidate', [$row, 'WEEKLY_SWING']);

        $this->assertIsArray($c['reason_codes']);
        $this->assertNotEmpty($c['reason_codes']);

        foreach ($c['reason_codes'] as $code) {
            $this->assertIsString($code);
            $this->assertMatchesRegularExpression('/^(WS_|GL_)/', $code, "Reason code must be prefixed: $code");
        }

        $this->assertIsArray($c['debug']);
        $this->assertArrayHasKey('rank_reason_codes', $c['debug']);
        $this->assertContains('GAP_UP_BLOCK', $c['debug']['rank_reason_codes']);
        $this->assertContains('EOD_NOT_READY', $c['debug']['rank_reason_codes']);
    }

    public function testDefaultChecklistIsInjectedWhenEmpty(): void
    {
        $engine = $this->makeEngine();

        $row = $this->baseRow();
        $row['checklist'] = [];

        $c = $this->callPrivate($engine, 'toContractCandidate', [$row, 'WEEKLY_SWING']);

        $this->assertIsArray($c['checklist']);
        $this->assertCount(3, $c['checklist']);
        foreach ($c['checklist'] as $item) {
            $this->assertIsString($item);
            $this->assertNotSame('', trim($item));
        }
    }

    public function testEntryStyleDefaultsWhenBlank(): void
    {
        $engine = $this->makeEngine();

        $row = $this->baseRow();
        $row['timing']['entry_style'] = '';

        $c = $this->callPrivate($engine, 'toContractCandidate', [$row, 'WEEKLY_SWING']);
        $this->assertIsArray($c['timing']);
        $this->assertArrayHasKey('entry_style', $c['timing']);
        $this->assertNotSame('', trim((string)$c['timing']['entry_style']));
    }

    public function testPullbackCandidateHasEntryLimitRange(): void
    {
        $engine = $this->makeEngine();

        $row = $this->baseRow();
        $row['setup_type'] = 'Pullback';

        $c = $this->callPrivate($engine, 'toContractCandidate', [$row, 'WEEKLY_SWING']);

        $this->assertIsArray($c['levels']);
        $this->assertArrayHasKey('entry_limit_low', $c['levels']);
        $this->assertArrayHasKey('entry_limit_high', $c['levels']);

        // Some engines emit [low, high] or [high, low] depending on setup.
        // Invariant we really want: the trigger price lies within the band.
        $low = (float)$c['levels']['entry_limit_low'];
        $high = (float)$c['levels']['entry_limit_high'];
        $trigger = (float)$c['levels']['entry_trigger_price'];
        $lo = min($low, $high);
        $hi = max($low, $high);

        // PHPUnit signature is (expected, actual).
        // Assert lo <= hi.
        $this->assertLessThanOrEqual($hi, $lo);
        // Assert trigger within [lo, hi].
        $this->assertGreaterThanOrEqual($lo - 1e-9, $trigger);
        $this->assertLessThanOrEqual($hi + 1e-9, $trigger);
    }

    public function testSizingContainsRequiredFields(): void
    {
        $engine = $this->makeEngine();
        $c = $this->callPrivate($engine, 'toContractCandidate', [$this->baseRow(), 'WEEKLY_SWING']);

        $this->assertIsArray($c['sizing']);
        foreach (['lot_size', 'slices', 'slice_pct', 'lots_recommended', 'estimated_cost', 'profit_tp2_net', 'rr_tp2_net'] as $k) {
            $this->assertArrayHasKey($k, $c['sizing']);
        }
    }

    public function testPositionSectionIsWellFormed(): void
    {
        $engine = $this->makeEngine();
        $c = $this->callPrivate($engine, 'toContractCandidate', [$this->baseRow(), 'WEEKLY_SWING']);

        $this->assertIsArray($c['position']);
        $this->assertArrayHasKey('has_position', $c['position']);
        $this->assertFalse((bool)$c['position']['has_position']);
        $this->assertIsArray($c['position']['action_windows']);
    }

    private function baseRow(): array
    {
        return [
            'ticker_id' => 1,
            'ticker_code' => 'BBCA',
            'rank' => 1,
            'watchlist_score' => 77.5,
            'confidence' => 'High',
            'setup_type' => 'Pullback',
            'reason_codes' => ['GAP_UP_BLOCK'],
            'debug' => ['rank_reason_codes' => ['WS_RANK_BASE']],
            'ticker_flags' => [],
            'timing' => [
                'entry_windows' => ['open-close'],
                'avoid_windows' => [],
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
            'checklist' => [],
        ];
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

        $watchRepo = new class extends WatchlistRepository {};
        $breadthRepo = new class extends MarketBreadthRepository {};
        $calRepo = new class extends MarketCalendarRepository {};
        $divRepo = new class extends DividendEventRepository {};
        $intraRepo = new class extends IntradaySnapshotRepository { public function snapshotsByTicker(string $tradeDate): array { return []; } };
        $statusRepo = new class extends TickerStatusRepository { public function statusByTickerAsOf(string $tradeDate): array { return []; } };
        $posRepo = new class extends PortfolioPositionRepository {};

        $policyDocs = new class implements PolicyDocLocator {
            public function check(string $policyCode): PolicyDocCheckResult
            {
                return PolicyDocCheckResult::ok($policyCode, null, null);
            }
        };

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
                case PolicyDocLocator::class: $args[] = $policyDocs; break;
                case CandidateDerivedMetricsBuilder::class: $args[] = $metricsBuilder; break;
                default:
                    $args[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
                    break;
            }
        }

        return new WatchlistEngine(...$args);
    }

    private function callPrivate(object $obj, string $method, array $args = [])
    {
        $m = new \ReflectionMethod(get_class($obj), $method);
        $m->setAccessible(true);
        return $m->invokeArgs($obj, $args);
    }
}
