<?php

namespace App\Repositories;

use App\DTO\Watchlist\CandidateInput;
use Illuminate\Support\Facades\DB;

class WatchlistRepository
{
    public function getEodCandidates(?string $eodDate = null): array
    {
        if (!$eodDate) {
            // Use the latest date where BOTH indicators and canonical OHLC are available.
            $eodDate = $this->getLatestCommonEodDate();
        }

        // previous trading day (for gap risk / prev candle)
        $prevDate = (string) (DB::table('market_calendar')
            ->where('is_trading_day', 1)
            ->where('cal_date', '<', $eodDate)
            ->max('cal_date') ?? '');

        // dv20 dates: last 20 trading days BEFORE eodDate (exclude today)
        $dv20Dates = DB::table('market_calendar')
            ->where('is_trading_day', 1)
            ->where('cal_date', '<', $eodDate)
            ->orderByDesc('cal_date')
            ->limit(20)
            ->pluck('cal_date')
            ->all();


        // dv20 = avg(close*volume) for those dates
        $dv20Sub = null;
        if (!empty($dv20Dates)) {
            $dv20Sub = DB::table('ticker_ohlc_daily')
                ->select([
                    'ticker_id',
                    DB::raw('AVG(close * volume) as dv20'),
                ])
                ->where('is_deleted', 0)
                ->whereIn('trade_date', $dv20Dates)
                ->groupBy('ticker_id');
        }

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
            // prev candle (canonical)
            ->leftJoin('ticker_ohlc_daily as od_prev', function($join) use ($prevDate) {
                $join->on('ti.ticker_id', '=', 'od_prev.ticker_id')
                     ->where('od_prev.is_deleted', 0)
                     ->where('od_prev.trade_date', '=', $prevDate);
            });

        if ($dv20Sub) {
            $q = $q->leftJoinSub($dv20Sub, 'dv', function($join) {
                $join->on('ti.ticker_id', '=', 'dv.ticker_id');
            });
        }
        $select = [
            't.ticker_id',
            't.ticker_code',
            't.company_name',

            // OHLC (prefer canonical ticker_ohlc_daily)
            DB::raw('COALESCE(od.open, ti.open) as open'),
            DB::raw('COALESCE(od.high, ti.high) as high'),
            DB::raw('COALESCE(od.low, ti.low) as low'),
            DB::raw('COALESCE(od.close, ti.close) as close'),
            DB::raw('COALESCE(od.volume, ti.volume) as volume'),

            // adjusted / corporate action hints
            DB::raw('COALESCE(od.adj_close, ti.adj_close) as adj_close'),
            'ti.ca_hint',
            'ti.ca_event',
            'ti.is_valid',
            'ti.invalid_reason',

            // prev candle
            DB::raw('od_prev.open as prev_open'),
            DB::raw('od_prev.high as prev_high'),
            DB::raw('od_prev.low as prev_low'),
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

            // old proxy (still useful)
            DB::raw('(COALESCE(od.close, ti.close) * COALESCE(od.volume, ti.volume)) as value_est'),
        ];

        if ($dv20Sub) {
            $select[] = DB::raw('dv.dv20 as dv20');
            $select[] = DB::raw("NULL as liq_bucket");
        } else {
            $select[] = DB::raw('NULL as dv20');
            $select[] = DB::raw("'U' as liq_bucket");
        }

        $rows = $q->where('ti.is_deleted', 0)
            ->where('ti.trade_date', $eodDate)
            ->select($select)
            ->get();

        return $rows->map(fn($r) => new CandidateInput((array) $r))->all();
    }

    /** Latest indicators date. */
    public function getLatestIndicatorsEodDate(): string
    {
        return (string) DB::table('ticker_indicators_daily')
            ->where('is_deleted', 0)
            ->max('trade_date');
    }

    /** Latest canonical OHLC date. */
    public function getLatestCanonicalEodDate(): string
    {
        return (string) DB::table('ticker_ohlc_daily')
            ->where('is_deleted', 0)
            ->max('trade_date');
    }

    /** Latest date where both indicators & canonical OHLC exist (simple min of max dates). */
    public function getLatestCommonEodDate(): string
    {
        $ind = $this->getLatestIndicatorsEodDate();
        $ohlc = $this->getLatestCanonicalEodDate();

        if ($ind === '') return $ohlc;
        if ($ohlc === '') return $ind;

        return ($ohlc < $ind) ? $ohlc : $ind;
    }

    /** Coverage snapshot for a given date. */
    public function coverageSnapshot(string $tradeDate): array
    {
        $total = (int) DB::table('tickers')->where('is_deleted', 0)->count();
        $ind = (int) DB::table('ticker_indicators_daily')
            ->where('is_deleted', 0)
            ->where('trade_date', $tradeDate)
            ->count();
        $ohlc = (int) DB::table('ticker_ohlc_daily')
            ->where('is_deleted', 0)
            ->where('trade_date', $tradeDate)
            ->count();

        $indPct = ($total > 0) ? round(($ind / $total) * 100, 2) : null;
        $ohlcPct = ($total > 0) ? round(($ohlc / $total) * 100, 2) : null;

        return [
            'trade_date' => $tradeDate,
            'total_active_tickers' => $total,
            'indicators_rows' => $ind,
            'canonical_ohlc_rows' => $ohlc,
            'indicators_coverage_pct' => $indPct,
            'canonical_coverage_pct' => $ohlcPct,
        ];
    }

    /**
     * Max close between dates (inclusive) for one ticker.
     * Used for position trailing stop computations.
     */
    public function maxCloseBetween(int $tickerId, string $fromDate, string $toDate): ?float
    {
        if ($tickerId <= 0) return null;
        if ($fromDate === '' || $toDate === '') return null;

        try {
            $v = DB::table('ticker_ohlc_daily')
                ->where('is_deleted', 0)
                ->where('ticker_id', $tickerId)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->max('close');
            return ($v !== null) ? (float)$v : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Max high between dates (inclusive) for one ticker.
     * Used for checking whether TP1 has been touched.
     */
    public function maxHighBetween(int $tickerId, string $fromDate, string $toDate): ?float
    {
        if ($tickerId <= 0) return null;
        if ($fromDate === '' || $toDate === '') return null;

        try {
            $v = DB::table('ticker_ohlc_daily')
                ->where('is_deleted', 0)
                ->where('ticker_id', $tickerId)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->max('high');
            return ($v !== null) ? (float)$v : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
