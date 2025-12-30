<?php

return [
    'eod_cutoff'     => env('SCREENER_EOD_CUTOFF', '16:30'),
    'session_start'  => env('SCREENER_SESSION_START', '09:00'),
    'session_end'    => env('SCREENER_SESSION_END', '15:00'),
    'sessions' => [
        // IDX regular market (umum)
        'session1' => ['start' => '09:00', 'end' => '11:30'],
        'session2' => ['start' => '13:30', 'end' => '15:50'],
    ],
    // batas entry by day (biar nggak kebawa weekend / minggu depan)
    'entry_end' => [
        'mon_wed' => '14:30',
        'thu'     => '14:00',
        'fri'     => '11:00',
    ],
    'broker' => env('SCREENER_BROKER', 'ajaib'),
    'fees' => [
        'ajaib' => [
            // tier berdasarkan nilai transaksi (notional)
            ['max' => 150_000_000,  'buy' => 0.001513, 'sell' => 0.002513],
            ['max' => 1_500_000_000,'buy' => 0.001412, 'sell' => 0.002412],
            ['max' => PHP_INT_MAX,  'buy' => 0.001311, 'sell' => 0.002311],
    ],

        // fallback "umum" (kalau mau)
        'generic' => [
            ['max' => PHP_INT_MAX, 'buy' => 0.0015, 'sell' => 0.0025],
        ],
    ],

    // Floor minimal time ratio untuk timed relvol (biar menit awal gak "meledak")
    'relvol_min_time_ratio' => (float) env('SCREENER_RELVOL_MIN_TIME_RATIO', 0.05),

    // Optional: clamp maksimal (kalau kamu mau batasi outlier)
    // 'relvol_max' => 10.0,

    // Guard: maksimal selisih menit antara waktu data (data_at) vs sekarang, agar tidak pakai data ngaret
    'intraday_max_lag_min' => (int) env('SCREENER_INTRADAY_MAX_LAG_MIN', 10),
];