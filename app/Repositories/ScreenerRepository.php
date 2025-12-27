<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class ScreenerRepository
{
    public function getScreenerHomeData(): array
    {
        $latestDate = $this->getLatestEodDate();

        if (!$latestDate) {
            return [
                'trade_date' => null,
                'rows' => collect(),
            ];
        }

        $rows = DB::table('ticker_indicators_daily as ind')
            ->join('tickers as t', 't.ticker_id', '=', 'ind.ticker_id')
            ->where('ind.is_deleted', 0)
            ->where('t.is_deleted', 0)
            ->whereDate('ind.trade_date', $latestDate)
            ->orderByDesc('ind.score_total')
            ->limit(100)
            ->get([
                'ind.trade_date',
                't.ticker_code',
                't.company_name',
                'ind.signal_code',
                'ind.volume_label_code',
                'ind.open',
                'ind.high',
                'ind.low',
                'ind.close',
                'ind.volume',
                'ind.ma20',
                'ind.ma50',
                'ind.ma200',
                'ind.vol_ratio',
                'ind.rsi14',
                'ind.atr14',
                'ind.support_20d',
                'ind.resistance_20d',
                'ind.score_total',
            ]);

        return [
            'trade_date' => $latestDate,
            'rows' => $rows,
        ];
    }

    /**
     * Latest EOD date.
     * - If $today provided and $usePrevTradingDay=true => pick max(trade_date) < $today.
     * - Otherwise => max(trade_date).
     */
    public function getLatestEodDate(?string $today = null, bool $usePrevTradingDay = false): ?string
    {
        $q = DB::table('ticker_indicators_daily')
            ->where('is_deleted', 0);

        if ($today) {
            $op = $usePrevTradingDay ? '<' : '<=';
            $q->whereDate('trade_date', $op, $today);
        }

        $d = $q->max('trade_date');
        return $d ? (string)$d : null;
    }

    /**
     * Untuk BUYLIST-TODAY: EOD reference = trading day terakhir sebelum today.
     * Ini yang bikin Jumat -> Senin tetap valid (bukan EXPIRED_WEEK).
     */
    public function getEodReferenceForToday(string $today): ?string
    {
        return $this->getLatestEodDate($today, true);
    }

    /**
     * Candidates page data: signal (4,5) + vlabel (8,9) + trend filter + rsi<=75.
     */
    public function getCandidatesByDate(string $tradeDate, array $signalCodes, array $volumeLabelCodes)
    {
        $q = DB::table('ticker_indicators_daily as ind')
            ->join('tickers as t', 't.ticker_id', '=', 'ind.ticker_id')
            ->where('ind.is_deleted', 0)
            ->where('t.is_deleted', 0)
            ->whereDate('ind.trade_date', $tradeDate)
            ->whereIn('ind.signal_code', $signalCodes)
            ->whereIn('ind.volume_label_code', $volumeLabelCodes);

        // Trend filter: close > ma20 > ma50 > ma200
        $q->whereNotNull('ind.close')
          ->whereNotNull('ind.ma20')
          ->whereNotNull('ind.ma50')
          ->whereNotNull('ind.ma200')
          ->whereRaw('ind.close > ind.ma20')
          ->whereRaw('ind.ma20 > ind.ma50')
          ->whereRaw('ind.ma50 > ind.ma200');

        // RSI <= 75 (swing mingguan masih aman sampai sini)
        $q->whereNotNull('ind.rsi14')
          ->where('ind.rsi14', '<=', 75);

        return $q->orderByDesc('ind.score_total')
            ->get([
                'ind.ticker_id',
                't.ticker_code',
                't.company_name',
                'ind.trade_date',
                'ind.signal_code',
                'ind.volume_label_code',
                'ind.open',
                'ind.high',
                'ind.low',
                'ind.close',
                'ind.volume',
                'ind.vol_ratio',
                'ind.rsi14',
                'ind.ma20',
                'ind.ma50',
                'ind.ma200',
                'ind.atr14',
                'ind.support_20d',
                'ind.resistance_20d',
                'ind.score_total',
                'ind.score_trend',
                'ind.score_momentum',
                'ind.score_volume',
                'ind.score_breakout',
                'ind.score_risk',
            ]);
    }

    /**
     * Untuk buylist-today: kandidat berdasarkan EOD reference.
     * Repo sudah filter signal(4,5) + vlabel(8,9) + trend filter + rsi<=75.
     */
    public function getEodCandidates(string $eodDate)
    {
        return $this->getCandidatesByDate($eodDate, [4, 5], [8, 9]);
    }

    /**
     * Intraday latest per ticker untuk 1 trade_date.
     * Karena tabel kamu sekarang UNIQUE(ticker_id, trade_date), ini cukup simple.
     */
    public function getLatestIntradayByDate(string $tradeDate)
    {
        return DB::table('ticker_intraday as i')
            ->where('i.is_deleted', 0)
            ->whereDate('i.trade_date', $tradeDate)
            ->get([
                'i.ticker_id',
                'i.trade_date',
                'i.snapshot_at',
                'i.last_price',
                'i.volume_so_far',
                'i.open_price',
                'i.high_price',
                'i.low_price',
            ]);
    }

    /**
     * EOD levels: pakai indicator daily (lebih konsisten, tidak tergantung ohlc table).
     */
    public function getEodLevels(string $eodDate)
    {
        return DB::table('ticker_indicators_daily as ind')
            ->where('ind.is_deleted', 0)
            ->whereDate('ind.trade_date', $eodDate)
            ->get([
                'ind.ticker_id',
                'ind.low',
                'ind.high',
                'ind.close',
            ]);
    }

    /**
     * AvgVol20: pakai indicator daily supaya satu sumber.
     * Window function (MariaDB 10.4 OK).
     */
    public function getAvgVol20ByEodDate(string $eodDate)
    {
        $sub = DB::table('ticker_indicators_daily as ind')
            ->selectRaw("
                ind.ticker_id,
                ind.trade_date,
                AVG(COALESCE(ind.volume,0)) OVER (
                    PARTITION BY ind.ticker_id
                    ORDER BY ind.trade_date
                    ROWS BETWEEN 19 PRECEDING AND CURRENT ROW
                ) AS avg_vol20
            ")
            ->where('ind.is_deleted', 0)
            ->whereDate('ind.trade_date', '<=', $eodDate);

        return DB::query()->fromSub($sub, 'x')
            ->whereDate('x.trade_date', $eodDate)
            ->get(['x.ticker_id', 'x.avg_vol20']);
    }

    public function getNthTradingDateAfter(string $from, int $n): ?string
    {
        $dates = DB::table('ticker_indicators_daily')
            ->where('is_deleted', 0)
            ->where('trade_date', '>', $from)
            ->select('trade_date')
            ->distinct()
            ->orderBy('trade_date', 'asc')
            ->limit($n)
            ->pluck('trade_date');

        return $dates->isEmpty() ? null : $dates->last();
    }
}
