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
        $capital = request()->query('capital_total', request()->query('capital'));
        $riskPct = request()->query('risk_per_trade_pct');

        $opts = [
            'policy' => $policy ? (string) $policy : null,
            'capital_total' => $capital !== null && $capital !== '' ? (float) preg_replace('/[^0-9.]/', '', (string) $capital) : null,
            'risk_per_trade_pct' => $riskPct !== null && $riskPct !== '' ? (float) preg_replace('/[^0-9.]/', '', (string) $riskPct) : null,
            'eod_date' => request()->query('trade_date') ? (string) request()->query('trade_date') : null,
            'now_ts' => request()->query('now_ts') ? (string) request()->query('now_ts') : null,
        ];

        return response()->json(
            $this->watchlistService->preopenContract($opts)
        );
    }
}
