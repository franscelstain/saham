<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WatchlistRepository
{
    /**
     * Get EOD candidates for watchlist scoring.
     *
     * Important (LOCKED by docs/watchlist/policy/*.md):
     * - WEEKLY_SWING needs highest_high(20) and lowest_low(5) computed using data **before** trade_date.
     * - We compute those once per run using market_calendars to get the prior N trading dates and
     *   joining aggregated subqueries (no per-ticker queries).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEodCandidates(string $eodDate): array
    {
        // --- trading dates ---
        // prevDate is the most recent trading day BEFORE eodDate.
        $prevDate = DB::table('market_calendars')
            ->where('is_trading_day', 1)
            ->where('trade_date', '<', $eodDate)
            ->orderBy('trade_date', 'desc')
            ->value('trade_date');

        // Prior 20 trading dates BEFORE eodDate (exclude eodDate)
        $prev20Dates = DB::table('market_calendars')
            ->where('is_trading_day', 1)
            ->where('trade_date', '<', $eodDate)
            ->orderBy('trade_date', 'desc')
            ->limit(20)
            ->pluck('trade_date')
            ->toArray();

        // Prior 50 trading dates BEFORE eodDate (exclude eodDate) for longer resistance reference
        $prev50Dates = DB::table('market_calendars')
            ->where('is_trading_day', 1)
            ->where('trade_date', '<', $eodDate)
            ->orderBy('trade_date', 'desc')
            ->limit(50)
            ->pluck('trade_date')
            ->toArray();

        // Prior 5 trading dates BEFORE eodDate
        $prev5Dates = array_slice($prev20Dates, 0, 5);

        // Prior 10 / 3 trading dates BEFORE eodDate (for INTRADAY_LIGHT)
        $prev10Dates = array_slice($prev20Dates, 0, 10);
        $prev3Dates  = array_slice($prev20Dates, 0, 3);

        // ROC5 base date: 5th prior trading day (oldest element in prev5Dates)
        $roc5Date = null;
        if (!empty($prev5Dates)) {
            $roc5Date = $prev5Dates[count($prev5Dates) - 1];
        }

        // ROC base date: 20th prior trading day (oldest element in prev20Dates)
        $rocDate = null;
        if (!empty($prev20Dates)) {
            $rocDate = $prev20Dates[count($prev20Dates) - 1];
        }

        // dv20 dates: includes eodDate + previous 19 days (existing behavior) for liquidity
        $dv20Dates = DB::table('market_calendars')
            ->where('is_trading_day', 1)
            ->where('trade_date', '<=', $eodDate)
            ->orderBy('trade_date', 'desc')
            ->limit(20)
            ->pluck('trade_date')
            ->toArray();

        // dv20: average traded value (close * volume)
        $dv20Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, AVG(close * volume) as dv20')
            ->whereIn('trade_date', $dv20Dates)
            ->groupBy('ticker_id');

        // Weekly Swing helpers:
        // - hh20: max(high) over prior 20 trading days (exclude eodDate)
        // - ll5 : min(low) over prior 5 trading days (exclude eodDate)
        $hh20Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MAX(high) as hh20')
            ->whereIn('trade_date', $prev20Dates)
            ->groupBy('ticker_id');

        // Intraday Light helpers:
        // - hh10: max(high) over prior 10 trading days (exclude eodDate)
        // - ll3 : min(low) over prior 3 trading days (exclude eodDate)
        $hh10Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MAX(high) as hh10')
            ->whereIn('trade_date', $prev10Dates)
            ->groupBy('ticker_id');

        $ll3Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MIN(low) as ll3')
            ->whereIn('trade_date', $prev3Dates)
            ->groupBy('ticker_id');

        // Dividend Swing helper:
        // - hh50: max(high) over prior 50 trading days (exclude eodDate)
        $hh50Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MAX(high) as hh50')
            ->whereIn('trade_date', $prev50Dates)
            ->groupBy('ticker_id');

        $ll5Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MIN(low) as ll5')
            ->whereIn('trade_date', $prev5Dates)
            ->groupBy('ticker_id');

        $q = DB::table('tickers as t')
            ->join('ticker_ohlc_daily as od', function ($j) use ($eodDate) {
                $j->on('od.ticker_id', '=', 't.ticker_id')
                  ->where('od.trade_date', '=', $eodDate);
            })
            ->leftJoin('ticker_indicators_daily as ti', function ($j) use ($eodDate) {
                $j->on('ti.ticker_id', '=', 't.ticker_id')
                  ->where('ti.trade_date', '=', $eodDate);
            })
            ->leftJoin('ticker_ohlc_daily as od_prev', function ($j) use ($prevDate) {
                $j->on('od_prev.ticker_id', '=', 't.ticker_id')
                  ->where('od_prev.trade_date', '=', $prevDate);
            })
            ->leftJoinSub($dv20Sub, 'dv', function ($j) {
                $j->on('dv.ticker_id', '=', 't.ticker_id');
            })
            ->leftJoinSub($hh20Sub, 'hh', function ($j) {
                $j->on('hh.ticker_id', '=', 't.ticker_id');
            })
            ->leftJoinSub($hh10Sub, 'hh10', function ($j) {
                $j->on('hh10.ticker_id', '=', 't.ticker_id');
            })
            ->leftJoinSub($hh50Sub, 'hh50', function ($j) {
                $j->on('hh50.ticker_id', '=', 't.ticker_id');
            })
            ->leftJoinSub($ll5Sub, 'll', function ($j) {
                $j->on('ll.ticker_id', '=', 't.ticker_id');
            })
            ->leftJoinSub($ll3Sub, 'll3', function ($j) {
                $j->on('ll3.ticker_id', '=', 't.ticker_id');
            });

        if ($rocDate !== null) {
            $q->leftJoin('ticker_ohlc_daily as od_roc', function ($j) use ($rocDate) {
                $j->on('od_roc.ticker_id', '=', 't.ticker_id')
                  ->where('od_roc.trade_date', '=', $rocDate);
            });
        }

        if ($roc5Date !== null) {
            $q->leftJoin('ticker_ohlc_daily as od_roc5', function ($j) use ($roc5Date) {
                $j->on('od_roc5.ticker_id', '=', 't.ticker_id')
                  ->where('od_roc5.trade_date', '=', $roc5Date);
            });
        }

        $q->select([
            't.ticker_id',
            't.ticker_code',

            // OHLC (trade_date)
            'od.open', 'od.high', 'od.low', 'od.close', 'od.volume',

            // Previous close (for gap)
            DB::raw('od_prev.close as prev_close'),

            // Indicators (may be null)
            'ti.score_total',
            'ti.signal_code',
            'ti.signal_age_days',
            'ti.ma20', 'ti.ma50', 'ti.ma200',
            'ti.rsi14',
            'ti.atr14',
            'ti.vol_sma20',
            'ti.vol_ratio',
            'ti.support_20d',
            'ti.resistance_20d',

            // Liquidity
            DB::raw('dv.dv20 as dv20'),

            // Weekly Swing helpers
            DB::raw('hh.hh20 as hh20'),
            DB::raw('hh50.hh50 as hh50'),
            DB::raw('ll.ll5 as ll5'),

            // Intraday Light helpers
            DB::raw('hh10.hh10 as hh10'),
            DB::raw('ll3.ll3 as ll3'),
        ]);

        if ($rocDate !== null) {
            $q->addSelect(DB::raw('od_roc.close as close_20ago'));
            $q->addSelect(DB::raw('CASE WHEN od_roc.close IS NOT NULL AND od_roc.close > 0 THEN (od.close / od_roc.close) - 1 ELSE NULL END as roc20'));
        } else {
            $q->addSelect(DB::raw('NULL as close_20ago'));
            $q->addSelect(DB::raw('NULL as roc20'));
        }

        if ($roc5Date !== null) {
            $q->addSelect(DB::raw('od_roc5.close as close_5ago'));
            $q->addSelect(DB::raw('CASE WHEN od_roc5.close IS NOT NULL AND od_roc5.close > 0 THEN (od.close / od_roc5.close) - 1 ELSE NULL END as roc5'));
        } else {
            $q->addSelect(DB::raw('NULL as close_5ago'));
            $q->addSelect(DB::raw('NULL as roc5'));
        }

        $q->where('t.is_active', 1)
          ->whereNotNull('od.close')
          ->orderBy('t.ticker_code', 'asc');

        return $q->get()->map(function ($r) {
            return (array) $r;
        })->toArray();
    }
}
