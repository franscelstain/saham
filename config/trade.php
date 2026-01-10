<?php

return [
    'compute' => [
        'lookback_days' => env('TRADE_COMPUTE_LOOKBACK_DAYS', 260),
        // decision guardrails
        'min_vol_ratio_buy' => env('TRADE_COMPUTE_MIN_VOL_RATIO_BUY', 1.5),
        'min_vol_ratio_confirm' => env('TRADE_COMPUTE_MIN_VOL_RATIO_CONFIRM', 1.0),        
        'rsi_warn' => env('TRADE_COMPUTE_RSI_WARN', 66),
        // volume label thresholds (7 batas → menghasilkan 8 level)
        'volume_ratio_thresholds' => [
            0.4, 0.7, 1.0, 1.5, 2.0, 3.0, 4.0
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
            ['lt' => 200,   'tick' => 1],
            ['lt' => 500,   'tick' => 2],
            ['lt' => 2000,  'tick' => 5],
            ['lt' => 5000,  'tick' => 10],
            ['lt' => 20000, 'tick' => 25],
            ['lt' => null,  'tick' => 50],
        ],
    ],
    'watchlist' => [
        'rsi_max' => env('WATCHLIST_RSI_MAX', 70),
        'rsi_confirm_from' => env('WATCHLIST_RSI_CONFIRM_FROM', 66),
        'min_value_est' => env('WATCHLIST_MIN_VALUE_EST', 1000000000),
    ],
];