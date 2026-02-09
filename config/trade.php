<?php

# Tujuan: config dipakai di berbagai file bila dibutuhkan. 
#         Dan bila ada config yang sebelumnya dipakai secara private bisa diubah strukturnya supaya lebih terorganisir.
# Catatan: biar reuseable dan konsisten di seluruh aplikasi.
# path: config/trade.php

// -----------------------------------------------------------------------------
// Watchlist Group Semantics (Option A) - runtime guardrails
// Tujuan: kalau env salah (misalnya toppick < secondary), sistem auto-normalize
// supaya ordering tetap monotonic: toppick >= secondary >= watch_only.
// Ini mencegah output yang bikin bingung (contoh: WatchOnly score tampak "jatuh"
// padahal top_cut bisa puluhan/persen), dan mencegah bug pengkondisian.
// -----------------------------------------------------------------------------

$__gsOption = (string) env('WATCHLIST_GROUP_SEMANTICS_OPTION', 'A');
$__gsToppickMin = (float) env('WATCHLIST_TOPPICK_MIN_SCORE', 0.70);
$__gsSecondaryMin = (float) env('WATCHLIST_SECONDARY_MIN_SCORE', 0.55);
$__gsWatchOnlyMin = (float) env('WATCHLIST_WATCH_ONLY_MIN_SCORE', 0.35);

$__gsClamp01 = static function (float $v): float {
    if ($v < 0.0) { return 0.0; }
    if ($v > 1.0) { return 1.0; }
    return $v;
};

$__gsToppickMin = $__gsClamp01($__gsToppickMin);
$__gsSecondaryMin = $__gsClamp01($__gsSecondaryMin);
$__gsWatchOnlyMin = $__gsClamp01($__gsWatchOnlyMin);

// enforce monotonic ordering
$__gsToppickMin = max($__gsToppickMin, $__gsSecondaryMin);
$__gsSecondaryMin = min($__gsSecondaryMin, $__gsToppickMin);
$__gsSecondaryMin = max($__gsSecondaryMin, $__gsWatchOnlyMin);
$__gsWatchOnlyMin = min($__gsWatchOnlyMin, $__gsSecondaryMin);

