<?php

namespace App\Repositories;

use App\DTO\Watchlist\CandidateInput;
use Illuminate\Support\Facades\DB;

class WatchlistRepository
{
    public function getEodCandidates(): array
    {
        $eodDate = $this->getLatestEodDate();

        // previous trading day (for gap risk / prev_close)
        $prevDate = (string) (DB::table('market_calendar')
            ->where('is_trading_day', 1)
            ->where('cal_date', '<', $eodDate)
            ->max('cal_date') ?? '');

        $q = DB::table('ticker_indicators_daily as ti')
            ->join('tickers as t', function($join) {
                $join->on('ti.ticker_id', '=', 't.ticker_id')
                     ->where('t.is_deleted', 0);
            })
            // canonical OHLC
            ->leftJoin('ticker_ohlc_daily as od', function($join) use ($eodDate) {
                $join->on('ti.ticker_id', '=', 'od.ticker_id')
                     ->where('od.is_deleted', 0)
                     ->where('od.trade_date', '=', $eodDate);
            })
            // prev close (canonical)
            ->leftJoin('ticker_ohlc_daily as od_prev', function($join) use ($prevDate) {
                $join->on('ti.ticker_id', '=', 'od_prev.ticker_id')
                     ->where('od_prev.is_deleted', 0)
                     ->where('od_prev.trade_date', '=', $prevDate);
            })
            ->where('ti.is_deleted', 0)
            ->where('ti.trade_date', $eodDate)
            ->select([
                't.ticker_id',
                't.ticker_code',
                't.company_name',

                // OHLC (prefer canonical ticker_ohlc_daily)
                DB::raw('COALESCE(od.open, ti.open) as open'),
                DB::raw('COALESCE(od.high, ti.high) as high'),
                DB::raw('COALESCE(od.low, ti.low) as low'),
                DB::raw('COALESCE(od.close, ti.close) as close'),
                DB::raw('COALESCE(od.volume, ti.volume) as volume'),

                DB::raw('od_prev.close as prev_close'),

                'ti.ma20',
                'ti.ma50',
                'ti.ma200',
                'ti.rsi14',
                'ti.vol_sma20',
                'ti.vol_ratio',
                'ti.atr14',
                'ti.support_20d',
                'ti.resistance_20d',

                // decision/signal/volume label
                'ti.score_total',
                'ti.decision_code',
                'ti.signal_code',
                'ti.volume_label_code',

                // expiry fields
                'ti.signal_first_seen_date',
                'ti.signal_age_days',

                'ti.trade_date',
                DB::raw('(COALESCE(od.close, ti.close) * COALESCE(od.volume, ti.volume)) as value_est'),
            ]);

        $rows = $q->get();

        return $rows->map(fn($r) => new CandidateInput((array) $r))->all();
    }

    protected function getLatestEodDate(): string
    {
        return (string) DB::table('ticker_indicators_daily')
            ->where('is_deleted', 0)
            ->max('trade_date');
    }
}
