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
     * GET /intraday/capture?ticker=ANTM&interval=1m
     * Kalau ticker kosong -> capture semua ticker aktif.
     */
    public function capture(Request $request)
    {
        $ticker   = $request->query('ticker');   // optional
        $interval = $request->query('interval', '1m');

        $stats = $this->svc->capture($ticker, $interval);

        return response()->json($stats);
    }
}
