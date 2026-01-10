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
    'watchlist' => [
        'rsi_max' => env('WATCHLIST_RSI_MAX', 70),
        'rsi_confirm_from' => env('WATCHLIST_RSI_CONFIRM_FROM', 66),
        'min_value_est' => env('WATCHLIST_MIN_VALUE_EST', 1000000000),
    ],
];