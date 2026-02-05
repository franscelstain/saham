<?php

namespace App\Http\Controllers;

use App\Services\Watchlist\WatchlistService;

class WatchlistController extends Controller
{
    protected WatchlistService $watchlistService;

    public function __construct(WatchlistService $watchlistService)
    {
        $this->watchlistService = $watchlistService;
    }

    public function preopen()
    {
	    $policy = request()->query('policy');
	    $capital = request()->query('capital_idr');
        $riskPct = request()->query('risk_per_trade_pct');
        $asofEod = request()->query('asof_eod_date');

	    $opts = [
            'policy' => $policy ? (string) $policy : null,
            'capital_idr' => $capital !== null && $capital !== '' ? (float) preg_replace('/[^0-9.]/', '', (string) $capital) : null,
            'risk_per_trade_pct' => $riskPct !== null && $riskPct !== '' ? (float) preg_replace('/[^0-9.]/', '', (string) $riskPct) : null,
	        'eod_date' => $asofEod ? (string) $asofEod : null,
            'now_ts' => request()->query('now_ts') ? (string) request()->query('now_ts') : null,
        ];

        return response()->json(
            $this->watchlistService->preopenContract($opts)
        );
    }
}
