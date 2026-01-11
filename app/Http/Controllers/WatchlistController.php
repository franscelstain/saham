<?php

namespace App\Http\Controllers;

use App\Services\Watchlist\WatchlistService;

class WatchlistController extends Controller
{
    protected $watchlistService;

    public function __construct(WatchlistService $watchlistService)
    {
        $this->watchlistService = $watchlistService;
    }
    
    public function preopen()
    {
        return response()->json(
            $this->watchlistService->preopenRaw()
        );
    }
}