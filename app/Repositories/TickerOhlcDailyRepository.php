<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TickerOhlcDailyRepository
{
    public function getHistoryRange(string $startDate, string $endDate): array
    {
        return DB::table('ticker_ohlc_daily as d')
            ->select([
                'd.ticker_id', 'd.trade_date',
                'd.open', 'd.high', 'd.low', 'd.close', 'd.volume',
            ])
            ->where('d.is_deleted', 0)
            ->whereBetween('d.trade_date', [$startDate, $endDate])
            ->orderBy('d.ticker_id', 'asc')
            ->orderBy('d.trade_date', 'asc')
            ->get()
            ->all();
    }
}
