<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ScreenerController;
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
Route::prefix('screener')->group(function () {
    Route::get('/', [ScreenerController::class, 'screenerPage'])->name('screener.index');
    Route::get('/candidates', [ScreenerController::class, 'candidates'])->name('screener.candidates');
    Route::get('/buylist-today', [ScreenerController::class, 'buylistToday'])->name('screener.buylistToday');

    // capital (POST)
    Route::post('/buylist-today/capital', [ScreenerController::class, 'setCapital'])->name('screener.setCapital');
});

Route::get('/intraday/capture', [IntradayController::class, 'capture'])->name('intraday.capture');

// php artisan screener:compute-daily --date=2025-12-15

// halaman UI
Route::get('/screener/buylist', [ScreenerController::class, 'buylistUi']);

// data JSON (1 endpoint)
Route::get('/screener/buylist/data', [ScreenerController::class, 'buylistData']);