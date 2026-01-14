<?php
namespace App\Trade\Support;

use Carbon\Carbon;

final class TradeClock
{
    public static function tz(): string
    {
        return (string) config('trade.clock.timezone', 'Asia/Jakarta');
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::tz());
    }

    public static function isBeforeEodCutoff(?Carbon $now = null): bool
    {
        $now = $now ?: self::now();

        $hour = (int) config('trade.clock.eod_cutoff.hour', 16);
        $min  = (int) config('trade.clock.eod_cutoff.min',  30);

        return $now->hour < $hour || ($now->hour === $hour && $now->minute < $min);
    }
}
