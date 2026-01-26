<?php

# Tujuan: config dipakai di berbagai file bila dibutuhkan. 
#         Dan bila ada config yang sebelumnya dipakai secara private bisa diubah strukturnya supaya lebih terorganisir.
# Catatan: biar reuseable dan konsisten di seluruh aplikasi.
# path: config/trade.php

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
        // Cutoff untuk menentukan apakah EOD hari ini dianggap sudah "ready" untuk dipakai sebagai as_of_trade_date.
        // Jika kosong, engine akan fallback ke trade.clock.eod_cutoff.
        'eod_cutoff_time' => env('WATCHLIST_EOD_CUTOFF_TIME', ''),
        // Strategy policy (explicit). Default = WEEKLY_SWING.
        'policy_default' => env('WATCHLIST_POLICY_DEFAULT', 'AUTO'),
        // Supported policies (CSV), ex: "WEEKLY_SWING,DIVIDEND_SWING"
        'supported_policies' => array_values(array_filter(array_map('trim', explode(',', (string) env('WATCHLIST_SUPPORTED_POLICIES', 'WEEKLY_SWING'))))),
        // Strict contract validation (docs/watchlist/watchlist.md)
        'strict_enabled' => env('WATCHLIST_STRICT_ENABLED', true),

        // Policy docs root (optional, for environments where docs folder isn't shipped).
        // - If empty, system will auto-detect using base_path()/fallback.
        // - If strict=true and root can't be found, watchlist will fail fast.
        'policy_docs' => [
            'root' => env('WATCHLIST_POLICY_DOCS_ROOT', ''),
            'strict' => env('WATCHLIST_POLICY_DOCS_STRICT', false),
        ],
        // Default session times if market_calendar doesn't provide them
        'session_default' => [
            'open_time' => env('WATCHLIST_SESSION_OPEN', '09:00'),
            'close_time' => env('WATCHLIST_SESSION_CLOSE', '15:50'),
            'breaks' => [],
        ],
        // Policy precedence when no explicit policy is requested
        'policy_precedence' => ['DIVIDEND_SWING', 'INTRADAY_LIGHT', 'POSITION_TRADE', 'WEEKLY_SWING'],
        'bucket_top_min_score' => env('WATCHLIST_BUCKET_TOP_MIN_SCORE', 60),
        'bucket_watch_min_score' => env('WATCHLIST_BUCKET_WATCH_MIN_SCORE', 35),
        'expiry_aging_from_days' => env('WATCHLIST_EXPIRY_AGING_FROM_DAYS', 2), // label banding umur (buat UI)
        'expiry_apply_to_decisions' => [4, 5], // default: Perlu Konfirmasi (4) & Layak Beli (5)
        'expiry_enabled' => env('WATCHLIST_EXPIRY_ENABLED', true),
        'expiry_max_age_days' => env('WATCHLIST_EXPIRY_MAX_AGE_DAYS', 3), // max umur sinyal (hari). 0 = hari pertama muncul.
        'explain_verbose' => env('WATCHLIST_EXPLAIN_VERBOSE', false),
        'min_value_est' => env('WATCHLIST_MIN_VALUE_EST', 1000000000),        

        // Liquidity proxy (dv20 = SMA20 of close*volume over 20 prior trading days; exclude today)
        'liq' => [
            'dv20_a_min' => (float) env('WATCHLIST_DV20_A_MIN', 20000000000), // >= 20B
            'dv20_b_min' => (float) env('WATCHLIST_DV20_B_MIN', 5000000000),  // >= 5B
            // dv20 minimal agar bucket B dianggap cukup liquid untuk match (selain bucket C).
            'dv20_low_match_min' => (float) env('WATCHLIST_DV20_LOW_MATCH_MIN', 5000000000),
            'min_for_top_picks' => (string) env('WATCHLIST_LIQ_MIN_FOR_TOP_PICKS', 'B'), // A or B

            // Candidate gate (Hard filter): allow A/B/C by default (U/unknown is rejected).
            // Override via env as CSV, ex: "A,B".
            'allowed_candidate_buckets' => array_values(array_filter(array_map('trim', explode(',', (string) env('WATCHLIST_LIQ_ALLOWED_CANDIDATE_BUCKETS', 'A,B,C'))))),
            // Minimal dv20 to include as candidate (0 = allow all allowed buckets).
            'dv20_candidate_min' => (float) env('WATCHLIST_DV20_CANDIDATE_MIN', 0),
        ],

        // Corporate action gate (split/reverse split/unadjusted events) - heuristic
        // Tujuan: cegah indikator palsu saat seri harga belum adj.
        'corporate_action' => [
            'suspect_ratio_min' => (float) env('WATCHLIST_CA_SUSPECT_RATIO_MIN', 0.55),
            'suspect_ratio_max' => (float) env('WATCHLIST_CA_SUSPECT_RATIO_MAX', 1.80),
        ],

        // Candle structure flags (computed from EOD candle + previous EOD candle)
        'candle' => [
            'long_wick_pct' => (float) env('WATCHLIST_CANDLE_LONG_WICK_PCT', 0.55),
        ],

        // Persistence (optional)
        'persistence' => [
            'enabled' => (bool) env('WATCHLIST_PERSISTENCE_ENABLED', true),
        ],
        'preopen_cache_seconds' => env('WATCHLIST_PREOPEN_CACHE_SECONDS', 15),
        // Jika data EOD terakhir terlalu basi (diukur dalam trading days), jangan keluarkan rekomendasi BUY.
        'max_stale_trading_days' => env('WATCHLIST_MAX_STALE_TRADING_DAYS', 1),

        // Coverage gate: minimal coverage agar EOD dianggap "ready".
        // Kalau coverage di bawah threshold, sistem akan emit NO_TRADE (EOD_NOT_READY).
        'min_canonical_coverage_pct' => env('WATCHLIST_MIN_CANONICAL_COVERAGE_PCT', 85),
        'min_indicator_coverage_pct' => env('WATCHLIST_MIN_INDICATOR_COVERAGE_PCT', 85),

        // Optional: auto-open position (paper/real) dari hasil watchlist
        'auto_position_trade_enabled' => env('WATCHLIST_AUTO_POSITION_TRADE_ENABLED', false),

        // Market context / regime (risk_on / neutral / risk_off)
        // Dipakai untuk gating rekomendasi saat breadth sedang risk_off.
        'market_regime_enabled' => env('WATCHLIST_MARKET_REGIME_ENABLED', true),
        'market_regime_block_buy_on_risk_off' => env('WATCHLIST_MARKET_REGIME_BLOCK_BUY_ON_RISK_OFF', true),
        'market_regime_thresholds' => [
            // OK dari user: risk_on jika above>=55, align>=45, rsi>=50
            'risk_on' => [
                'above_ma200' => env('WATCHLIST_MR_RISK_ON_ABOVE_MA200', 55),
                'ma_alignment' => env('WATCHLIST_MR_RISK_ON_MA_ALIGN', 45),
                'avg_rsi14' => env('WATCHLIST_MR_RISK_ON_AVG_RSI', 50),
            ],
            // OK dari user: risk_off jika above<=40, align<=30, rsi<=45
            'risk_off' => [
                'above_ma200' => env('WATCHLIST_MR_RISK_OFF_ABOVE_MA200', 40),
                'ma_alignment' => env('WATCHLIST_MR_RISK_OFF_MA_ALIGN', 30),
                'avg_rsi14' => env('WATCHLIST_MR_RISK_OFF_AVG_RSI', 45),
            ],
        ],
        'ranking_enabled' => env('WATCHLIST_RANKING_ENABLED', true),
        'ranking_penalty_plan_invalid' => env('WATCHLIST_RANKING_PENALTY_PLAN_INVALID', 30),
        'ranking_penalty_rr_below_min' => env('WATCHLIST_RANKING_PENALTY_RR_BELOW_MIN', 20),
        'ranking_rr_min' => env('WATCHLIST_RANKING_RR_MIN', 1.2), // Minimal RR TP2 biar kandidat gak ngaco (soft: bukan filter, tapi penalty)
        // Weight v1 (simple)
        'ranking_signal_weights' => [
            5 => 18,
            4 => 12,
            6 => 10,
            7 => 8,
            3 => 6,
            2 => 4,
            1 => 0,
            8 => -10,
            9 => -15,
            10 => -25,
            0 => -3,
        ],
        'ranking_weights' => [
            'setup_ok' => 40,
            'setup_confirm' => 25,

            'decision_5' => 20, // Layak Beli
            'decision_4' => 10, // Perlu Konfirmasi

            'volume_strong_burst' => 15, // code 7 (Strong Burst / Breakout)
            'volume_burst' => 10,        // code 6 (Volume Burst / Accumulation)
            'volume_early' => 5,         // code 5 (Early Interest)

            'fresh_age_0' => 10,
            'fresh_age_1' => 7,
            'fresh_age_2' => 4,

            'aging' => -8,
            'expired' => -25,

            'liq_5b' => 10,
            'liq_2b' => 6,
            'liq_1b' => 3,

            'rr_ge_2' => 15,
            'rr_ge_15' => 10,
            'rr_ge_12' => 5,
            'rr_lt_min_penalty' => -15,
        ],
        'rsi_max' => env('TRADE_RSI_MAX_BUY', 70),
        'rsi_confirm_from' => env('TRADE_RSI_WARN', 66),
        'top_picks_max' => env('WATCHLIST_TOP_PICKS_MAX', 5),
        'top_picks_min_score' => env('WATCHLIST_TOP_PICKS_MIN_SCORE', 60),
        'top_picks_require_not_expired' => env('WATCHLIST_TOP_PICKS_REQUIRE_NOT_EXPIRED', true),
        'top_picks_require_setup_ok' => env('WATCHLIST_TOP_PICKS_REQUIRE_SETUP_OK', true),

        // Execution Check + Scorecard (docs/watchlist/scorecard.md)
        'scorecard' => [
            // Default guards used when candidate doesn't carry explicit guard.
            'max_chase_pct_default' => (float) env('WATCHLIST_SC_MAX_CHASE_PCT', 0.01),
            'gap_up_block_pct_default' => (float) env('WATCHLIST_SC_GAP_UP_BLOCK_PCT', 0.015),
            'spread_max_pct_default' => (float) env('WATCHLIST_SC_SPREAD_MAX_PCT', 0.004),

            // By default, scorecard evaluates only top_picks + secondary.
            'include_watch_only' => (bool) env('WATCHLIST_SC_INCLUDE_WATCH_ONLY', false),

            // Session time defaults used by scorecard window parsing.
            // Supports window tokens like "15:15-close" in docs/watchlist/scorecard.md.
            // You can override via snapshot JSON keys: session_open_time/session_close_time.
            'session_open_time_default' => env('WATCHLIST_SC_SESSION_OPEN', '09:00'),
            'session_close_time_default' => env('WATCHLIST_SC_SESSION_CLOSE', '15:50'),
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
        'max_open_positions' => env('PORTFOLIO_MAX_OPEN_POSITIONS', 5),
        'max_alloc_pct_per_position' => env('PORTFOLIO_MAX_ALLOC_PCT_PER_POSITION', 0.25),
        'max_total_alloc_pct' => env('PORTFOLIO_MAX_TOTAL_ALLOC_PCT', 1.0),

        // FIFO matching (hard)
        'matching_method' => env('PORTFOLIO_MATCHING_METHOD', 'FIFO'),
        'reject_short_sell' => env('PORTFOLIO_REJECT_SHORT_SELL', true),
    ],
];
