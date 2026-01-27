<?php

namespace Tests\Feature\Portfolio;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\MarketData\CanonicalEodRepository;
use App\Repositories\MarketData\RunRepository;
use App\Repositories\PortfolioLotMatchRepository;
use App\Repositories\PortfolioLotRepository;
use App\Repositories\PortfolioPositionEventRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\PortfolioTradeRepository;
use App\Repositories\PortfolioPlanRepository;
use App\Repositories\TickerRepository;
use App\Services\Portfolio\PortfolioService;
use App\Trade\Portfolio\Policies\PolicyFactory;
use App\Trade\Planning\PlanningPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortfolioServiceIdempotencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootSqliteMemory();
        $this->createMinimalPortfolioSchema();

        // Seed minimal data.
        DB::table('tickers')->insert([
            ['ticker_id' => 1, 'ticker_code' => 'ITMA', 'is_deleted' => 0],
        ]);

        DB::table('market_calendar')->insert([
            // Seed both columns to stay compatible if repository queries either name.
            ['cal_date' => '2026-01-27', 'trade_date' => '2026-01-27', 'is_trading_day' => 1],
        ]);
    }

    public function testIngestTradeIsIdempotentByExternalRef(): void
    {
        $svc = $this->buildPortfolioService();

        $payload = [
            'account_id' => 1,
            'ticker_id' => 1,
            'trade_date' => '2026-01-27',
            'side' => 'BUY',
            'qty' => 100,
            'price' => 1000,
            'currency' => 'IDR',
            'external_ref' => 'EXT-001',
        ];

        $r1 = $svc->ingestTrade($payload);
        $this->assertTrue($r1['ok'], 'first ingest failed: ' . json_encode($r1));
        $this->assertSame(1, (int)DB::table('portfolio_trades')->count());

        // Same external_ref should be treated as duplicate and must not create a second row.
        $r2 = $svc->ingestTrade($payload);
        $this->assertTrue($r2['ok'], 'second ingest failed: ' . json_encode($r2));
        $this->assertSame(1, (int)DB::table('portfolio_trades')->count());
    }

    private function buildPortfolioService(): \App\Services\Portfolio\PortfolioService
    {
        $calRepo = new \App\Repositories\MarketCalendarRepository();

        $fee = new \App\Trade\Pricing\FeeConfig(0.001, 0.001, 0.0, 0.0, 0.0);
        $factory = new \App\Trade\Portfolio\Policies\PolicyFactory($calRepo, []);

        $planning = new \App\Trade\Planning\PlanningPolicy('DUMMY', 0, 'ATR', 0, 0, 1, 2, 1, 1);

        return new \App\Services\Portfolio\PortfolioService(
            new \App\Repositories\PortfolioTradeRepository(),
            new \App\Repositories\PortfolioLotRepository(),
            new \App\Repositories\PortfolioLotMatchRepository(),
            new \App\Repositories\PortfolioPositionRepository(),
            new \App\Repositories\PortfolioPositionEventRepository(),
            new \App\Repositories\PortfolioPlanRepository(),
            new \App\Repositories\TickerRepository(),
            new \App\Repositories\MarketData\RunRepository(),
            new \App\Repositories\MarketData\CanonicalEodRepository(),
            $calRepo,
            $fee,
            $factory,
            $planning
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
            // Support both naming conventions used across versions.
            // Newer code uses `cal_date` (DATE) as the primary key.
            $t->string('cal_date', 10)->primary();
            // Older fixtures sometimes used `trade_date`; keep as optional alias.
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
            $t->string('ticker_code', 16)->nullable();
            $t->integer('is_open')->default(1);
            $t->string('state', 16)->default('OPEN');
            $t->string('strategy_code', 32)->nullable();
            $t->string('policy_code', 32)->nullable();
            $t->string('entry_date', 10)->nullable();
            $t->integer('qty')->default(0);
            $t->decimal('avg_price', 18, 4)->default(0);
            $t->string('last_trade_date', 10)->nullable();
            $t->string('last_valued_date', 10)->nullable();
            $t->integer('position_lots')->default(0);
            $t->decimal('realized_pnl', 18, 4)->default(0);
            $t->decimal('unrealized_pnl', 18, 4)->default(0);
            $t->integer('plan_id')->nullable();
            $t->string('plan_version', 64)->nullable();
            $t->string('as_of_trade_date', 10)->nullable();
            $t->text('plan_snapshot_json')->nullable();
            $t->decimal('last_price', 18, 4)->nullable();
            $t->decimal('market_value', 18, 4)->default(0);
            $t->timestamps();
        });

        Schema::create('portfolio_position_events', function ($t) {
            $t->increments('id');
            $t->integer('account_id');
            $t->integer('ticker_id');
            $t->string('event_type', 32);
            $t->string('as_of_trade_date', 10)->nullable();
            $t->string('plan_version', 64)->nullable();
            $t->string('strategy_code', 32)->nullable();
            $t->integer('qty_before')->nullable();
            $t->integer('qty_after')->nullable();
            $t->decimal('price', 18, 4)->nullable();
            $t->string('reason_code', 32)->nullable();
            $t->string('notes', 255)->nullable();
            $t->text('payload_json')->nullable();
            $t->timestamps();
        });
    }
}
