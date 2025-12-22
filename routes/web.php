<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('ohlc', 'TickerController@historicalOhlc');
Route::get('yahoo/history', 'YahooFinanceController@history');
Route::get('yahoo/names', 'YahooFinanceController@names');
Route::get('screener', 'ScreenerController@screenerPage');
Route::get('screener/candidates', 'ScreenerController@candidates');
Route::get('screener/buylist-today', 'ScreenerController@buylistToday');
Route::get('intraday/capture', 'IntradayController@capture');

// php artisan screener:compute-daily --date=2025-12-15