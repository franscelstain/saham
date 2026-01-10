<?php

namespace App\Http\Controllers;

use App\Services\Watchlist\WatchlistService;
use Carbon\Carbon;

class WatchlistController extends Controller
{
    protected $watchlistService;

    public function __construct(WatchlistService $watchlistService)
    {
        $this->watchlistService = $watchlistService;
    }

    public function preopen()
    {
        $candidates = $this->watchlistService->preopenRaw();
        $eodDate = $candidates[0]['tradeDate'] ?? Carbon::now()->toDateString();
        return response()->json([
            'eod_date' => $eodDate,
            'candidates' => $candidates,
        ]);
    }
}