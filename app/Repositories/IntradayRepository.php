<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntradayRepository
{
    /**
     * Ambil ticker aktif (atau 1 ticker saja).
     */
    public function getActiveTickers(?string $tickerCode = null): Collection
    {
        $q = DB::table('tickers')
            ->select(['ticker_id','ticker_code'])
            ->where('is_deleted', 0);

        if (!empty($tickerCode)) {
            $q->where('ticker_code', strtoupper(trim($tickerCode)));
        }

        return $q->orderBy('ticker_code')->get();
    }

    public function countActiveTickers(): int
    {
        return (int) DB::table('tickers')
            ->where('is_deleted', 0)
            ->count();
    }

    /**
     * Ambil slice ticker aktif (round-robin).
     */
    public function getActiveTickersSlice(int $offset, int $limit): Collection
    {
        return DB::table('tickers')
            ->select(['ticker_id','ticker_code'])
            ->where('is_deleted', 0)
            ->orderBy('ticker_code')
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Upsert snapshot:
     * - key: (ticker_id, trade_date)
     * - update: snapshot_at + fields lain
     */
    public function upsertSnapshots(array $rows): void
    {
        if (empty($rows)) return;

        DB::table('ticker_intraday')->upsert(
            $rows,
            ['ticker_id', 'trade_date'],
            [
                'snapshot_at',
                'last_bar_at',
                'last_price',
                'volume_so_far',
                'open_price',
                'high_price',
                'low_price',
                'source',
                'is_deleted',
                'updated_at',
            ]
        );
    }

    /**
     * Karena 1 row per ticker per date, cukup ambil semua row date itu.
     */
    public function getLatestSnapshotsByDate(string $tradeDate): Collection
    {
        return DB::table('ticker_intraday')
            ->select([
                'ticker_id',
                'trade_date',
                'snapshot_at',
                'last_bar_at',
                'last_price',
                'volume_so_far',
                'open_price',
                'high_price',
                'low_price',
            ])
            ->where('trade_date', $tradeDate)
            ->where('is_deleted', 0)
            ->get();
    }
}
