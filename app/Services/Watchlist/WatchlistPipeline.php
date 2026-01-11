<?php

namespace App\Services\Watchlist;

use App\Trade\Expiry\ExpiryEvaluator;
use App\Trade\Ranking\WatchlistBucketer;
use App\Trade\Ranking\WatchlistRanker;

class WatchlistPipeline
{
    public WatchlistSelector $selector;
    public ExpiryEvaluator $expiry;
    public WatchlistRanker $ranker;
    public WatchlistBucketer $bucketer;
    public WatchlistPresenter $presenter;
    public WatchlistSorter $sorter;
    public WatchlistGrouper $grouper;

    public function __construct(
        WatchlistSelector $selector,
        ExpiryEvaluator $expiry,
        WatchlistRanker $ranker,
        WatchlistBucketer $bucketer,
        WatchlistPresenter $presenter,
        WatchlistSorter $sorter,
        WatchlistGrouper $grouper
    ) {
        $this->selector = $selector;
        $this->expiry = $expiry;
        $this->ranker = $ranker;
        $this->bucketer = $bucketer;
        $this->presenter = $presenter;
        $this->sorter = $sorter;
        $this->grouper = $grouper;
    }
}
