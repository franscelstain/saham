<?php

namespace App\Trade\Watchlist\Support;

/**
 * Helper to normalize score representations.
 *
 * Contract (docs/watchlist):
 * - `score_total` in output MUST be float range [0..1].
 * - UI may display as percent: `score_total * 100`.
 *
 * Legacy inputs may appear as:
 * - 0..1   (already normalized)
 * - 0..10  (common internal scale)
 * - 0..100 (display scale)
 */
class WatchlistScoreScale
{
    /**
     * Normalize any supported input into score_total float [0..1].
     * Non-numeric inputs yield 0.0.
     *
     * Heuristics:
     * - <= 1.0  => assume already normalized
     * - <= 10.0 => assume 0..10
     * - > 10.0  => assume 0..100
     *
     * @param mixed $v
     */
    public static function toScore01($v): float
    {
        if ($v === null) return 0.0;

        if (is_int($v) || is_float($v)) {
            $s = (float) $v;
        } elseif (is_string($v)) {
            $v = trim($v);
            if ($v === '' || !is_numeric($v)) return 0.0;
            $s = (float) $v;
        } else {
            return 0.0;
        }

        if ($s <= 0.0) return 0.0;

        if ($s <= 1.0) {
            $out = $s;
        } elseif ($s <= 10.0) {
            $out = $s / 10.0;
        } else {
            $out = $s / 100.0;
        }

        if ($out < 0.0) $out = 0.0;
        if ($out > 1.0) $out = 1.0;
        return $out;
    }

    /**
     * Convert any supported input into a display percentage (0..100).
     *
     * @param mixed $v
     */
    public static function toScorePct($v): float
    {
        return self::toScore01($v) * 100.0;
    }

    /**
     * Backward compatible alias (historically used by tests/consumers).
     *
     * @param mixed $v
     */
    public static function toWatchlistScore($v): float
    {
        return self::toScorePct($v);
    }
}
