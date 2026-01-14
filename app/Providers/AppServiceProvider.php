<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MarketData\Contracts\OhlcEodProvider;
use App\Services\MarketData\Providers\YahooOhlcEodProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(OhlcEodProvider::class, function () {
            // nanti kalau multi-provider: pilih dari config('trade.market_data.default_provider')
            return new YahooOhlcEodProvider();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
