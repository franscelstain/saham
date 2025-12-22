<?php

namespace App\Http\Controllers;

use App\Services\ScreenerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScreenerController extends Controller
{
    private $svc;

    public function __construct(ScreenerService $svc)
    {
        $this->svc = $svc;
    }

    /**
     * /screener/candidates?date=YYYY-MM-DD (optional)
     */
    public function candidates(Request $request)
    {
        $date = $request->query('date'); // optional: force tanggal EOD tertentu

        $data = $this->svc->getCandidatesPageData($date);

        return view('screener.candidates', $data);
    }

    public function buylistToday(Request $r)
    {
        $today = $r->get('today');           // optional
        $capital = $r->get('capital');       // contoh: 5000000

        $data = $this->svc->getTodayBuylistData(
            $today,
            $capital !== null ? (float) $capital : null
        );

        return view('screener.buylist_today', $data);
    }


    public function screenerPage()
    {
        $latestDate = DB::table('ticker_indicators_daily')->max('trade_date');

        $rows = collect();
        if ($latestDate) {
            $rows = DB::table('ticker_indicators_daily as d')
                ->join('tickers as t', 't.ticker_id', '=', 'd.ticker_id')
                ->where('d.trade_date', $latestDate)
                ->where('d.is_deleted', 0)
                ->where('t.is_deleted', 0)
                ->select([
                    't.ticker_code',
                    't.company_name',
                    'd.trade_date',
                    'd.ma20',
                    'd.ma50',
                    'd.ma200',
                    'd.close',
                    'd.rsi14',
                    'd.vol_ratio',
                    'd.score_total',
                    DB::raw("CASE d.signal_code
                        WHEN 1 THEN 'False Breakout / Batal'
                        WHEN 2 THEN 'Hati - Hati'
                        WHEN 3 THEN 'Hindari'
                        WHEN 4 THEN 'Perlu Konfirmasi'
                        WHEN 5 THEN 'Layak Beli'
                        ELSE 'Unknown' END AS signal_name"),
                    DB::raw("CASE d.volume_label_code
                        WHEN 1 THEN 'Climax / Euphoria – hati-hati'
                        WHEN 2 THEN 'Quiet/Normal – Volume lemah'
                        WHEN 3 THEN 'Ultra Dry'
                        WHEN 4 THEN 'Dormant'
                        WHEN 5 THEN 'Quiet'
                        WHEN 6 THEN 'Normal'
                        WHEN 7 THEN 'Early Interest'
                        WHEN 8 THEN 'Volume Burst / Accumulation'
                        WHEN 9 THEN 'Strong Burst / Breakout'
                        WHEN 10 THEN 'Climax / Euphoria'
                        ELSE '-' END AS volume_label_name"),
                ])
                ->orderByDesc('d.signal_code')
                ->orderByDesc('d.volume_label_code')
                ->orderByDesc('d.score_total')
                ->get();
        }

        return view('screener.index', [
            'trade_date' => $latestDate,
            'rows' => $rows,
        ]);
    }
}