<?php

namespace App\Services\Watchlist;

use App\Trade\Expiry\ExpiryEvaluator;
use App\Trade\Filters\LiquidityFilter;
use App\Trade\Filters\RsiFilter;
use App\Trade\Filters\TrendFilter;
use App\Trade\Filters\WatchlistHardFilter;
use App\Trade\Ranking\WatchlistBucketer;
use App\Trade\Ranking\WatchlistRanker;
use App\Trade\Signals\SetupClassifier;

class WatchlistPipelineFactory
{
    public function makePreopen(): WatchlistPipeline
    {
        // Hard filter (syarat wajib)
        $liqAllowed = (array) config('trade.watchlist.liq.allowed_candidate_buckets', ['A', 'B', 'C']);
        $liqMinDv20 = (float) config('trade.watchlist.liq.dv20_candidate_min', 0);

        $filter = new WatchlistHardFilter(
            new TrendFilter(),
            new RsiFilter((float) config('trade.watchlist.rsi_max', 70)),
            new LiquidityFilter($liqMinDv20, $liqAllowed)
        );

        // Setup classifier (SETUP_OK / CONFIRM / dll)
        $classifier = new SetupClassifier(
            (float) config('trade.watchlist.rsi_confirm_from', 66)
        );

        $selector = new WatchlistSelector($filter, $classifier);

        // Expiry evaluator (soft)
        $expiry = new ExpiryEvaluator(
            (bool) config('trade.watchlist.expiry_enabled', true),
            (int) config('trade.watchlist.expiry_max_age_days', 3),
            (int) config('trade.watchlist.expiry_aging_from_days', 2),
            (array) config('trade.watchlist.expiry_apply_to_decisions', [4, 5])
        );

        // Ranker v2 (signal-aware + plan gating)
        $weights = (array) config('trade.watchlist.ranking_weights', []);

        // inject signal weights + penalties biar ranker tetap “pure” (nggak baca config di dalam)
        $weights['signal_weights'] = (array) config('trade.watchlist.ranking_signal_weights', []);
        $weights['penalty_plan_invalid'] = -abs((int) config('trade.watchlist.ranking_penalty_plan_invalid', 30));
        $weights['penalty_rr_below_min'] = -abs((int) config('trade.watchlist.ranking_penalty_rr_below_min', 20));
        $rrMin = (float) config('trade.watchlist.ranking_rr_min', 1.2);

        // Ranker v3 menerima opts (debug_reasons optional)
        $opts = $weights;
        $opts['debug_reasons'] = (bool) config('trade.watchlist.explain_verbose', false);

        $ranker = new WatchlistRanker(
            (bool) config('trade.watchlist.ranking_enabled', true),
            $rrMin,
            $opts
        );

        $bucketer = new WatchlistBucketer(
            (int) config('trade.watchlist.bucket_top_min_score', 60),
            (int) config('trade.watchlist.bucket_watch_min_score', 35),
            $rrMin
        );

        $grouper = new WatchlistGrouper(
            (int) config('trade.watchlist.top_picks_max', 5),
            (int) config('trade.watchlist.top_picks_min_score', 60),
            (bool) config('trade.watchlist.top_picks_require_setup_ok', true),
            (bool) config('trade.watchlist.top_picks_require_not_expired', true),
            $rrMin
        );

        $presenter = new WatchlistPresenter();
        $sorter = new WatchlistSorter();

        return new WatchlistPipeline(
            $selector,
            $expiry,
            $ranker,
            $bucketer,
            $presenter,
            $sorter,
            $grouper
        );
    }
}
