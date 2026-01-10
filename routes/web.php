<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WatchlistController;

Route::get('/watchlist/preopen', [WatchlistController::class, 'preopen'])->name('watchlist.preopen');