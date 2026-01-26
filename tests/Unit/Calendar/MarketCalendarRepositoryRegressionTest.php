<?php

namespace Tests\Unit\Calendar;

use App\Repositories\MarketCalendarRepository;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ReflectionMethod;

class MarketCalendarRepositoryRegressionTest extends TestCase
{
    public function testPreviousTradingDateSkipsNonTradingDaysAndHolidays(): void
    {
        $this->bootSqliteMemory();
        $this->installSchema();

        DB::table('market_calendar')->insert([
            // Support both naming conventions used across versions.
            ['cal_date' => '2026-01-24', 'trade_date' => '2026-01-24', 'is_trading_day' => 1],
            ['cal_date' => '2026-01-25', 'trade_date' => '2026-01-25', 'is_trading_day' => 0],
            ['cal_date' => '2026-01-26', 'trade_date' => '2026-01-26', 'is_trading_day' => 0],
            ['cal_date' => '2026-01-27', 'trade_date' => '2026-01-27', 'is_trading_day' => 1],
        ]);

        $repo = new MarketCalendarRepository();

        $prev = $this->callPreviousTradingDate($repo, '2026-01-27');
        $this->assertSame('2026-01-24', $prev);

        $prev2 = $this->callPreviousTradingDate($repo, '2026-01-26');
        $this->assertSame('2026-01-24', $prev2);
    }

    public function testPreviousTradingDateReturnsNullWhenNoEarlierTradingDay(): void
    {
        $this->bootSqliteMemory();
        $this->installSchema();

        DB::table('market_calendar')->insert([
            ['cal_date' => '2026-01-27', 'trade_date' => '2026-01-27', 'is_trading_day' => 1],
        ]);

        $repo = new MarketCalendarRepository();
        $prev = $this->callPreviousTradingDate($repo, '2026-01-27');
        $this->assertNull($prev);
    }

    private function callPreviousTradingDate(MarketCalendarRepository $repo, string $date): ?string
    {
        // Different TradeAxis versions have different method signatures:
        // - previousTradingDate(string $date)
        // - previousTradingDate(string $date, int $accountId)
        $m = new ReflectionMethod($repo, 'previousTradingDate');
        $n = $m->getNumberOfParameters();
        if ($n >= 2) {
            return $repo->previousTradingDate($date, 1);
        }

        return $repo->previousTradingDate($date);
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

    private function installSchema(): void
    {
        // Support both naming conventions used across versions.
        // Newer code uses `cal_date` as the PK; older fixtures sometimes use `trade_date`.
        DB::statement('CREATE TABLE market_calendar (cal_date TEXT PRIMARY KEY, trade_date TEXT NULL, is_trading_day INTEGER, session_open_time TEXT NULL, session_close_time TEXT NULL, breaks_json TEXT NULL)');
    }
}
