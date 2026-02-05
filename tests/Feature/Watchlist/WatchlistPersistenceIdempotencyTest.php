<?php

namespace Tests\Feature\Watchlist;

use App\Repositories\WatchlistPersistenceRepository;
use App\Trade\Watchlist\WatchlistPolicyCodes;
use App\Trade\Watchlist\WatchlistSources;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesSqliteInMemory;
use Tests\TestCase;

final class WatchlistPersistenceIdempotencyTest extends TestCase
{
    use UsesSqliteInMemory;

    public function testSaveDailySnapshotIsIdempotentByPolicyTradeDateSource(): void
    {
        $this->bootSqliteInMemory();
        $this->migrateFreshSqlite();

        $repo = $this->app->make(WatchlistPersistenceRepository::class);

        $payloadA = [
            'meta' => [
                'policy' => WatchlistPolicyCodes::WEEKLY_SWING,
                'trade_date' => '2026-01-31',
                'asof_eod_date' => '2026-01-30',
                'canonical_ready' => true,
            ],
            'groups' => [
                'top_picks' => [],
                'secondary' => [],
                'watch_only' => [],
                'avoid' => [],
                'no_trade' => [],
            ],
            'recommendations' => [
                'mode' => 'A',
            ],
        ];

        $payloadB = $payloadA;
        $payloadB['meta']['canonical_ready'] = false;

        $source = WatchlistSources::preopenContract(WatchlistPolicyCodes::WEEKLY_SWING);

        $id1 = $repo->saveDailySnapshot('2026-01-31', $payloadA, $source);
        $id2 = $repo->saveDailySnapshot('2026-01-31', $payloadB, $source);

        $this->assertSame($id1, $id2, 'Id should be stable for same (policy, trade_date, source)');

        $cnt = DB::table('watchlist_daily')
            ->where('policy', WatchlistPolicyCodes::WEEKLY_SWING)
            ->where('trade_date', '2026-01-31')
            ->where('source', $source)
            ->count();

        $this->assertSame(1, (int)$cnt, 'Must not create duplicate rows for same key');

        $row = DB::table('watchlist_daily')->where('watchlist_daily_id', $id1)->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int)$row->canonical_ready, 'Second save should update row');
    }
}
