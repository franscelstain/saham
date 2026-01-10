<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistRepository;
use App\Trade\Filters\WatchlistHardFilter;
use App\Trade\Filters\TrendFilter;
use App\Trade\Filters\RsiFilter;
use App\Trade\Filters\LiquidityFilter;
use App\Trade\Signals\SetupClassifier;

class WatchlistService
{
    protected $repo;

    public function __construct(WatchlistRepository $repo)
    {
        $this->repo = $repo;
    }

    public function preopenRaw(): array
    {
        $raw = $this->repo->getEodCandidates();

        $filter = new WatchlistHardFilter(
            new TrendFilter(),
            new RsiFilter((float) config('trade.watchlist.rsi_max', 70)),
            new LiquidityFilter((float) config('trade.watchlist.min_value_est', 1000000000))
        );

        $classifier = new SetupClassifier(
            (float) config('trade.watchlist.rsi_confirm_from', 66)
        );

        $eligible = [];

        foreach ($raw as $c) {
            $outcome = $filter->evaluate($c);
            if (!$outcome->eligible) continue;

            $setupStatus = $classifier->classify($c);

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
                'decisionLabel' => $c->decisionLabel, // sementara dari signal_code (lihat poin repo+DTO)

                'setupStatus' => $setupStatus,
                'reasons' => array_map(fn($r) => $r->code, $outcome->passed()),
            ];
        }

        return $eligible;
    }
}
