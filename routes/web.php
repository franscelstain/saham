<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Screener\WatchlistController;
use App\Http\Controllers\YahooFinanceController;
use App\Http\Controllers\IntradayController;

// Landing -> langsung ke screener
Route::get('/', fn () => redirect()->route('screener.index'));

// Yahoo
Route::prefix('yahoo')->group(function () {
    Route::get('/history', [YahooFinanceController::class, 'history'])->name('yahoo.history');
    Route::get('/names', [YahooFinanceController::class, 'names'])->name('yahoo.names');
});

// Screener
Route::prefix('watchlist')->group(function () {
    // halaman UI
    Route::get('/', [WatchlistController::class, 'buylistUi']);

    // data JSON (1 endpoint)
    Route::get('/data', [WatchlistController::class, 'buylistData']);
});

Route::get('/intraday/capture', [IntradayController::class, 'capture'])->name('intraday.capture');

// php artisan screener:compute-daily --date=2025-12-15