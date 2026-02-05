<?php

namespace Tests\Feature\Watchlist;

use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\Repositories\WatchlistPersistenceRepository;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Services\Watchlist\WatchlistScorecardService;
use App\Trade\Watchlist\WatchlistPolicyCodes;
use App\Trade\Watchlist\WatchlistSources;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesSqliteInMemory;
use Tests\TestCase;

final class WatchlistScorecardBootstrapE2ETest extends TestCase
{
    use UsesSqliteInMemory;

    public function testCheckLiveCanBootstrapRunFromPersistedPreopenContract(): void
    {
        $this->bootSqliteInMemory();
        $this->migrateFreshSqlite();

        // Seed minimal tickers + EOD bars required by scorecard calculators.
        DB::table('tickers')->insert([
            ['ticker_id' => 1, 'ticker_code' => 'BBCA', 'company_name' => 'Bank Central Asia', 'company_logo' => null, 'is_deleted' => 0],
            ['ticker_id' => 2, 'ticker_code' => 'ASII', 'company_name' => 'Astra International', 'company_logo' => null, 'is_deleted' => 0],
        ]);

        DB::table('ticker_ohlc_daily')->insert([
            [
                'ticker_id' => 1,
                'run_id' => null,
                'trade_date' => '2026-01-30',
                'open' => 10050,
                'high' => 10200,
                'low' => 9950,
                'close' => 10000,
                'adj_close' => 10000,
                'price_basis' => 'close',
                'volume' => 1000000,
                'ca_hint' => null,
                'ca_event' => null,
                'source' => 'test',
                'is_deleted' => 0,
            ],
            [
                'ticker_id' => 2,
                'run_id' => null,
                'trade_date' => '2026-01-30',
                'open' => 6030,
                'high' => 6050,
                'low' => 5950,
                'close' => 6000,
                'adj_close' => 6000,
                'price_basis' => 'close',
                'volume' => 2000000,
                'ca_hint' => null,
                'ca_event' => null,
                'source' => 'test',
                'is_deleted' => 0,
            ],
        ]);

        $persist = $this->app->make(WatchlistPersistenceRepository::class);

        $payloadPath = base_path('tests/Fixtures/watchlist/preopen_weekly_swing_with_capital.json');
        $payload = json_decode((string)file_get_contents($payloadPath), true);
        $this->assertIsArray($payload);

        $source = WatchlistSources::preopenContract(WatchlistPolicyCodes::WEEKLY_SWING);

        // Persist same way WatchlistService does: key uses exec_date (= meta.trade_date).
        $dailyId = $persist->saveDailySnapshot('2026-01-31', $payload, $source);
        $persist->saveCandidatesFromContract($dailyId, (array)($payload['meta'] ?? []), (array)($payload['groups'] ?? []));

        // Live snapshot input
        $snapshotPath = base_path('tests/Fixtures/scorecard/snapshot.json');
        $snapArr = json_decode((string)file_get_contents($snapshotPath), true);
        $this->assertIsArray($snapArr);

        /** @var ScorecardConfig $cfg */
        $cfg = $this->app->make(ScorecardConfig::class);
        $snapDto = LiveSnapshotDto::fromArray($snapArr, $cfg, '2026-01-30T09:05:00+07:00');

        /** @var WatchlistScorecardService $svc */
        $svc = $this->app->make(WatchlistScorecardService::class);

        // tradeDate = asof_eod_date, execDate = trade_date
        $result = $svc->checkLiveDto('2026-01-30', '2026-01-31', WatchlistPolicyCodes::WEEKLY_SWING, $snapDto, $source);

        $this->assertNotEmpty($result->results, 'Must produce at least one eligibility result');

        // Ensure strategy_run is bootstrapped and persisted.
        $runCnt = DB::table('watchlist_strategy_runs')
            ->where('trade_date', '2026-01-30')
            ->where('exec_trade_date', '2026-01-31')
            ->where('policy', WatchlistPolicyCodes::WEEKLY_SWING)
            ->where('source', $source)
            ->count();

        $this->assertSame(1, (int)$runCnt, 'Strategy run must exist after check-live bootstrap');

        // Ensure check row persisted.
        $checkCnt = DB::table('watchlist_strategy_checks')
            ->where('trade_date', '2026-01-30')
            ->where('exec_trade_date', '2026-01-31')
            ->where('policy', WatchlistPolicyCodes::WEEKLY_SWING)
            ->where('source', $source)
            ->count();

        $this->assertGreaterThanOrEqual(1, (int)$checkCnt, 'Strategy check must be persisted');
    }
}
