<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class MarketCalendarRepository
{
    public function isTradingDay(string $date): bool
    {
        $row = DB::table('market_calendar')
            ->select(['cal_date', 'is_trading_day'])
            ->where('cal_date', $date)
            ->first();

        if (!$row) return false;
        return ((int)$row->is_trading_day) === 1;
    }

    public function previousTradingDate(string $date): ?string
    {
        $row = DB::table('market_calendar')
            ->select(['cal_date'])
            ->where('is_trading_day', 1)
            ->where('cal_date', '<', $date)
            ->orderByDesc('cal_date')
            ->first();

        return $row ? $row->cal_date : null;
    }

    /**
     * @return string[] trade dates (YYYY-MM-DD) for trading days only
     */
    public function tradingDatesBetween(string $from, string $to): array
    {
        $rows = DB::table('market_calendar')
            ->where('is_trading_day', 1)
            ->where('cal_date', '>=', $from)
            ->where('cal_date', '<=', $to)
            ->orderBy('cal_date')
            ->pluck('cal_date');

        $out = [];
        foreach ($rows as $d) $out[] = (string) $d;
        return $out;
    }

    /**
     * Get start date by N trading days lookback including endDate.
     */
    public function lookbackStartDate(string $endDate, int $n): string
    {
        $n = max(1, (int) $n);

        $rows = DB::table('market_calendar')
            ->select(['cal_date'])
            ->where('is_trading_day', 1)
            ->where('cal_date', '<=', $endDate)
            ->orderByDesc('cal_date')
            ->limit($n)
            ->get();

        if ($rows->count() === 0) return $endDate;

        $last = $rows->last();
        return (string) $last->cal_date;
    }
}
