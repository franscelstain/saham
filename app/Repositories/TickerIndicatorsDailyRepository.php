<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TickerIndicatorsDailyRepository
{
    public function getPrevSnapshot(int $tickerId, string $prevTradeDate)
    {
        return DB::table('ticker_indicators_daily')
            ->select([
                'ticker_id', 'trade_date',
                'decision_code',
                'signal_first_seen_date',
                'signal_age_days',
            ])
            ->where('ticker_id', $tickerId)
            ->where('trade_date', $prevTradeDate)
            ->first();
    }

    public function upsert(array $row): void
    {
        DB::table('ticker_indicators_daily')->upsert(
            [$row],
            ['ticker_id', 'trade_date'],
            array_keys($row)
        );
    }
}
