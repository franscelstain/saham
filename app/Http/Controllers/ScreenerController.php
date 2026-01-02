<?php

namespace App\Http\Controllers;

use App\Services\ScreenerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScreenerController extends Controller
{
    private $svc;

    public function __construct(ScreenerService $svc)
    {
        $this->svc = $svc;
    }

    /**
     * GET /screener
     */
    public function screenerPage()
    {
        $latestDate = DB::table('ticker_indicators_daily')->where('is_deleted', 0)->max('trade_date');

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
                    'd.open',
                    'd.high',
                    'd.low',
                    'd.close',
                    'd.volume',
                    'd.ma20',
                    'd.ma50',
                    'd.ma200',
                    'd.rsi14',
                    'd.vol_ratio',
                    'd.score_total',
                    'd.signal_code',
                    'd.volume_label_code',
                ])
                ->orderByDesc('d.signal_code')
                ->orderByDesc('d.volume_label_code')
                ->orderByDesc('d.score_total')
                ->get()
                ->map(function ($r) {
                    $r->signal_name = $this->svc->signalName((int) $r->signal_code);
                    $r->volume_label_name = $this->svc->volumeLabelName($r->volume_label_code !== null ? (int) $r->volume_label_code : null);
                    return $r;
                });
        }

        return view('screener.index', [
            'trade_date' => $latestDate,
            'rows' => $rows,
        ]);
    }

    /**
     * GET /screener/candidates?date=YYYY-MM-DD (optional)
     */
    public function candidates(Request $request)
    {
        $date = $request->query('date');

        // validasi ringan date
        if ($date !== null && !$this->isValidYmd($date)) {
            return response()->view('screener.candidates', [
                'trade_date' => null,
                'rows' => collect(),
                'error' => 'Parameter date harus format YYYY-MM-DD',
            ], 422);
        }

        $data = $this->svc->getCandidatesPageData($date);

        return view('screener.candidates', $data);
    }

    /**
     * GET /screener/buylist-today?today=YYYY-MM-DD&capital=9000000
     */
    public function buylistToday(Request $request)
    {
        $today = $request->query('today');
        if ($today !== null && !$this->isValidYmd($today)) {
            $today = null;
        }

        // capital: query > session > null
        $capital = $request->query('capital');
        $capital = $capital !== null ? $this->toPositiveNumber($capital) : null;

        if ($capital === null) {
            $capital = session('trade_capital');
            $capital = $capital !== null ? (float) $capital : null;
            if ($capital !== null && $capital <= 0) $capital = null;
        }

        $data = $this->svc->getTodayBuylistData($today, $capital);
        $reco = $this->svc->getTodayRecommendations($today, $capital, null, $data);

        return view('screener.buylist_today', [
            'today'       => $data['today'] ?? ($today ?: date('Y-m-d')),
            'eod_date'    => $data['eod_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'calendar_ok' => $data['calendar_ok'] ?? null,
            'capital'     => $data['capital'] ?? $capital,
            'rows'        => $data['rows'] ?? collect(),

            'picks'       => $reco['picks'] ?? collect(),
            'note'        => $reco['note'] ?? null,
        ]);
    }

    /**
     * POST /screener/buylist-today/capital
     */
    public function setCapital(Request $request)
    {
        $cap = $this->toPositiveNumber($request->input('capital'));

        if ($cap !== null) {
            session(['trade_capital' => $cap]);
        } else {
            session()->forget('trade_capital');
        }

        // redirect balik ke halaman + bawa capital biar terlihat jelas (opsional)
        return redirect('/screener/buylist-today');
    }

    private function isValidYmd(string $s): bool
    {
        $dt = Carbon::createFromFormat('Y-m-d', $s);
        return $dt && $dt->format('Y-m-d') === $s;
    }

    private function toPositiveNumber($raw): ?float
    {
        if ($raw === null) return null;
        $raw = (string) $raw;
        // input bisa "9.000.000" atau "9000000" atau "9 000 000"
        $raw = str_replace(['.', ',', ' '], '', $raw);
        if ($raw === '' || !is_numeric($raw)) return null;

        $v = (float) $raw;
        return $v > 0 ? $v : null;
    }

    public function buylistUi(Request $request)
    {
        $today = $request->query('today');
        if ($today !== null && !$this->isValidYmd($today)) {
            $today = null;
        }

        $capital = $request->query('capital');
        $capital = $capital !== null ? $this->toPositiveNumber($capital) : null;

        if ($capital === null) {
            $capital = session('trade_capital');
            $capital = $capital !== null ? (float) $capital : null;
            if ($capital !== null && $capital <= 0) $capital = null;
        }

        // metadata ringan (optional)
        $data = $this->svc->getTodayBuylistData($today, $capital);

        return view('screener.pages.buylist', [
            'today'   => $data['today'] ?? ($today ?: date('Y-m-d')),
            'eodDate' => $data['eod_date'] ?? null,
            'capital' => $data['capital'] ?? $capital,
        ]);
    }

    public function buylistData(Request $request)
    {
        $today = $request->query('today');
        if ($today !== null && !$this->isValidYmd($today)) {
            $today = null;
        }

        $capital = $request->query('capital');
        $capital = $capital !== null ? $this->toPositiveNumber($capital) : null;

        if ($capital === null) {
            $capital = session('trade_capital');
            $capital = $capital !== null ? (float) $capital : null;
            if ($capital !== null && $capital <= 0) $capital = null;
        }

        // HITUNG SEKALI
        $data = $this->svc->getTodayBuylistData($today, $capital);
        $reco = $this->svc->getTodayRecommendations($today, $capital, null, $data);

        return response()->json([
            'today'   => $data['today'] ?? ($today ?: date('Y-m-d')),
            'eodDate' => $data['eod_date'] ?? null,
            'capital' => $data['capital'] ?? $capital,
            'rows'    => $data['rows'] ?? [],
            'reco'    => $reco,
        ]);
    }
}
