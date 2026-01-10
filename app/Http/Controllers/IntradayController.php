<?php

namespace App\Http\Controllers;

use App\Services\YahooIntradayService;
use Illuminate\Http\Request;

class IntradayController extends Controller
{
    private $svc;

    public function __construct(YahooIntradayService $svc)
    {
        $this->svc = $svc;
    }

    /**
     * GET/POST /intraday/capture?ticker=ANTM&interval=1m
     * Kalau ticker kosong -> capture semua ticker aktif.
     */
    public function capture(Request $request)
    {
        $ticker   = $request->query('ticker') ?? $request->input('ticker');
        
        // if (empty($ticker)) {
        //     return response()->json([
        //         'message' => 'Capture massal dilarang via web (rawan max_execution_time). Jalankan: php artisan intraday:capture'
        //     ], 422);
        // }
        
        $interval = $request->query('interval', $request->input('interval', '1m'));

        // whitelist interval biar gak aneh-aneh
        $allowed = ['1m','2m','5m','15m','30m','60m','90m','1h'];
        if (!in_array($interval, $allowed, true)) {
            return response()->json(['message' => 'interval tidak valid'], 422);
        }

        $stats = $this->svc->capture($ticker, $interval);

        return response()->json($stats);
    }
}
