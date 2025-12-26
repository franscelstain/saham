<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TickerOhlcRepository
{
    /**
     * Ambil ticker aktif (is_deleted = 0).
     * Kalau $tickerCode diisi, hanya 1 ticker itu.
     */
    public function getActiveTickers(?string $tickerCode = null): Collection
    {
        $q = DB::table('tickers')
            ->select(['ticker_id', 'ticker_code'])
            ->where('is_deleted', 0);

        if ($tickerCode !== null && trim($tickerCode) !== '') {
            $code = strtoupper(trim($tickerCode));
            $q->where('ticker_code', $code);
        }

        return $q->orderBy('ticker_id')->get();
    }

    /**
     * Upsert OHLC daily.
     * Unique key di tabel: (ticker_id, trade_date)
     */
    public function upsertDailyBars(array $rows): void
    {
        if (empty($rows)) return;

        DB::table('ticker_ohlc_daily')->upsert(
            $rows,
            ['ticker_id', 'trade_date'],
            ['open','high','low','close','adj_close','volume','source','is_deleted','updated_at']
        );
    }

    public function getLatestTradeDate(): ?string
    {
        return DB::table('ticker_ohlc_daily')
            ->where('is_deleted', 0)
            ->max('trade_date');
    }

    public function getLatestTradeDateByTicker(int $tickerId): ?string
    {
        return DB::table('ticker_ohlc_daily')
            ->where('is_deleted', 0)
            ->where('ticker_id', $tickerId)
            ->max('trade_date');
    }
}
