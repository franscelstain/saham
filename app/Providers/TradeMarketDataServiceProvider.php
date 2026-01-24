<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MarketData\ImportEodService;
use App\Trade\MarketData\Config\ImportPolicy;
use App\Trade\MarketData\Config\ProviderPriority;
use App\Trade\MarketData\Config\QualityRules;
use App\Trade\MarketData\Config\ImportHoldRules;
use App\Trade\MarketData\Config\ValidatorPolicy;
use App\Trade\MarketData\Providers\Contracts\EodProvider;
use App\Trade\MarketData\Providers\Yahoo\YahooEodProvider;
use App\Trade\MarketData\Providers\EodHd\EodhdEodProvider;
use App\Services\MarketData\DisagreementMajorService;
use App\Services\MarketData\MissingTradingDayService;
use App\Services\MarketData\SoftQualityRulesService;

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


        $this->app->singleton(ImportHoldRules::class, function () {
            $holdDisagreeRatio = (float) config('trade.market_data.quality.hold_disagree_ratio_min', 0.01);
            $holdDisagreeCount = (int) config('trade.market_data.quality.hold_disagree_count_min', 20);
            $minDayCoverageRatio = (float) config('trade.market_data.quality.min_day_coverage_ratio', 0.60);
            $minPointsPerDay = (int) config('trade.market_data.quality.min_points_per_day', 5);
            $holdLowCoverageDaysMin = (int) config('trade.market_data.quality.hold_low_coverage_days_min', 2);

            return new ImportHoldRules(
                $holdDisagreeRatio,
                $holdDisagreeCount,
                $minDayCoverageRatio,
                $minPointsPerDay,
                $holdLowCoverageDaysMin
            );
        });

        // Validator policy (Phase 7) to avoid config() reads inside service.
        $this->app->singleton(ValidatorPolicy::class, function () {
            $max = (int) config('trade.market_data.validator.max_tickers', 20);
            $callLimit = (int) config('trade.market_data.providers.eodhd.daily_call_limit', 20);
            $disagreeMajorPct = (float) config('trade.market_data.validator.disagree_major_pct', 1.5);
            return new ValidatorPolicy($max, $callLimit, $disagreeMajorPct);
        });

        // Provider bindings (multi-source ready)
        $this->app->bind(YahooEodProvider::class, function () {
            $cfg = (array) config('trade.market_data.providers.yahoo', []);
            return new YahooEodProvider($cfg);
        });

        // Validator provider (NOT tagged into import pipeline to avoid daily API limits)
        $this->app->bind(EodhdEodProvider::class, function () {
            $cfg = (array) config('trade.market_data.providers.eodhd', []);
            return new EodhdEodProvider($cfg);
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
                $app->make(DisagreementMajorService::class),
                $app->make(MissingTradingDayService::class),
                $app->make(SoftQualityRulesService::class),
                $app->make(ImportHoldRules::class),
                (int) config('trade.perf.http_pool', 15),
                $app->make('md.eod.providers_map')
            );
        });
    }
}
