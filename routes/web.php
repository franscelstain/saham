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

// Intraday capture (kalau memang controller kamu punya endpoint ini)
// Kalau IntradayController belum punya method capture, hapus blok ini.
Route::get('/intraday/capture', [IntradayController::class, 'capture'])->name('intraday.capture');

// php artisan screener:compute-daily --date=2025-12-15