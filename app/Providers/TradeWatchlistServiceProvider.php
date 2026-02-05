<?php

namespace App\Providers;

use App\Support\Clock;
use App\Support\SystemClock;
use App\Trade\Watchlist\Contracts\PolicyDocLocator;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Services\FsPolicyDocLocator;
use App\Trade\Watchlist\WatchlistPolicyCodes;
use Illuminate\Support\ServiceProvider;

class TradeWatchlistServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PolicyDocLocator::class, function () {
            $cfg = (array) config('trade.watchlist.policy_docs', []);
            $root = isset($cfg['root']) ? (string) $cfg['root'] : '';
            $strictRaw = $cfg['strict'] ?? false;
            // env('X', false) kadang berupa string 'false' => perlu parse boolean yang benar.
            $strict = filter_var($strictRaw, FILTER_VALIDATE_BOOLEAN);

            return new FsPolicyDocLocator(
                $root,
                $strict,
                [
                    WatchlistPolicyCodes::WEEKLY_SWING   => 'weekly_swing.md',
                    WatchlistPolicyCodes::DIVIDEND_SWING => 'dividend_swing.md',
                    WatchlistPolicyCodes::INTRADAY_LIGHT => 'intraday_light.md',
                    WatchlistPolicyCodes::POSITION_TRADE => 'position_trade.md',
                    WatchlistPolicyCodes::NO_TRADE       => 'no_trade.md',
                ]
            );
        });

        // SRP_Performa.md: config() must only be read inside a Provider.
        $this->app->singleton(ScorecardConfig::class, function () {
            return ScorecardConfig::fromArray((array) config('trade.watchlist.scorecard', []));
        });

        $this->app->singleton(Clock::class, function () {
            return new SystemClock();
        });
    }
}
