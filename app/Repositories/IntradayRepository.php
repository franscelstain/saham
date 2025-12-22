<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntradayRepository
{
    public function getActiveTickers(?string $tickerCode = null): Collection
    {
        $q = DB::table('tickers')
            ->select('ticker_id', 'ticker_code')
            ->where('is_deleted', 0)
            ->orderBy('ticker_id');

        if ($tickerCode) {
            $q->where('ticker_code', strtoupper(trim($tickerCode)));
        }

        return $q->get();
    }

    /**
     * Simpan snapshot (upsert supaya idempotent kalau snapshot_at sama).
     * BUTUH unique key: (ticker_id, snapshot_at) atau (ticker_id, trade_date, snapshot_at).
     */
    public function upsertSnapshots(array $rows): void
    {
        if (empty($rows)) return;

        DB::table('ticker_intraday')->upsert(
            $rows,
            ['ticker_id', 'trade_date'],
            ['snapshot_at','last_price','volume_so_far','open_price','high_price','low_price','source','is_deleted','updated_at']
        );
    }
}
