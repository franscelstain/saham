<?php

namespace Tests\Feature\Portfolio;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\PortfolioLotMatchRepository;
use App\Repositories\PortfolioLotRepository;
use App\Repositories\PortfolioPlanRepository;
use App\Repositories\PortfolioPositionEventRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\PortfolioTradeRepository;
use App\Repositories\TickerRepository;
use App\Repositories\MarketData\CanonicalEodRepository;
use App\Repositories\MarketData\RunRepository;
use App\Services\Portfolio\PortfolioService;
use App\Trade\Planning\PlanningPolicy;
use App\Trade\Portfolio\Policies\PolicyFactory;
use App\Trade\Pricing\FeeConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortfolioInvariantsTest extends TestCase
{
    public function testNoNegativeLotRemainingAndMatchesNeverExceedBuys(): void
    {
        $this->bootSqliteMemory();
        $this->createMinimalPortfolioSchema();

        // Seed tickers + calendar (support both naming conventions).
        DB::table('tickers')->insert([
            ['ticker_id' => 1, 'ticker_code' => 'BBCA', 'is_deleted' => 0],
        ]);

        DB::table('market_calendar')->insert([
            ['cal_date' => '2026-01-27', 'trade_date' => '2026-01-27', 'is_trading_day' => 1],
        ]);

        $svc = $this->makeService();

        // Two buys, one sell (partial FIFO match).
        $r1 = $svc->ingestTrade([
            'account_id' => 1,
            'ticker_id' => 1,
            'symbol' => 'BBCA',
            'trade_date' => '2026-01-27',
            'side' => 'BUY',
            'qty' => 100,
            'price' => 1000,
            'currency' => 'IDR',
            'external_ref' => 'B1',
        ]);
        $this->assertTrue($r1['ok'], 'BUY#1 failed: ' . json_encode($r1));

        $r2 = $svc->ingestTrade([
            'account_id' => 1,
            'ticker_id' => 1,
            'symbol' => 'BBCA',
            'trade_date' => '2026-01-27',
            'side' => 'BUY',
            'qty' => 100,
            'price' => 1100,
            'currency' => 'IDR',
            'external_ref' => 'B2',
        ]);
        $this->assertTrue($r2['ok'], 'BUY#2 failed: ' . json_encode($r2));

        $r3 = $svc->ingestTrade([
            'account_id' => 1,
            'ticker_id' => 1,
            'symbol' => 'BBCA',
            'trade_date' => '2026-01-27',
            'side' => 'SELL',
            'qty' => 150,
            'price' => 1200,
            'currency' => 'IDR',
            'external_ref' => 'S1',
        ]);
        $this->assertTrue($r3['ok'], 'SELL failed: ' . json_encode($r3));

        // Invariants: remaining_qty never negative and never exceeds qty.
        $lots = DB::table('portfolio_lots')->orderBy('id')->get()->all();
        $this->assertCount(2, $lots);
        foreach ($lots as $lot) {
            $this->assertGreaterThanOrEqual(0, (int)$lot->remaining_qty);
            $this->assertLessThanOrEqual((int)$lot->qty, (int)$lot->remaining_qty);
        }

        // Matches never exceed total buy qty.
        $matched = (int) DB::table('portfolio_lot_matches')->sum('matched_qty');
        $this->assertLessThanOrEqual(200, $matched);
        $this->assertSame(150, $matched);

        // Position should reflect remaining qty = 50.
        $pos = DB::table('portfolio_positions')->where('account_id', 1)->where('ticker_id', 1)->first();
        $this->assertNotNull($pos);
        $this->assertSame(50, (int)$pos->qty);
    }

    private function makeService(): PortfolioService
    {
        $tickerRepo = new TickerRepository();
        $calRepo = new MarketCalendarRepository();
        $tradeRepo = new PortfolioTradeRepository();
        $lotRepo = new PortfolioLotRepository();
        $matchRepo = new PortfolioLotMatchRepository();
        $posRepo = new PortfolioPositionRepository();
        $eventRepo = new PortfolioPositionEventRepository();
        $planRepo = new PortfolioPlanRepository(); // table intentionally not created => tableExists() false

        $fee = new FeeConfig(0.0015, 0.0025, 0.0, 0.0, 0.0);

        // Fake run + canonical price sources (avoid md_* tables).
        $runRepo = new class extends RunRepository {
            public function findLatestSuccessImportRunCoveringDate(string $tradeDate): ?int { return 10; }
            public function findLatestImportRunCoveringDate(string $tradeDate): ?object { return (object)['run_id' => 10, 'status' => 'SUCCESS']; }
            public function findLatestSuccessImportRunAtOrBeforeDate(string $tradeDate): ?object { return (object)['run_id' => 10, 'effective_end_date' => $tradeDate]; }
        };
        $canonRepo = new class extends CanonicalEodRepository {
            public function loadByRunAndDate(int $runId, string $tradeDate, array $tickerIds): array
            {
                $out = [];
                foreach ($tickerIds as $tid) {
                    $out[(int)$tid] = ['close' => 1200.0, 'chosen_source' => 'TEST', 'adj_close' => null];
                }
                return $out;
            }
        };

        $policyFactory = new PolicyFactory($calRepo, []);
        $planningPolicy = new PlanningPolicy('DUMMY', 0, 'ATR', 0, 0, 1, 2, 1, 1);

        return new PortfolioService(
            $tradeRepo,
            $lotRepo,
            $matchRepo,
            $posRepo,
            $eventRepo,
            $planRepo,
            $tickerRepo,
            $runRepo,
            $canonRepo,
            $calRepo,
            $fee,
            $policyFactory,
            $planningPolicy
        );
    }

    private function bootSqliteMemory(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // SQLite lacks GREATEST() by default; portfolio SQL uses it.
        $pdo = DB::connection('sqlite')->getPdo();
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('GREATEST', function ($a, $b) {
                return max((float)$a, (float)$b);
            }, 2);
        }
    }

    private function createMinimalPortfolioSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('tickers', function ($t) {
            $t->integer('ticker_id')->primary();
            $t->string('ticker_code', 16);
            $t->integer('is_deleted')->default(0);
        });

        Schema::create('market_calendar', function ($t) {
            $t->string('cal_date', 10)->primary();
            $t->string('trade_date', 10)->nullable();
            $t->integer('is_trading_day')->default(1);
        });

        Schema::create('portfolio_trades', function ($t) {
            $t->increments('id');
            $t->integer('account_id');
            $t->integer('ticker_id');
            $t->string('symbol', 16)->nullable();
            $t->string('trade_date', 10);
            $t->string('side', 8);
            $t->integer('qty');
            $t->decimal('price', 18, 4);
            $t->decimal('gross_amount', 18, 4)->default(0);
            $t->decimal('fee_amount', 18, 4)->default(0);
            $t->decimal('tax_amount', 18, 4)->default(0);
            $t->decimal('net_amount', 18, 4)->default(0);
            $t->string('external_ref', 64)->nullable();
            $t->string('trade_hash', 128)->nullable();
            $t->string('broker_ref', 64)->nullable();
            $t->string('source', 32)->nullable();
            $t->string('currency', 8)->default('IDR');
            $t->text('meta_json')->nullable();
            $t->timestamps();
        });

        Schema::create('portfolio_lots', function ($t) {
            $t->increments('id');
            $t->integer('account_id');
            $t->integer('ticker_id');
            $t->integer('buy_trade_id');
            $t->string('buy_date', 10);
            $t->integer('qty');
            $t->integer('remaining_qty');
            $t->decimal('unit_cost', 18, 4);
            $t->decimal('total_cost', 18, 4)->default(0);
            $t->decimal('gross_cost', 18, 4)->default(0);
            $t->decimal('fee_cost', 18, 4)->default(0);
            $t->decimal('tax_cost', 18, 4)->default(0);
            $t->decimal('net_cost', 18, 4)->default(0);
            $t->timestamps();
        });

        Schema::create('portfolio_lot_matches', function ($t) {
            $t->increments('id');
            $t->integer('account_id');
            $t->integer('ticker_id');
            $t->integer('sell_trade_id');
            $t->integer('buy_lot_id');
            $t->integer('matched_qty');
            $t->decimal('buy_unit_cost', 18, 4)->default(0);
            $t->decimal('sell_unit_price', 18, 4)->default(0);
            $t->decimal('buy_fee_alloc', 18, 4)->nullable();
            $t->decimal('sell_fee_alloc', 18, 4)->nullable();
            $t->decimal('realized_pnl', 18, 4)->default(0);
            $t->timestamps();
        });

        Schema::create('portfolio_positions', function ($t) {
            $t->increments('id');
            $t->integer('account_id');
            $t->integer('ticker_id');
            $t->string('symbol', 16)->nullable();
            $t->integer('is_open')->default(1);
            $t->string('state', 16)->default('OPEN');
            $t->string('strategy_code', 32)->nullable();
            $t->string('policy_code', 32)->nullable();
            $t->string('entry_date', 10)->nullable();
            $t->integer('position_lots')->default(0);
            $t->integer('qty')->default(0);
            $t->decimal('avg_price', 18, 4)->default(0);
            $t->decimal('realized_pnl', 18, 4)->default(0);
            $t->decimal('unrealized_pnl', 18, 4)->default(0);
            $t->integer('plan_id')->nullable();
            $t->string('plan_version', 64)->nullable();
            $t->string('as_of_trade_date', 10)->nullable();
            $t->text('plan_snapshot_json')->nullable();
            $t->string('last_trade_date', 10)->nullable();
            $t->string('last_valued_date', 10)->nullable();
            $t->decimal('last_price', 18, 4)->nullable();
            $t->decimal('market_value', 18, 4)->default(0);
            $t->timestamps();
        });

        Schema::create('portfolio_position_events', function ($t) {
            $t->increments('id');
            $t->integer('account_id');
            $t->integer('ticker_id');
            $t->string('event_type', 32);

            // Columns used by PortfolioService in current codebase (v5.6+)
            $t->string('as_of_trade_date', 10)->nullable();
            $t->string('plan_version', 64)->nullable();
            $t->string('strategy_code', 32)->nullable();
            $t->integer('qty_before')->nullable();
            $t->integer('qty_after')->nullable();
            $t->decimal('price', 18, 4)->nullable();
            $t->string('reason_code', 32)->nullable();
            $t->string('notes', 255)->nullable();
            $t->text('payload_json')->nullable();

            // Legacy/other versions may write these (keep nullable superset to avoid schema mismatch)
            $t->string('from_state', 16)->nullable();
            $t->string('to_state', 16)->nullable();
            $t->string('trade_date', 10)->nullable();
            $t->text('event_json')->nullable();

            $t->timestamps();
        });
    }
}
