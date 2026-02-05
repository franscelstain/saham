<?php

namespace App\Trade\Watchlist\Algo;

/**
 * WatchlistAlgoConfig
 *
 * Dependency-free constants that are LOCKED by docs/watchlist/watchlist.md.
 * Keep this file in sync with docs; tests enforce basic invariants.
 */
final class WatchlistAlgoConfig
{
    // Grouping thresholds
    public const TOPPICK_MIN_SCORE = 0.70;
    public const TOPPICK_SCORE_GAP = 0.08;
    public const SECONDARY_MIN_SCORE = 0.55;
    public const WATCH_ONLY_MIN_SCORE = 0.35;

    // Recommendations cutoff
    public const MIN_RECO_SCORE = 0.70;
    public const RECO_SCORE_GAP = 0.05;

    private function __construct()
    {
        // static only
    }
}
