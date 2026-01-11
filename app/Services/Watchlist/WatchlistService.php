<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistRepository;
use App\Services\Trade\TradePlanService;
use App\Trade\Expiry\ExpiryEvaluator;
use App\Trade\Filters\LiquidityFilter;
use App\Trade\Filters\RsiFilter;
use App\Trade\Filters\TrendFilter;
use App\Trade\Filters\WatchlistHardFilter;
use App\Trade\Ranking\WatchlistRanker;
use App\Trade\Signals\SetupClassifier;

class WatchlistService
{
    private WatchlistRepository $watchRepo;
    private TradePlanService $planService;

    private WatchlistSelector $selector;
    private ExpiryEvaluator $expiry;
    private WatchlistRanker $ranker;

    private WatchlistPresenter $presenter;
    private WatchlistSorter $sorter;

    public function __construct(WatchlistRepository $watchRepo, TradePlanService $planService)
    {
        $this->watchRepo = $watchRepo;
        $this->planService = $planService;

        $filter = new WatchlistHardFilter(
            new TrendFilter(),
            new RsiFilter((float) config('trade.watchlist.rsi_max', 70)),
            new LiquidityFilter((float) config('trade.watchlist.min_value_est', 1000000000))
        );

        $classifier = new SetupClassifier(
            (float) config('trade.watchlist.rsi_confirm_from', 66)
        );

        $this->selector = new WatchlistSelector($filter, $classifier);

        $this->expiry = new ExpiryEvaluator(
            (bool) config('trade.watchlist.expiry_enabled', true),
            (int) config('trade.watchlist.expiry_max_age_days', 3),
            (int) config('trade.watchlist.expiry_aging_from_days', 2),
            (array) config('trade.watchlist.expiry_apply_to_decisions', [4, 5])
        );

        $this->ranker = new WatchlistRanker(
            (bool) config('trade.watchlist.ranking_enabled', true),
            (float) config('trade.watchlist.ranking_rr_min', 1.2),
            (array) config('trade.watchlist.ranking_weights', [])
        );

        $this->presenter = new WatchlistPresenter();
        $this->sorter = new WatchlistSorter();
    }

    public function preopenRaw(): array
    {
        $raw = $this->watchRepo->getEodCandidates();
        $selected = $this->selector->select($raw);

        $rows = [];

        foreach ($selected as $item) {
            $c = $item['candidate'];
            $outcome = $item['outcome'];
            $setupStatus = $item['setupStatus'];

            $plan = $this->planService->buildFromCandidate($c);
            $expiry = $this->expiry->evaluate($c);

            $row = $this->presenter->baseRow($c, $outcome, $setupStatus, $plan, $expiry);

            $rank = $this->ranker->rank($row);
            $row = $this->presenter->attachRank($row, $rank);

            $rows[] = $row;
        }

        $this->sorter->sort($rows);

        return $rows;
    }
}
