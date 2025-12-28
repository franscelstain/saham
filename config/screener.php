<?php

return [
    'eod_cutoff'     => env('SCREENER_EOD_CUTOFF', '16:30'),
    'session_start'  => env('SCREENER_SESSION_START', '09:00'),
    'session_end'    => env('SCREENER_SESSION_END', '15:00'),
    'broker' => env('SCREENER_BROKER', 'ajaib'),
    'fees' => [
        'ajaib' => [
        // tier berdasarkan nilai transaksi (notional)
        ['max' => 150_000_000,  'buy' => 0.001513, 'sell' => 0.002513],
        ['max' => 1_500_000_000,'buy' => 0.001412, 'sell' => 0.002412],
        ['max' => PHP_INT_MAX,  'buy' => 0.001311, 'sell' => 0.002311],

        // tambahan kondisi khusus
        'market_order_extra_broker' => 0.001,   // +0.1% (Market Order) :contentReference[oaicite:1]{index=1}
        'forced_sell_extra_broker'  => 0.0025,  // +0.25% (forced sell) :contentReference[oaicite:2]{index=2}
        ],

        // fallback "umum" (kalau mau)
        'generic' => [
        ['max' => PHP_INT_MAX, 'buy' => 0.0015, 'sell' => 0.0025],
        'market_order_extra_broker' => 0.001,
        'forced_sell_extra_broker'  => 0.0025,
        ],
    ],
    // Jam bursa WIB (default IDX reguler)
    'market_sessions' => [
        ['start' => '09:00', 'end' => '11:30'],
        ['start' => '13:00', 'end' => '15:00'],
    ],

    // Floor minimal time ratio untuk timed relvol (biar menit awal gak "meledak")
    'relvol_min_time_ratio' => (float) env('SCREENER_RELVOL_MIN_TIME_RATIO', 0.05),

    // Optional: clamp maksimal (kalau kamu mau batasi outlier)
    // 'relvol_max' => 10.0,
];