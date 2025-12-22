<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ScreenerRepository
{
    public function getLatestEodDate(): ?string
    {
        return DB::table('ticker_indicators_daily')->max('trade_date');
    }

    /**
     * Ambil kandidat (signal 4 & 5) untuk 1 trade_date.
     */
    public function getCandidatesByDate(string $tradeDate, array $signalCodes = [4, 5], array $volumeLabelCodes = [8, 9]): Collection
    {
        return DB::table('ticker_indicators_daily as d')
            ->join('tickers as t', 't.ticker_id', '=', 'd.ticker_id')
            ->where('d.trade_date', $tradeDate)
            ->whereIn('d.signal_code', $signalCodes)
            ->whereIn('d.volume_label_code', $volumeLabelCodes)
            ->where('d.is_deleted', 0)
            ->where('t.is_deleted', 0)
            ->select([
                't.ticker_code',
                't.company_name',
                'd.trade_date',
                'd.close',
                'd.rsi14',
                'd.vol_ratio',
                'd.score_total',
                'd.signal_code',
                'd.volume_label_code',

                // label signal (teks)
                DB::raw("CASE d.signal_code
                    WHEN 1 THEN 'False Breakout / Batal'
                    WHEN 2 THEN 'Hati - Hati'
                    WHEN 3 THEN 'Hindari'
                    WHEN 4 THEN 'Perlu Konfirmasi'
                    WHEN 5 THEN 'Layak Beli'
                    ELSE 'Unknown'
                END AS signal_name"),

                // label volume (teks)
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
                    ELSE '-'
                END AS volume_label_name"),
            ])
            ->orderByDesc('d.signal_code')     // Layak Beli di atas
            ->orderByDesc('d.score_total')
            ->orderByDesc('d.vol_ratio')
            ->get();
    }

    /**
     * Ambil kandidat EOD (signal 4 & 5) untuk tanggal tertentu.
     */
    public function getEodCandidates(string $eodDate): Collection
    {
        return DB::table('ticker_indicators_daily as d')
            ->join('tickers as t', 't.ticker_id', '=', 'd.ticker_id')
            ->where('d.trade_date', $eodDate)
            ->whereIn('d.signal_code', [4, 5])
            ->whereIn('d.volume_label_code', [8, 9])
            ->where('d.is_deleted', 0)
            ->where('t.is_deleted', 0)
            ->select([
                't.ticker_id',
                't.ticker_code',
                't.company_name',
                'd.trade_date as eod_date',
                'd.signal_code',
                'd.volume_label_code',
                'd.score_total',
                'd.rsi14',
                'd.vol_ratio',
                'd.close as eod_close',

                DB::raw("CASE d.signal_code
                    WHEN 4 THEN 'Perlu Konfirmasi'
                    WHEN 5 THEN 'Layak Beli'
                    ELSE 'Other' END AS signal_name"),

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
            ->orderByDesc('d.score_total')
            ->get();
    }

    /**
     * Snapshot intraday terbaru hari ini per ticker.
     * Asumsi tabel: ticker_intraday(ticker_id, trade_date, snapshot_at, last_price, volume_so_far)
     */
    public function getLatestIntradayByDate(string $today): Collection
    {
        $latest = DB::table('ticker_intraday')
            ->select('ticker_id', DB::raw('MAX(snapshot_at) as snapshot_at'))
            ->where('trade_date', $today)
            ->groupBy('ticker_id');

        return DB::table('ticker_intraday as i')
            ->joinSub($latest, 'x', function ($join) {
                $join->on('x.ticker_id', '=', 'i.ticker_id')
                     ->on('x.snapshot_at', '=', 'i.snapshot_at');
            })
            ->where('i.trade_date', $today)
            ->select([
                'i.ticker_id',
                'i.trade_date',
                'i.snapshot_at',
                'i.last_price',
                'i.volume_so_far',
                // opsional kalau ada:
                'i.open_price',
                'i.high_price',
                'i.low_price',
            ])
            ->get();
    }

    /**
     * Ambil level EOD kemarin (low & close) untuk validasi harga.
     */
    public function getEodLevels(string $eodDate): Collection
    {
        return DB::table('ticker_ohlc_daily')
            ->where('trade_date', $eodDate)
            ->where('is_deleted', 0)
            ->select('ticker_id', 'low', 'close')
            ->get();
    }

    /**
     * Avg volume 20 hari terakhir sebelum/hingga EOD date (exclude today intraday).
     * Cara aman: pakai 20 bar terakhir dari ohlc_daily.
     */
    public function getAvgVol20ByEodDate(string $eodDate): Collection
    {
        // MySQL 8: window function paling enak
        // Kalau MySQL kamu < 8, bilang, nanti gue kasih versi non-window.
        $sql = "
            SELECT ticker_id, AVG(volume) AS avg_vol20
            FROM (
                SELECT ticker_id, volume,
                       ROW_NUMBER() OVER (PARTITION BY ticker_id ORDER BY trade_date DESC) AS rn
                FROM ticker_ohlc_daily
                WHERE trade_date <= ?
                  AND is_deleted = 0
            ) z
            WHERE rn <= 20
            GROUP BY ticker_id
        ";

        return collect(DB::select($sql, [$eodDate]));
    }
}
