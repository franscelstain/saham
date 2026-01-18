<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Trade\Support\TradeClock;
use App\Trade\Support\TradeClockConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // TradeClockConfig: wrapper config agar TradeClock tidak baca config() langsung.
        $this->app->singleton(TradeClockConfig::class, function () {
            $tz = (string) config('trade.clock.timezone', 'Asia/Jakarta');
            $h = (int) config('trade.clock.eod_cutoff.hour', 16);
            $m = (int) config('trade.clock.eod_cutoff.min', 30);
            return new TradeClockConfig($tz, $h, $m);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Pastikan TradeClock sudah ter-init saat app boot.
        try {
            TradeClock::init($this->app->make(TradeClockConfig::class));
        } catch (\Throwable $e) {
            // silent fallback: TradeClock punya fallback config() untuk early boot.
        }
    }
}
