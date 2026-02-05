<?php

namespace App\Trade\Watchlist\Algo;

/**
 * WatchlistCutoffs
 *
 * Pure functions derived from docs/watchlist/watchlist.md.
 */
final class WatchlistCutoffs
{
    /**
     * Top-pick cutoff: max(TOPPICK_MIN_SCORE, S0 - TOPPICK_SCORE_GAP)
     */
    public static function topPickCutoff(float $s0): float
    {
        return max(WatchlistAlgoConfig::TOPPICK_MIN_SCORE, $s0 - WatchlistAlgoConfig::TOPPICK_SCORE_GAP);
    }

    /**
     * Recommendations cutoff: max(MIN_RECO_SCORE, S0 - RECO_SCORE_GAP)
     */
    public static function recommendationsCutoff(float $s0): float
    {
        return max(WatchlistAlgoConfig::MIN_RECO_SCORE, $s0 - WatchlistAlgoConfig::RECO_SCORE_GAP);
    }

    private function __construct()
    {
        // static only
    }
}
