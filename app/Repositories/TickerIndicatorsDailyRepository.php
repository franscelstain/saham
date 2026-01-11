<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TickerIndicatorsDailyRepository
{
    public function getPrevSignalStateMap(string $prevTradeDate): array
    {
        $rows = DB::table('ticker_indicators_daily')
            ->where('is_deleted', 0)
            ->where('trade_date', $prevTradeDate)
            ->select(['ticker_id', 'signal_code', 'signal_first_seen_date'])
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->ticker_id] = [
                'signal_code' => (int) ($r->signal_code ?? 0),
                'signal_first_seen_date' => $r->signal_first_seen_date ? (string) $r->signal_first_seen_date : null,
            ];
        }

        return $map;
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
