<?php

namespace App\Repositories;

use App\Repositories\Contracts\TickerOhlcRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TickerOhlcRepository implements TickerOhlcRepositoryInterface
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

    public function getLatestOhlcDate(): ?string
    {
        return DB::table('ticker_ohlc_daily')->max('trade_date');
    }

    public function upsertOhlcDaily(array $rows): void
    {
        if (empty($rows)) return;

        DB::table('ticker_ohlc_daily')->upsert(
            $rows,
            ['ticker_id', 'trade_date'],
            ['open','high','low','close','adj_close','volume','source','is_deleted','updated_at']
        );
    }
}
