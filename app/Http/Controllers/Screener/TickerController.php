<?php

namespace App\Http\Controllers\Screener;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Screener\TickerService;

class TickerController extends Controller
{
    private $tickerSrv;

    public function __construct(TickerService $tickerSrv)
    {
        $this->tickerSrv = $tickerSrv;
    }

    public function index()
    {
        return view('screener.tickers.index', [
            'title' => 'TradeAxis - Tickers',
        ]);
    }

    // server-side pagination endpoint for tabulator
    public function data(Request $request)
    {
        $page  = max(1, (int) $request->input('page', 1));
        $size  = min(200, max(10, (int) $request->input('size', 25)));

        $search = trim((string) $request->input('search', ''));
        $sort   = (string) $request->input('sort', 'ticker_code');
        $dir    = strtolower((string) $request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $result = $this->tickerSrv->paginateLatestOhlc([
            'page'   => $page,
            'size'   => $size,
            'search' => $search,
            'sort'   => $sort,
            'dir'    => $dir,
        ]);

        return response()->json($result, 200);
    }
}
