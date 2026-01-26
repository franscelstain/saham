<?php

namespace Tests\Feature\Portfolio;

use App\Services\Portfolio\PortfolioService;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesSqliteInMemory;
use Tests\TestCase;

/**
 * High-urgency lifecycle coverage:
 * - FIFO matching
 * - deterministic state transitions (ENTRY_FILLED -> MANAGED when plan exists)
 * - effective trading day (holiday falls back to previous trading day for valuation)
 */
class PortfolioLifecycleTest extends TestCase
{
    use UsesSqliteInMemory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSqliteInMemory();
        $this->migrateFreshSqlite();
    }

    public function testFifoMatching_stateTransitions_andEffectiveTradingDay(): void
    {
        // Seed ticker
        $tickerId = (int) DB::table('tickers')->insertGetId([
            'ticker_code' => 'BBCA',
            'ticker_name' => 'Bank Central Asia',
            'is_active' => 'Yes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var PortfolioService $svc */
        $svc = app()->make(PortfolioService::class);

        // Create a plan so buy events can attach and enforce deterministic MANAGED transition.
        $plan = $svc->upsertPlan([
            'account_id' => 1,
            'ticker_id' => $tickerId,
            'strategy_code' => 'WEEKLY_SWING',
            'plan_version' => 'v1',
            'as_of_trade_date' => '2026-01-26',
            'entry_price' => 1000,
            'stop_loss_price' => 950,
            'take_profit_price' => 1100,
            'alloc_pct' => 0.5,
        ]);
        $this->assertTrue((bool) ($plan['ok'] ?? false));

        // Two buys -> two lots
        $r1 = $svc->ingestTrade([
            'account_id' => 1,
            'ticker' => 'BBCA',
            'side' => 'BUY',
            'qty' => 100,
            'price' => 1000,
            'trade_date' => '2026-01-26',
        ]);
        $this->assertTrue((bool) ($r1['ok'] ?? false));

        $r2 = $svc->ingestTrade([
            'account_id' => 1,
            'ticker' => 'BBCA',
            'side' => 'BUY',
            'qty' => 100,
            'price' => 1100,
            'trade_date' => '2026-01-26',
        ]);
        $this->assertTrue((bool) ($r2['ok'] ?? false));

        // After ENTRY_FILLED with a plan -> MANAGED (deterministic)
        $pos = DB::table('portfolio_positions')->where('account_id', 1)->where('ticker_id', $tickerId)->first();
        $this->assertNotNull($pos);
        $this->assertSame('MANAGED', (string) $pos->state);
        $this->assertSame(200, (int) $pos->qty);

        // Sell 150 -> FIFO should match first lot fully (100) + second lot partially (50)
        $r3 = $svc->ingestTrade([
            'account_id' => 1,
            'ticker' => 'BBCA',
            'side' => 'SELL',
            'qty' => 150,
            'price' => 1200,
            'trade_date' => '2026-01-26',
        ]);
        $this->assertTrue((bool) ($r3['ok'] ?? false));

        $matches = DB::table('portfolio_lot_matches')
            ->where('account_id', 1)
            ->where('ticker_id', $tickerId)
            ->orderBy('match_id')
            ->get();

        $this->assertCount(2, $matches);
        $this->assertSame(100, (int) $matches[0]->matched_qty);
        $this->assertSame(50, (int) $matches[1]->matched_qty);

        // Remaining open qty should be 50
        $pos2 = DB::table('portfolio_positions')->where('account_id', 1)->where('ticker_id', $tickerId)->first();
        $this->assertSame(50, (int) $pos2->qty);
        $this->assertSame('CLOSING', (string) $pos2->state);

        // PnL sanity (uses default fee config: buy 0.15%, sell 0.25%)
        $totalPnl = (float) DB::table('portfolio_lot_matches')
            ->where('account_id', 1)
            ->where('ticker_id', $tickerId)
            ->sum('realized_pnl');

        // buy1 unit_cost = 1000*(1+0.0015)=1001.5
        // buy2 unit_cost = 1100*(1+0.0015)=1101.65
        // sell net/share = 1200*(1-0.0025)=1197
        // pnl = (1197-1001.5)*100 + (1197-1101.65)*50 = 24317.5
        $this->assertEquals(24317.5, $totalPnl, '', 0.0001);

        // --- Effective trading day ---
        // Mark 2026-01-27 as holiday and 2026-01-26 as trading day.
        DB::table('market_calendar')->insert([
            'trade_date' => '2026-01-26',
            'is_trading_day' => 1,
            'session_open_time' => '09:00',
            'session_close_time' => '16:00',
            'breaks_json' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('market_calendar')->insert([
            'trade_date' => '2026-01-27',
            'is_trading_day' => 0,
            'session_open_time' => null,
            'session_close_time' => null,
            'breaks_json' => null,
            'notes' => 'HOLIDAY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a SUCCESS md_run covering 2026-01-26 and one canonical close.
        $runId = (int) DB::table('md_runs')->insertGetId([
            'job' => 'import_eod',
            'run_mode' => 'FETCH',
            'parent_run_id' => null,
            'raw_source_run_id' => null,
            'timezone' => 'Asia/Jakarta',
            'cutoff' => '16:30',
            'effective_start_date' => '2026-01-26',
            'effective_end_date' => '2026-01-26',
            'last_good_trade_date' => '2026-01-26',
            'target_tickers' => 1,
            'target_days' => 1,
            'expected_points' => 1,
            'canonical_points' => 1,
            'status' => 'SUCCESS',
            'coverage_pct' => 100,
            'fallback_pct' => 0,
            'hard_rejects' => 0,
            'soft_flags' => 0,
            'disagree_major' => 0,
            'missing_trading_day' => 0,
            'notes' => null,
            'started_at' => now(),
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('md_canonical_eod')->insert([
            'run_id' => $runId,
            'ticker_id' => $tickerId,
            'trade_date' => '2026-01-26',
            'chosen_source' => 'TEST',
            'reason' => 'ONLY_SOURCE',
            'flags' => null,
            'open' => 0,
            'high' => 0,
            'low' => 0,
            'close' => 1300,
            'adj_close' => null,
            'volume' => 0,
            'built_at' => now(),
        ]);

        // Valuation requested on holiday should fall back to prev trading day.
        $val = $svc->valueEod('2026-01-27');
        $this->assertTrue((bool) ($val['ok'] ?? false));

        $pos3 = DB::table('portfolio_positions')->where('account_id', 1)->where('ticker_id', $tickerId)->first();
        $this->assertSame('2026-01-26', (string) $pos3->last_valued_date);
        $this->assertEquals(65000.0, (float) $pos3->market_value, '', 0.0001); // 50 * 1300
    }
}
