<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\PortfolioController;

Route::get('/watchlist/preopen', [WatchlistController::class, 'preopen'])->name('watchlist.preopen');

// Portfolio API (minimal) - sesuai docs/PORTFOLIO.md
Route::get('/portfolio/positions', [PortfolioController::class, 'positions'])->name('portfolio.positions');
Route::post('/portfolio/trades/ingest', [PortfolioController::class, 'ingestTrade'])->name('portfolio.trades.ingest');
Route::post('/portfolio/plans/upsert', [PortfolioController::class, 'upsertPlan'])->name('portfolio.plans.upsert');
Route::get('/portfolio/value-eod', [PortfolioController::class, 'valueEod'])->name('portfolio.value_eod');