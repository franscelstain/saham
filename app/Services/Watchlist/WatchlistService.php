<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistRepository;
use App\Services\Trade\TradePlanService;
use App\Trade\Filters\LiquidityFilter;
use App\Trade\Filters\RsiFilter;
use App\Trade\Filters\TrendFilter;
use App\Trade\Filters\WatchlistHardFilter;
use App\Trade\Signals\SetupClassifier;

class WatchlistService
{
    private WatchlistRepository $watchRepo;
    private TradePlanService $planService;
    private WatchlistHardFilter $filter;
    private SetupClassifier $classifier;

    public function __construct(WatchlistRepository $watchRepo, TradePlanService $planService)
    {
        $this->watchRepo = $watchRepo;
        $this->planService = $planService;

        $this->filter = new WatchlistHardFilter(
            new TrendFilter(),
            new RsiFilter((float) config('trade.watchlist.rsi_max', 70)),
            new LiquidityFilter((float) config('trade.watchlist.min_value_est', 1000000000))
        );

        $this->classifier = new SetupClassifier(
            (float) config('trade.watchlist.rsi_confirm_from', 66)
        );
    }

    public function preopenRaw(): array
    {
        $raw = $this->watchRepo->getEodCandidates();
        $eligible = [];

        foreach ($raw as $c) {
            $outcome = $this->filter->evaluate($c);
            if (!$outcome->eligible) continue;

            $setupStatus = $this->classifier->classify($c);
            $plan = $this->planService->buildFromCandidate($c);

            $eligible[] = [
                'tickerId' => $c->tickerId,
                'code' => $c->code,
                'name' => $c->name,
                'close' => $c->close,
                'ma20' => $c->ma20,
                'ma50' => $c->ma50,
                'ma200' => $c->ma200,
                'rsi' => $c->rsi,
                'volume' => $c->volume,
                'valueEst' => $c->valueEst,
                'tradeDate' => $c->tradeDate,

                'volumeLabel' => $c->volumeLabel,
                'decisionLabel' => $c->decisionLabel,

                'setupStatus' => $setupStatus,
                'reasons' => array_map(fn($r) => $r->code, $outcome->passed()),

                'plan' => $plan,
            ];
        }

        return $eligible;
    }
}
