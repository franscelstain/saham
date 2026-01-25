<?php

namespace App\Providers;

use App\Repositories\MarketCalendarRepository;
use App\Trade\Portfolio\Policies\PolicyFactory;
use Illuminate\Support\ServiceProvider;

/**
 * TradePortfolioServiceProvider
 *
 * SRP_Performa.md: config() hanya boleh dibaca di Provider.
 * Provider ini mengikat dependency Portfolio yang butuh config.
 */
class TradePortfolioServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Portfolio policy factory reads trade.portfolio config only here.
        $this->app->singleton(PolicyFactory::class, function ($app) {
            /** @var MarketCalendarRepository $calRepo */
            $calRepo = $app->make(MarketCalendarRepository::class);
            return new PolicyFactory($calRepo, (array) config('trade.portfolio', []));
        });
    }
}
