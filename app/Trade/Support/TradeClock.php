<?php

namespace App\Trade\Support;

use Carbon\Carbon;

final class TradeClock
{
    /** @var TradeClockConfig|null */
    private static $cfg;

    public static function init(TradeClockConfig $cfg): void
    {
        self::$cfg = $cfg;
    }

    public static function tz(): string
    {
        if (self::$cfg) return self::$cfg->timezone();

        // Fallback for safety (app may call TradeClock before providers boot)
        return (string) config('trade.clock.timezone', 'Asia/Jakarta');
    }

    public static function now(?Carbon $now = null): Carbon
    {
        // Pastikan timezone konsisten. Kalau $now dikasih dari luar, jangan diubah.
        return $now ?: Carbon::now(self::tz());
    }

    public static function eodCutoff(): string
    {
        return sprintf('%02d:%02d', self::eodCutoffHour(), self::eodCutoffMin());
    }

    public static function eodCutoffHour(): int
    {
        if (self::$cfg) return self::$cfg->eodCutoffHour();

        // Fallback for safety
        return (int) config('trade.clock.eod_cutoff.hour', 16);
    }

    public static function eodCutoffMin(): int
    {
        if (self::$cfg) return self::$cfg->eodCutoffMin();

        // Fallback for safety
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
