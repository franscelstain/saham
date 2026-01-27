<?php

namespace Tests\Unit\Calendar;

use App\Repositories\MarketCalendarRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketCalendarRepositoryMoreCasesTest extends TestCase
{
    public function testPreviousTradingDateCanSkipConsecutiveHolidaysAndWeekend(): void
    {
        $this->bootSqliteMemory();
        $this->createSchema();

        // 2026-01-23 Fri trading
        // 2026-01-24 Sat non-trading
        // 2026-01-25 Sun non-trading
        // 2026-01-26 Mon holiday (non-trading)
        // 2026-01-27 Tue trading
        DB::table('market_calendar')->insert([
            ['cal_date' => '2026-01-23', 'trade_date' => '2026-01-23', 'is_trading_day' => 1],
            ['cal_date' => '2026-01-24', 'trade_date' => null, 'is_trading_day' => 0],
            ['cal_date' => '2026-01-25', 'trade_date' => null, 'is_trading_day' => 0],
            ['cal_date' => '2026-01-26', 'trade_date' => null, 'is_trading_day' => 0],
            ['cal_date' => '2026-01-27', 'trade_date' => '2026-01-27', 'is_trading_day' => 1],
        ]);

        $repo = new MarketCalendarRepository();
        $prev = $repo->previousTradingDate('2026-01-27', 1);

        $this->assertSame('2026-01-23', $prev);
    }

    public function testPreviousTradingDateWorksEvenIfQueryDateMissingFromCalendarTable(): void
    {
        $this->bootSqliteMemory();
        $this->createSchema();

        DB::table('market_calendar')->insert([
            ['cal_date' => '2026-01-24', 'trade_date' => '2026-01-24', 'is_trading_day' => 1],
            ['cal_date' => '2026-01-25', 'trade_date' => null, 'is_trading_day' => 0],
        ]);

        $repo = new MarketCalendarRepository();

        // 2026-01-26 isn't inserted; should still find 2026-01-24.
        $prev = $repo->previousTradingDate('2026-01-26', 1);
        $this->assertSame('2026-01-24', $prev);
    }

    public function testPreviousTradingDateReturnsNullWhenCalendarEmpty(): void
    {
        $this->bootSqliteMemory();
        $this->createSchema();

        $repo = new MarketCalendarRepository();
        $this->assertNull($repo->previousTradingDate('2026-01-27', 1));
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
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();
        Schema::create('market_calendar', function ($t) {
            $t->string('cal_date', 10)->primary();
            $t->string('trade_date', 10)->nullable();
            $t->integer('is_trading_day')->default(1);
        });
    }
}
