<?php

namespace App\Providers;

use App\Support\Clock;
use App\Support\SystemClock;
use App\Trade\Watchlist\Config\ScorecardConfig;
use Illuminate\Support\ServiceProvider;

class TradeWatchlistServiceProvider extends ServiceProvider
{
    public function register()
    {
        // SRP_Performa.md: config() must only be read inside a Provider.
        $this->app->singleton(ScorecardConfig::class, function () {
            return ScorecardConfig::fromArray((array) config('trade.watchlist.scorecard', []));
        });

        $this->app->singleton(Clock::class, function () {
            return new SystemClock();
        });
    }
}
