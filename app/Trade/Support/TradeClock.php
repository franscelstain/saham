<?php

namespace App\Trade\Support;

use Carbon\Carbon;

final class TradeClock
{
    public static function tz(): string
    {
        return (string) config('trade.clock.timezone', 'Asia/Jakarta');
    }

    public static function now(?Carbon $now = null): Carbon
    {
        // Pastikan timezone konsisten. Kalau $now dikasih dari luar, jangan diubah.
        return $now ?: Carbon::now(self::tz());
    }

    public static function eodCutoffHour(): int
    {
        return (int) config('trade.clock.eod_cutoff.hour', 16);
    }

    public static function eodCutoffMin(): int
    {
        return (int) config('trade.clock.eod_cutoff.min', 30);
    }

    public static function isBeforeEodCutoff(?Carbon $now = null): bool
    {
        $now = self::now($now);

        $h = self::eodCutoffHour();
        $m = self::eodCutoffMin();

        return $now->hour < $h || ($now->hour === $h && $now->minute < $m);
    }

    public static function today(?Carbon $now = null): string
    {
        return self::now($now)->toDateString();
    }
}
