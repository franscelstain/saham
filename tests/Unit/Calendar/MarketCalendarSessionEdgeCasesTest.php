<?php

namespace Tests\Unit\Calendar;

use App\Repositories\DividendEventRepository;
use App\Repositories\IntradaySnapshotRepository;
use App\Repositories\MarketBreadthRepository;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\TickerStatusRepository;
use App\Repositories\WatchlistRepository;
use App\Trade\Pricing\TickRule;
use App\Trade\Watchlist\CandidateDerivedMetricsBuilder;
use App\Trade\Watchlist\Config\WatchlistPolicyConfig;
use App\Trade\Watchlist\WatchlistEngine;
use App\Trade\Pricing\FeeConfig;
use App\Trade\Support\TradeClockConfig;
use Tests\TestCase;

/**
 * Calendar/session edge cases:
 * - token replacement (open/close)
 * - break subtraction (split windows)
 *
 * We use reflection to hit private helpers for deterministic unit tests.
 */
class MarketCalendarSessionEdgeCasesTest extends TestCase
{
    public function testResolveWindows_replacesOpenCloseTokens(): void
    {
        $engine = $this->makeEngine(new class extends MarketCalendarRepository {
            public function getCalendarRow(string $tradeDate): ?array { return null; }
        });

        $resolve = new \ReflectionMethod($engine, 'resolveWindows');
        $resolve->setAccessible(true);

        $out = $resolve->invoke($engine, ['open-09:15', '15:50-close'], '09:00', '16:00');

        $this->assertSame(['09:00-09:15', '15:50-16:00'], $out);
    }

    public function testSubtractBreaks_splitsOverlappingWindows(): void
    {
        $engine = $this->makeEngine(new class extends MarketCalendarRepository {
            public function getCalendarRow(string $tradeDate): ?array { return null; }
        });

        $subtract = new \ReflectionMethod($engine, 'subtractBreaks');
        $subtract->setAccessible(true);

        $entry = ['09:00-10:00', '13:00-14:00'];
        $breaks = ['09:30-09:45', '13:30-14:30'];

        $out = $subtract->invoke($engine, $entry, $breaks);
        $this->assertSame(['09:00-09:30', '09:45-10:00', '13:00-13:30'], $out);
    }

    public function testSessionForDate_usesCalendarOverridesWithBreaksJson(): void
    {
        $calRepo = new class extends MarketCalendarRepository {
            public function getCalendarRow(string $tradeDate): ?array
            {
                if ($tradeDate !== '2026-01-26') return null;
                return [
                    'session_open_time' => '08:55',
                    'session_close_time' => '15:00',
                    'breaks_json' => json_encode(['11:30-13:30']),
                ];
            }
        };

        $engine = $this->makeEngine($calRepo);
        $sessionForDate = new \ReflectionMethod($engine, 'sessionForDate');
        $sessionForDate->setAccessible(true);

        $sess = $sessionForDate->invoke($engine, '2026-01-26');

        $this->assertSame('08:55', $sess['open']);
        $this->assertSame('15:00', $sess['close']);
        $this->assertSame(['11:30-13:30'], $sess['breaks']);
    }

    private function makeEngine(MarketCalendarRepository $calRepo): WatchlistEngine
    {
        // Only calendar helpers are used in these tests; others can be mocks.
        $watchRepo = $this->createMock(WatchlistRepository::class);
        $breadthRepo = $this->createMock(MarketBreadthRepository::class);
        $divRepo = $this->createMock(DividendEventRepository::class);
        $intraRepo = $this->createMock(IntradaySnapshotRepository::class);
        $statusRepo = $this->createMock(TickerStatusRepository::class);
        $posRepo = $this->createMock(PortfolioPositionRepository::class);

        /** @var TickRule $tickRule */
        $tickRule = app()->make(TickRule::class);
        /** @var FeeConfig $fee */
        $fee = app()->make(FeeConfig::class);
        /** @var TradeClockConfig $clock */
        $clock = app()->make(TradeClockConfig::class);
        /** @var WatchlistPolicyConfig $cfg */
        $cfg = app()->make(WatchlistPolicyConfig::class);
        /** @var CandidateDerivedMetricsBuilder $derived */
        $derived = app()->make(CandidateDerivedMetricsBuilder::class);

        return new WatchlistEngine(
            $watchRepo,
            $breadthRepo,
            $calRepo,
            $divRepo,
            $intraRepo,
            $statusRepo,
            $posRepo,
            $tickRule,
            $fee,
            $clock,
            $cfg,
            $derived
        );
    }
}
