<?php

namespace App\Trade\Filters;

use App\DTO\Watchlist\CandidateInput;
use App\DTO\Watchlist\FilterOutcome;

class WatchlistHardFilter
{
    private TrendFilter $trend;
    private RsiFilter $rsi;
    private LiquidityFilter $liq;

    public function __construct(TrendFilter $trend, RsiFilter $rsi, LiquidityFilter $liq)
    {
        $this->trend = $trend;
        $this->rsi   = $rsi;
        $this->liq   = $liq;
    }

    public function evaluate(CandidateInput $c): FilterOutcome
    {
        $results = [
            $this->trend->check($c),
            $this->rsi->check($c),
            $this->liq->check($c),
        ];

        $eligible = true;
        foreach ($results as $r) {
            if (!$r->pass) { $eligible = false; break; }
        }

        return new FilterOutcome($eligible, $results);
    }
}
