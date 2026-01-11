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
    private WatchlistHardFilter $filter;
    private SetupClassifier $classifier;
    private ExpiryEvaluator $expiry;
    private WatchlistRanker $ranker;

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
            $expiry = $this->expiry->evaluate($c);

            $row = [
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

                'decisionCode' => $c->decisionCode,
                'decisionLabel' => $c->decisionLabel,
                'signalCode' => $c->signalCode,
                'signalLabel' => $c->signalLabel,
                'volumeLabelCode' => $c->volumeLabelCode,
                'volumeLabel' => $c->volumeLabel,

                'setupStatus' => $setupStatus,
                'reasons' => array_map(fn($r) => $r->code, $outcome->passed()),

                'plan' => $plan,
                'expiryStatus' => $expiry['expiryStatus'],
                'isExpired' => $expiry['isExpired'],
                'signalAgeDays' => $c->signalAgeDays,
                'signalFirstSeenDate' => $c->signalFirstSeenDate,
            ];

            $rank = $this->ranker->rank($row);
            $row['rankScore'] = $rank['score'];
            $row['rankReasons'] = $rank['reasons'];

            $eligible[] = $row;
        }

        usort($eligible, function ($a, $b) {
            $s = ($b['rankScore'] ?? 0) <=> ($a['rankScore'] ?? 0);
            if ($s !== 0) return $s;

            // tie-breaker: liquidity desc
            $l = ($b['valueEst'] ?? 0) <=> ($a['valueEst'] ?? 0);
            if ($l !== 0) return $l;

            // tie-breaker: rr desc
            $r = (($b['plan']['rrTp2'] ?? 0) <=> ($a['plan']['rrTp2'] ?? 0));
            return $r;
        });

        return $eligible;
    }
}