return [
    'compute_eod' => [
        'upsert_batch_size' => env('TRADE_EOD_UPSERT_BATCH_SIZE', 500),
        // extra warmup trading days untuk memastikan indikator (RSI/ATR/MA) stabil saat compute di trade_date.
        // default 60 trading days.
        'warmup_extra_trading_days' => env('TRADE_EOD_WARMUP_EXTRA_TRADING_DAYS', 60),
    ],
    'clock' => [
        'timezone'    => env('TRADE_EOD_TZ', 'Asia/Jakarta'),
        'eod_cutoff'  => [
            'hour' => env('TRADE_EOD_CUTOFF_HOUR', 16),
            'min'  => env('TRADE_EOD_CUTOFF_MIN', 30),
        ],
    ],
    'fees' => [
        // gunakan estimasi konservatif & mudah diubah
        // contoh umum retail: buy fee 0.15% , sell fee 0.25%
        'buy_rate'  => env('TRADE_FEE_BUY_RATE', 0.0015),
        'sell_rate' => env('TRADE_FEE_SELL_RATE', 0.0025),

        // optional: levy/ppn/pajak tambahan kalau kamu mau masukin belakangan
        'extra_buy_rate'  => env('TRADE_FEE_EXTRA_BUY_RATE', 0.0),
        'extra_sell_rate' => env('TRADE_FEE_EXTRA_SELL_RATE', 0.0),

        // slippage asumsi (konservatif) -> dipakai di entry & exit
        // misal 0.05% per sisi
        'slippage_rate' => env('TRADE_SLIPPAGE_RATE', 0.0005),
    ],
    'indicators' => [
        'lookback_days' => env('TRADE_LOOKBACK_DAYS', 260),
        'volume_ratio_thresholds' => [0.4, 0.7, 1.0, 1.5, 2.0, 3.0, 4.0],
        'decision_guardrails' => [
            'rsi_max_buy'          => env('TRADE_RSI_MAX_BUY', 70),
            'rsi_warn'             => env('TRADE_RSI_WARN', 66),
            'min_vol_ratio_buy'     => env('TRADE_MIN_VOL_RATIO_BUY', 1.5),
            'min_vol_ratio_confirm' => env('TRADE_MIN_VOL_RATIO_CONFIRM', 1.0),
        ],
        'pattern_thresholds' => [
            'vol_strong' => env('TRADE_PATTERN_VOL_STRONG', 2.0),
            'vol_burst'  => env('TRADE_PATTERN_VOL_BURST', 1.5),
        ],
    ],
    'market_data' => [
        'eod' => [
            'lookback_trading_days' => env('TRADE_MD_LOOKBACK_TRADING_DAYS', 7),
        ],
        'gating' => [
            'coverage_min_pct' => env('TRADE_MD_COVERAGE_MIN', 95),
        ],
        'providers' => [
            'yahoo' => [
                'base_url' => env('TRADE_YAHOO_BASE_URL', 'https://query1.finance.yahoo.com'),
                'suffix' => env('TRADE_YAHOO_SUFFIX', '.JK'), // BEI
                'timeout' => env('TRADE_YAHOO_TIMEOUT', 20),
                'retry' => env('TRADE_YAHOO_RETRY', 2),
                'retry_sleep_ms' => env('TRADE_YAHOO_RETRY_SLEEP_MS', 250),
                'user_agent' => env('TRADE_YAHOO_UA', 'Mozilla/5.0'),
            ],

            // Provider 2 (validator) - EODHD
            // Dipakai untuk validasi subset ticker (recommended/candidates) karena free plan ada limit calls/hari.
            'eodhd' => [
                'base_url' => env('TRADE_EODHD_BASE_URL', 'https://eodhd.com/api'),
                'suffix' => env('TRADE_EODHD_SUFFIX', '.JK'),
                'api_token' => env('TRADE_EODHD_API_TOKEN', ''),
                'timeout' => env('TRADE_EODHD_TIMEOUT', 20),
                'retry' => env('TRADE_EODHD_RETRY', 1),
                'retry_sleep_ms' => env('TRADE_EODHD_RETRY_SLEEP_MS', 250),
                // Hard cap untuk penggunaan harian (opsional, enforcement di layer command/service)
                'daily_call_limit' => env('TRADE_EODHD_DAILY_CALL_LIMIT', 20),
            ],
        ],
        'providers_priority' => ['yahoo'],
        'quality' => [
            'price_in_range_tolerance' => env('TRADE_MD_TOL', 0.0001),
            'disagree_major_pct' => env('TRADE_MD_DISAGREE_PCT', 2.0),
            'gap_extreme_pct' => env('TRADE_MD_GAP_EXTREME_PCT', 20.0),
            // Additional HOLD gates (beyond simple coverage_pct) — see docs/MARKET_DATA.md
            'hold_disagree_ratio_min' => env('TRADE_MD_HOLD_DISAGREE_RATIO_MIN', 0.01), // 0.01 = 1%
            'hold_disagree_count_min' => env('TRADE_MD_HOLD_DISAGREE_COUNT_MIN', 20),
            'min_day_coverage_ratio' => env('TRADE_MD_MIN_DAY_COVERAGE_RATIO', 0.60),
            'min_points_per_day' => env('TRADE_MD_MIN_POINTS_PER_DAY', 5),
            'hold_low_coverage_days_min' => env('TRADE_MD_HOLD_LOW_COVERAGE_DAYS_MIN', 2),
        ],

        // Validator settings (post-screener / post-candidate) - does NOT affect import coverage.
        'validator' => [
            'max_tickers' => env('TRADE_MD_VALIDATOR_MAX_TICKERS', 20),
            // Disagree threshold yang dipakai untuk badge/peringatan di UI.
            'disagree_major_pct' => env('TRADE_MD_VALIDATOR_DISAGREE_PCT', 1.5),
        ],
    ],
    'perf' => [
        'ticker_chunk' => env('TRADE_TICKER_CHUNK', 200),
        'http_pool' => env('TRADE_HTTP_POOL', 15),
        'http_timeout' => env('TRADE_HTTP_TIMEOUT', 20),
        'retries' => env('TRADE_HTTP_RETRIES', 2),
        'retry_sleep_ms' => env('TRADE_HTTP_RETRY_SLEEP_MS', 300),
    ],
    'planning' => [
        // risk sizing basis (tanpa intraday)
        // SL distance default (ATR multiple atau %)
        'sl_mode' => env('TRADE_SL_MODE', 'ATR'), // ATR | PCT | SUPPORT
        'sl_atr_mult' => env('TRADE_SL_ATR_MULT', 1.5),
        'sl_pct' => env('TRADE_SL_PCT', 0.03),

        // TP multiple terhadap risk (R)
        'tp1_r_mult' => env('TRADE_TP1_R', 1.0),
        'tp2_r_mult' => env('TRADE_TP2_R', 2.0),

        // minimal RR untuk dianggap “masuk akal”
        'min_rr_tp2' => env('TRADE_MIN_RR_TP2', 1.5),

        // entry model
        'entry_mode' => env('TRADE_ENTRY_MODE', 'BREAKOUT'), // BREAKOUT | CLOSE
        'entry_buffer_ticks' => env('TRADE_ENTRY_BUFFER_TICKS', 1), // breakout: tambah 1 tick

        // BE (break-even) trigger target: kapan minimal aman pindah SL ke BE
        'be_at_r' => env('TRADE_BE_AT_R', 1.0),
    ],
    'pricing' => [
        // Tick rules IDX (simplified standard ladder)
        // If price < 200 => tick 1
        // 200-<500 => 2
        // 500-<2000 => 5
        // 2000-<5000 => 10
        // 5000-<20000 => 25
        // >=20000 => 50
        'idx_ticks' => [
            ['lt' => 200,  'tick' => 1],
            ['lt' => 500,  'tick' => 2],
            ['lt' => 2000, 'tick' => 5],
            ['lt' => 5000, 'tick' => 10],
            ['lt' => null, 'tick' => 25],
        ],
    ],
    'watchlist' => [
        // Default policy if query param missing
        'policy_default' => env('WATCHLIST_POLICY_DEFAULT', 'WEEKLY_SWING'),

        // Supported policies (for UI, docs, and validation)
        'supported_policies' => [
            'WEEKLY_SWING',
            'DIVIDEND_SWING',
            'POSITION_TRADE',
            'INTRADAY_LIGHT',
            'NO_TRADE',
        ],

        // Default session if calendar overrides missing (HH:MM:SS)
        'session_default' => [
            'open_time' => '09:00:00',
            'close_time' => '16:00:00',
            'breaks' => [],
        ],

        // EOD cutoff time (HH:MM) to decide whether to use today's canonical (if available)
        'eod_cutoff_time' => env('WATCHLIST_EOD_CUTOFF_TIME', ''),

        // Market regime (global locks)
        'market_regime_enabled' => env('WATCHLIST_MARKET_REGIME_ENABLED', true),
        'market_regime_thresholds' => [
            'risk_off_max_breadth_pct' => (float) env('WATCHLIST_RISK_OFF_MAX_BREADTH_PCT', 0.35),
            'risk_on_min_breadth_pct' => (float) env('WATCHLIST_RISK_ON_MIN_BREADTH_PCT', 0.55),
        ],

        // Freshness and readiness gates
        'max_stale_trading_days' => (int) env('WATCHLIST_MAX_STALE_TRADING_DAYS', 1),
        'min_canonical_coverage_pct' => (float) env('WATCHLIST_MIN_CANONICAL_COVERAGE_PCT', 85.0),
        'min_indicator_coverage_pct' => (float) env('WATCHLIST_MIN_INDICATOR_COVERAGE_PCT', 85.0),

        // Auto-position-trade fallback (optional)
        'auto_position_trade_enabled' => env('WATCHLIST_AUTO_POSITION_TRADE_ENABLED', false),

        // Group semantics cutoffs (docs/watchlist/watchlist.md)
        'group_semantics' => [
            // Doc-anchored option selector for group semantics thresholding.
            // Keep as explicit string to avoid "Option A vs B" ambiguity.
            'option' => $__gsOption,

            // Max items per group in the PREOPEN output (display / UX guardrail).
            // NOTE: recommendations allocator is NOT capped by these maxima.
            'toppick_max' => (int) env('WATCHLIST_TOPPICK_MAX', 10),
            'secondary_max' => (int) env('WATCHLIST_SECONDARY_MAX', 10),
            'watch_only_max' => (int) env('WATCHLIST_WATCH_ONLY_MAX', 50),

            // Backward-compat aliases (older code/tests may still reference these).
            'top_pick_max' => (int) env('WATCHLIST_TOPPICK_MAX', 10),

            // Thresholds are FRACTION units (0..1), NOT watchlist_score (0..100).
            'toppick_min_score' => $__gsToppickMin,
            'toppick_score_gap' => (float) env('WATCHLIST_TOPPICK_SCORE_GAP', 0.08),
            'secondary_min_score' => $__gsSecondaryMin,
            'watch_only_min_score' => $__gsWatchOnlyMin,
        ],
        // IMPORTANT: These are score_total fractions (0..1), not percent.
        // Liquidity proxy (dv20 = SMA20 of close*volume over 20 prior trading days; exclude today)
        'liq' => [
            'dv20_a_min' => (float) env('WATCHLIST_DV20_A_MIN', 20000000000), // >= 20B
            'dv20_b_min' => (float) env('WATCHLIST_DV20_B_MIN', 5000000000),  // >= 5B
        ],

        // Universe hard gates (docs/watchlist/watchlist.md)
        'universe' => [
            'min_price' => (float) env('WATCHLIST_UNIVERSE_MIN_PRICE', 50),
            'min_dv20_idr' => (float) env('WATCHLIST_UNIVERSE_MIN_DV20_IDR', 2000000000),
            'min_turnover20_idr' => (float) env('WATCHLIST_UNIVERSE_MIN_TURNOVER20_IDR', 2000000000),
            'max_atr_pct_universe' => (float) env('WATCHLIST_UNIVERSE_MAX_ATR_PCT', 0.20),
        ],

        // Corporate action gate (heuristic)
        'corporate_action' => [
            'suspect_ratio_min' => (float) env('WATCHLIST_CA_SUSPECT_RATIO_MIN', 0.55),
            'suspect_ratio_max' => (float) env('WATCHLIST_CA_SUSPECT_RATIO_MAX', 1.80),
        ],

        // Candle heuristics
        'candle' => [
            'long_wick_pct' => (float) env('WATCHLIST_LONG_WICK_PCT', 0.55),
        ],

        // CONFIRM enabled flag. Thresholds are sourced from SCORECARD strict config below.
        'confirm_enabled' => env('WATCHLIST_CONFIRM_ENABLED', true),

        // SCORECARD / CONFIRM STRICT (docs/watchlist/scorecard.md)
        // NOTE: This is the preferred config source for CONFIRM.
        'scorecard' => [
            'include_watch_only' => (bool) env('WATCHLIST_SCORECARD_INCLUDE_WATCH_ONLY', false),
            // Defaults (LOCKED by docs/watchlist/scorecard.md)
            // NOTE: Per-policy overrides below are the real source; these defaults are only fallbacks.
            'max_chase_pct_default' => 0.010,
            'gap_up_block_pct_default' => 0.015,
            'spread_max_pct_default' => 0.006,
            'breakout_band_pct_default' => 0.004,
            'max_retry_windows_default' => 2,

            'stale_tol_pct' => 0.003,
            'max_snapshot_age_sec' => 30,
            'retry_cooldown_sec' => 30,
            'session_open_time_default' => '09:00',
            'session_close_time_default' => '16:00',

            // Per-policy overrides + windows.
            // Window syntax: "HH:MM-HH:MM", token "open"/"close" allowed.
            'policy_overrides' => [
                'WEEKLY_SWING' => [
                    'max_chase_pct' => 0.010,
                    'gap_up_block_pct' => 0.015,
                    'spread_max_pct' => 0.006,
                    'breakout_band_pct' => 0.004,
                    'max_retry_windows' => 2,
                    'entry_windows' => ['09:20-10:15','13:35-14:15'],
                    'avoid_windows' => ['09:00-09:20','11:30-13:30','15:15-16:00'],
                ],
                'DIVIDEND_SWING' => [
                    'max_chase_pct' => 0.008,
                    'gap_up_block_pct' => 0.012,
                    'spread_max_pct' => 0.006,
                    'breakout_band_pct' => 0.004,
                    'max_retry_windows' => 2,
                    'entry_windows' => ['09:20-10:15','13:35-14:15'],
                    'avoid_windows' => ['09:00-09:20','11:30-13:30','15:15-16:00'],
                ],
                'POSITION_TRADE' => [
                    'max_chase_pct' => 0.012,
                    'gap_up_block_pct' => 0.018,
                    'spread_max_pct' => 0.008,
                    'breakout_band_pct' => 0.004,
                    'max_retry_windows' => 2,
                    'entry_windows' => ['09:20-10:15','13:35-14:15'],
                    'avoid_windows' => ['09:00-09:20','11:30-13:30','15:15-16:00'],
                ],
                'INTRADAY_LIGHT' => [
                    'max_chase_pct' => 0.006,
                    'gap_up_block_pct' => 0.010,
                    'spread_max_pct' => 0.010,
                    'breakout_band_pct' => 0.004,
                    'max_retry_windows' => 1,
                    'entry_windows' => ['09:05-09:45','13:35-14:10'],
                    'avoid_windows' => ['09:00-09:05','11:30-13:30','15:00-16:00'],
                ],
            ],
        ],
    ],

    // Portfolio: lifecycle + lots + realized/unrealized PnL (docs/PORTFOLIO.md)
    'portfolio' => [
        'default_account_id' => env('PORTFOLIO_DEFAULT_ACCOUNT_ID', 1),
        'default_strategy_code' => env('PORTFOLIO_DEFAULT_STRATEGY_CODE', 'WEEKLY_SWING'),

        'weekly_swing' => [
            'cooldown_days_after_sl' => (int) env('PORTFOLIO_WEEKLY_COOLDOWN_AFTER_SL', 2),
            'no_averaging_down' => (bool) env('PORTFOLIO_WEEKLY_NO_AVERAGING_DOWN', true),
        ],
        'dividend_swing' => [
            'cooldown_days_after_sl' => (int) env('PORTFOLIO_DIVIDEND_COOLDOWN_AFTER_SL', 3),
            'no_averaging_down' => (bool) env('PORTFOLIO_DIVIDEND_NO_AVERAGING_DOWN', true),
        ],

        // Minimal policies to satisfy docs/PORTFOLIO.md strategy coverage.
        // Enforcement is intentionally soft and can be disabled.
        'intraday_light' => [
            'enabled' => (bool) env('PORTFOLIO_INTRADAY_ENABLED', true),
            'cooldown_days_after_sl' => (int) env('PORTFOLIO_INTRADAY_COOLDOWN_AFTER_SL', 0),
            'no_averaging_down' => (bool) env('PORTFOLIO_INTRADAY_NO_AVERAGING_DOWN', false),
            'enable_risk_events' => (bool) env('PORTFOLIO_INTRADAY_ENABLE_RISK_EVENTS', false),
        ],
        'position_trade' => [
            'enabled' => (bool) env('PORTFOLIO_POSITION_TRADE_ENABLED', true),
            'allow_new_entries' => (bool) env('PORTFOLIO_POSITION_TRADE_ALLOW_NEW_ENTRIES', false),
            'cooldown_days_after_sl' => (int) env('PORTFOLIO_POSITION_TRADE_COOLDOWN_AFTER_SL', 0),
            'no_averaging_down' => (bool) env('PORTFOLIO_POSITION_TRADE_NO_AVERAGING_DOWN', false),
            'enable_risk_events' => (bool) env('PORTFOLIO_POSITION_TRADE_ENABLE_RISK_EVENTS', false),
        ],

        // Risk caps (soft): enforce via strategy rules / UI layer
        'max_open_positions' => (int) env('PORTFOLIO_MAX_OPEN_POSITIONS', 5),
        'max_alloc_pct_per_position' => env('PORTFOLIO_MAX_ALLOC_PCT_PER_POSITION', 0.25),
        'max_total_alloc_pct' => env('PORTFOLIO_MAX_TOTAL_ALLOC_PCT', 1.0),

        // FIFO matching (hard)
        'matching_method' => env('PORTFOLIO_MATCHING_METHOD', 'FIFO'),
        'reject_short_sell' => env('PORTFOLIO_REJECT_SHORT_SELL', true),
    ],
];
