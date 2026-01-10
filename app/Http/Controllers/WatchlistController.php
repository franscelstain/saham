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
        return response()->json([
            'eod_date' => Carbon::now()->toDateString(),
            'candidates' => $this->watchlistService->preopenRaw(),
        ]);
    }
}