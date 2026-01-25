<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class MarketCalendarRepository
{
    public function tableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('market_calendar');
        } catch (\Throwable $e) {
            return false;
        }
    }


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

    // Alias for older naming
    public function prevTradingDay(string $date): ?string
    {
        return $this->previousTradingDate($date);
    }

    public function nextTradingDay(string $date): ?string
    {
        $row = DB::table('market_calendar')
            ->select(['cal_date'])
            ->where('is_trading_day', 1)
            ->where('cal_date', '>', $date)
            ->orderBy('cal_date')
            ->first();

        return $row ? (string)$row->cal_date : null;
    }

    /**
     * Add N trading days from a start date (start date excluded).
     * For n=0 returns start date.
     */
    public function addTradingDays(string $startDate, int $n): ?string
    {
        if ($n === 0) return $startDate;
        if ($n > 0) {
            $rows = DB::table('market_calendar')
                ->select(['cal_date'])
                ->where('is_trading_day', 1)
                ->where('cal_date', '>', $startDate)
                ->orderBy('cal_date')
                ->limit($n)
                ->get();
            if ($rows->count() === 0) return null;
            $last = $rows->last();
            return (string)$last->cal_date;
        }

        $n = abs($n);
        $rows = DB::table('market_calendar')
            ->select(['cal_date'])
            ->where('is_trading_day', 1)
            ->where('cal_date', '<', $startDate)
            ->orderByDesc('cal_date')
            ->limit($n)
            ->get();
        if ($rows->count() === 0) return null;
        $last = $rows->last();
        return (string)$last->cal_date;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getCalendarRow(string $date): ?array
    {
        $row = DB::table('market_calendar')
            ->where('cal_date', $date)
            ->first();

        if (!$row) return null;
        return (array) $row;
    }

}
