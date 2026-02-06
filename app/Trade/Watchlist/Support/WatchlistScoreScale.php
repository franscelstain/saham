<?php

namespace App\Trade\Watchlist\Support;

/**
 * Helper to normalize score representations.
 *
 * Watchlist score_total is commonly expressed on a 0..10 scale (floats allowed).
 * Some UI/consumers display it as 0..100.
 */
class WatchlistScoreScale
{
    /**
     * Convert an input score (0..10) to a watchlist score (0..100).
     * Non-numeric inputs yield 0.0.
     *
     * @param mixed $v
     */
    public static function toWatchlistScore($v): float
    {
        if ($v === null) return 0.0;
        if (is_int($v) || is_float($v)) {
            $s = (float)$v;
        } elseif (is_string($v)) {
            $v = trim($v);
            if ($v === '' || !is_numeric($v)) return 0.0;
            $s = (float)$v;
        } else {
            return 0.0;
        }

        // Clamp to [0..10] then scale.
        if ($s < 0.0) $s = 0.0;
        if ($s > 10.0) $s = 10.0;
        return $s * 10.0;
    }
}
