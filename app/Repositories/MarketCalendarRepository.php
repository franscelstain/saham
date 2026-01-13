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
}
