<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MarketData\ImportEodService;
use App\Trade\MarketData\Config\ImportPolicy;
use App\Trade\MarketData\Config\ProviderPriority;
use App\Trade\MarketData\Config\QualityRules;
use App\Trade\MarketData\Providers\Contracts\EodProvider;
use App\Trade\MarketData\Providers\Yahoo\YahooEodProvider;

class TradeMarketDataServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ImportPolicy::class, function () {
            $lookback = (int) config('trade.market_data.eod.lookback_trading_days', 7);
            $coverageMin = (float) config('trade.market_data.gating.coverage_min_pct', 95);

            return new ImportPolicy($lookback, $coverageMin);
        });

        $this->app->singleton(ProviderPriority::class, function () {
            $list = (array) config('trade.market_data.providers_priority', ['yahoo']);
            return new ProviderPriority($list);
        });

        $this->app->singleton(QualityRules::class, function () {
            // toleransi, disagree threshold, outlier threshold, dll
            $tol = (float) config('trade.market_data.quality.price_in_range_tolerance', 0.0001);
            $disagreePct = (float) config('trade.market_data.quality.disagree_major_pct', 2.0);
            $gapPct = (float) config('trade.market_data.quality.gap_extreme_pct', 20.0);

            return new QualityRules($tol, $disagreePct, $gapPct);
        });

        // Provider bindings (multi-source ready)
        $this->app->bind(YahooEodProvider::class, function () {
            $cfg = (array) config('trade.market_data.providers.yahoo', []);
            return new YahooEodProvider($cfg);
        });

        // registry sederhana: nanti bisa jadi map providerName => instance
        $this->app->tag([YahooEodProvider::class], 'md.eod.providers');

        $this->app->singleton('md.eod.providers_map', function ($app) {
            $map = [];
            foreach ($app->tagged('md.eod.providers') as $p) {
                if ($p instanceof EodProvider) {
                    $map[strtolower($p->name())] = $p;
                }
            }
            return $map;
        });

        $this->app->bind(ImportEodService::class, function ($app) {
            return new ImportEodService(
                $app->make(ImportPolicy::class),
                $app->make(QualityRules::class),
                $app->make(ProviderPriority::class),
                $app->make(\App\Repositories\TickerRepository::class),
                $app->make(\App\Repositories\MarketCalendarRepository::class),
                $app->make(\App\Repositories\MarketData\RunRepository::class),
                $app->make(\App\Repositories\MarketData\RawEodRepository::class),
                $app->make(\App\Repositories\MarketData\CanonicalEodRepository::class),
                $app->make('md.eod.providers_map')
            );
        });
    }
}
