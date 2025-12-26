<?php

namespace App\Http\Controllers;

use App\Services\YahooOhlcImportService;
use Illuminate\Http\Request;

class YahooFinanceController extends Controller
{
    private $importer;

    public function __construct(YahooOhlcImportService $importer)
    {
        $this->importer = $importer;
    }

    /**
     * DEV ONLY: manual trigger import OHLC dari Yahoo.
     * GET /yahoo/history?ticker=BBCA&start=2024-01-01&end=2025-12-31
     */
    public function history(Request $request)
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $ticker = $request->query('ticker'); // optional
        $start  = $request->query('start');  // optional
        $end    = $request->query('end');    // optional

        $stats = $this->importer->import($ticker, $start, $end);

        return response()->json($stats);
    }
}
