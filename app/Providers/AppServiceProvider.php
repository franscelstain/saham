<?php

namespace App\Providers;

use App\Trade\Planning\PlanningPolicy;
use App\Trade\Pricing\FeeConfig;
use App\Trade\Pricing\FeeModel;
use App\Trade\Pricing\TickLadderConfig;
use App\Trade\Pricing\TickRule;
use App\Trade\Support\TradeClock;
use App\Trade\Support\TradeClockConfig;
use App\Trade\Support\TradePerf;
use App\Trade\Support\TradePerfConfig;
use App\Trade\Watchlist\Config\WatchlistPolicyConfig;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // ---- Clock ----
        $this->app->singleton(TradeClockConfig::class, function () {
            $tz = (string) config('trade.clock.timezone', 'Asia/Jakarta');
            $h = (int) config('trade.clock.eod_cutoff.hour', 16);
            $m = (int) config('trade.clock.eod_cutoff.min', 30);
            return new TradeClockConfig($tz, $h, $m);
        });

        // ---- Perf ----
        $this->app->singleton(TradePerfConfig::class, function () {
            return new TradePerfConfig(
                (int) config('trade.perf.ticker_chunk', 200),
                (int) config('trade.perf.http_pool', 15),
                (int) config('trade.perf.http_timeout', 20),
                (int) config('trade.perf.retries', 2),
                (int) config('trade.perf.retry_sleep_ms', 300)
            );
        });

        // ---- Pricing ----
        $this->app->singleton(FeeConfig::class, function () {
            return new FeeConfig(
                (float) config('trade.fees.buy_rate', 0.0015),
                (float) config('trade.fees.sell_rate', 0.0025),
                (float) config('trade.fees.extra_buy_rate', 0.0),
                (float) config('trade.fees.extra_sell_rate', 0.0),
                (float) config('trade.fees.slippage_rate', 0.0005)
            );
        });

        $this->app->singleton(TickLadderConfig::class, function () {
            $ladder = (array) config('trade.pricing.idx_ticks', []);
            return new TickLadderConfig($ladder);
        });

        // Bind concrete pricing services so constructor signature tetap bersih
        $this->app->singleton(FeeModel::class, function ($app) {
            return new FeeModel($app->make(FeeConfig::class));
        });

        $this->app->singleton(TickRule::class, function ($app) {
            return new TickRule($app->make(TickLadderConfig::class));
        });

        // ---- Planning ----
        $this->app->singleton(PlanningPolicy::class, function () {
            return new PlanningPolicy(
                (string) config('trade.planning.entry_mode', 'BREAKOUT'),
                (int) config('trade.planning.entry_buffer_ticks', 1),
                (string) config('trade.planning.sl_mode', 'ATR'),
                (float) config('trade.planning.sl_pct', 0.03),
                (float) config('trade.planning.sl_atr_mult', 1.5),
                (float) config('trade.planning.tp1_r_mult', 1.0),
                (float) config('trade.planning.tp2_r_mult', 2.0),
                (float) config('trade.planning.min_rr_tp2', 1.5),
                (float) config('trade.planning.be_at_r', 1.0)
            );
        });

        // ---- Watchlist ----
        $this->app->singleton(WatchlistPolicyConfig::class, function () {
            // CONFIRM guards source-of-truth is SCORECARD strict (docs/watchlist/scorecard.md).
            // We keep legacy shape in WatchlistPolicyConfig for internal helpers.
            $score = (array) config('trade.watchlist.scorecard', []);
            $over = isset($score['policy_overrides']) && is_array($score['policy_overrides']) ? $score['policy_overrides'] : [];
            $confirmGuards = [];
            foreach ($over as $policy => $vals) {
                if (!is_string($policy) || $policy === '' || !is_array($vals)) continue;
                $p = strtoupper($policy);
                $confirmGuards[$p] = [
                    'max_gap_up_pct' => isset($vals['gap_up_block_pct']) ? (float)$vals['gap_up_block_pct'] : (float)($score['gap_up_block_pct_default'] ?? 0.015),
                    'max_chase_from_close_pct' => isset($vals['max_chase_pct']) ? (float)$vals['max_chase_pct'] : (float)($score['max_chase_pct_default'] ?? 0.010),
                    'max_spread_pct' => isset($vals['spread_max_pct']) ? (float)$vals['spread_max_pct'] : (float)($score['spread_max_pct_default'] ?? 0.006),
                    // optional depth guards
                    'depth_top_n' => isset($vals['depth_top_n']) ? (int)$vals['depth_top_n'] : 0,
                    'min_depth_lots_bid' => isset($vals['min_depth_lots_bid']) ? (int)$vals['min_depth_lots_bid'] : 0,
                    'min_depth_lots_ask' => isset($vals['min_depth_lots_ask']) ? (int)$vals['min_depth_lots_ask'] : 0,
                ];
            }

            return new WatchlistPolicyConfig(
                (string) config('trade.watchlist.policy_default', 'AUTO'),
                (string) (config('trade.watchlist.eod_cutoff_time', '') ?: null),
                (bool) config('trade.watchlist.market_regime_enabled', true),
                (array) config('trade.watchlist.market_regime_thresholds', []),
                (int) config('trade.watchlist.max_stale_trading_days', 1),
                (float) config('trade.watchlist.min_canonical_coverage_pct', 85.0),
                (float) config('trade.watchlist.min_indicator_coverage_pct', 85.0),
                (bool) config('trade.watchlist.auto_position_trade_enabled', false),
                (float) config('trade.watchlist.liq.dv20_a_min', 20000000000),
                (float) config('trade.watchlist.liq.dv20_b_min', 5000000000),
                (float) config('trade.watchlist.corporate_action.suspect_ratio_min', 0.55),
                (float) config('trade.watchlist.corporate_action.suspect_ratio_max', 1.80),
                (float) config('trade.watchlist.candle.long_wick_pct', 0.55),
                (bool) config('trade.watchlist.confirm_enabled', true),
                $confirmGuards,
                (int) config('trade.watchlist.policies.weekly_swing.signal_recent_days', 5),
                (array) config('trade.watchlist.policies.weekly_swing.signal_codes', []),
                (array) config('trade.watchlist.policies.weekly_swing.avoid_reason_codes', []),
                (array) config('trade.watchlist.group_semantics', []),
                (array) config('trade.watchlist.universe', [])
            );
        });
    }

    public function boot()
    {
        // Initialize static helpers
        TradeClock::init($this->app->make(TradeClockConfig::class));
        TradePerf::init($this->app->make(TradePerfConfig::class));
    }
}