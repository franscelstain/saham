<?php

namespace App\Repositories;

use App\DTO\Watchlist\CandidateInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WatchlistRepository
{
    private MarketCalendarRepository $calRepo;

    public function __construct(MarketCalendarRepository $calRepo)
    {
        $this->calRepo = $calRepo;
    }

    /**
     * Fail-soft table existence check.
     */
    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Latest date where BOTH OHLC and indicators exist (not necessarily "ready").
     *
     * LOCKED: watchlist.md requires one trade_date for all tickers.
     */
    public function getLatestCommonEodDate(): ?string
    {
	    if (!$this->hasTable('ticker_ohlc_daily') || !$this->hasTable('ticker_indicators_daily')) {
            return null;
        }
	    $hasSignals = $this->hasTable('ticker_signals_daily');

        try {
	        $row = DB::table('ticker_ohlc_daily as od')
	            ->selectRaw('MAX(od.trade_date) as d')
	            ->whereIn('od.trade_date', function ($q) {
	                $q->select('trade_date')->from('ticker_indicators_daily')->distinct();
	            })
	            ->when($hasSignals, function ($q) {
	                $q->whereIn('od.trade_date', function ($qq) {
	                    $qq->select('trade_date')->from('ticker_signals_daily')->distinct();
	                });
	            })
	            ->first();

            $d = $row && isset($row->d) ? (string) $row->d : '';
            return $d !== '' ? $d : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Coverage snapshot for a given EOD date.
     *
     * Used by WatchlistEngine readiness gate:
     * - canonical_coverage_pct
     * - indicators_coverage_pct
     */
    public function coverageSnapshot(string $eodDate): array
    {
        $out = [
            'trade_date' => $eodDate,
            'ticker_total' => 0,
            'canonical_count' => 0,
            'indicators_count' => 0,
	        'signals_count' => 0,
            'canonical_coverage_pct' => 0.0,
            'indicators_coverage_pct' => 0.0,
	        'signals_coverage_pct' => 0.0,
        ];

        if (!$this->hasTable('tickers')) return $out;

        try {
            $total = (int) DB::table('tickers')->where('is_deleted', 0)->count();
            $out['ticker_total'] = $total;

            if ($total <= 0) return $out;

            $canonCount = 0;
            if ($this->hasTable('ticker_ohlc_daily')) {
                $canonCount = (int) DB::table('ticker_ohlc_daily')
                    ->where('trade_date', $eodDate)
                    ->distinct('ticker_id')
                    ->count('ticker_id');
            }

            $indCount = 0;
            if ($this->hasTable('ticker_indicators_daily')) {
                $indCount = (int) DB::table('ticker_indicators_daily')
                    ->where('trade_date', $eodDate)
                    ->distinct('ticker_id')
                    ->count('ticker_id');
            }

	        $sigCount = 0;
	        if ($this->hasTable('ticker_signals_daily')) {
	            $sigCount = (int) DB::table('ticker_signals_daily')
	                ->where('trade_date', $eodDate)
	                ->distinct('ticker_id')
	                ->count('ticker_id');
	        }

            $out['canonical_count'] = $canonCount;
            $out['indicators_count'] = $indCount;
	        $out['signals_count'] = $sigCount;

            $out['canonical_coverage_pct'] = round(($canonCount / $total) * 100.0, 4);
            $out['indicators_coverage_pct'] = round(($indCount / $total) * 100.0, 4);
	        $out['signals_coverage_pct'] = round(($sigCount / $total) * 100.0, 4);

            return $out;
        } catch (\Throwable $e) {
            return $out;
        }

    }

    /**
     * Max CLOSE between two dates (inclusive). Fail-soft: returns null when data/table missing.
     */
    public function maxCloseBetween(int $tickerId, string $fromDate, string $toDate): ?int
    {
        if ($tickerId <= 0) return null;
        if (!$this->hasTable('ticker_ohlc_daily')) return null;
        if ($fromDate === '' || $toDate === '' || $fromDate > $toDate) return null;

        try {
            $v = DB::table('ticker_ohlc_daily')
                ->where('ticker_id', $tickerId)
                ->where('trade_date', '>=', $fromDate)
                ->where('trade_date', '<=', $toDate)
                ->max('close');
            if ($v === null) return null;
            return (int) round((float) $v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Max HIGH between two dates (inclusive). Fail-soft: returns null when data/table missing.
     */
    public function maxHighBetween(int $tickerId, string $fromDate, string $toDate): ?int
    {
        if ($tickerId <= 0) return null;
        if (!$this->hasTable('ticker_ohlc_daily')) return null;
        if ($fromDate === '' || $toDate === '' || $fromDate > $toDate) return null;

        try {
            $v = DB::table('ticker_ohlc_daily')
                ->where('ticker_id', $tickerId)
                ->where('trade_date', '>=', $fromDate)
                ->where('trade_date', '<=', $toDate)
                ->max('high');
            if ($v === null) return null;
            return (int) round((float) $v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build list of last N distinct trade dates from OHLC table.
     * Fallback if market_calendar isn't available.
     *
     * @return string[] dates ascending
     */
    private function lastNOhlcDates(string $endDate, int $n, bool $includeEnd): array
    {
        if (!$this->hasTable('ticker_ohlc_daily')) return [];
        $n = max(1, (int) $n);

        $op = $includeEnd ? '<=' : '<';

        $rows = DB::table('ticker_ohlc_daily')
            ->select('trade_date')
            ->where('trade_date', $op, $endDate)
            ->distinct()
            ->orderByDesc('trade_date')
            ->limit($n)
            ->pluck('trade_date');

        $dates = [];
        foreach ($rows as $d) $dates[] = (string) $d;
        $dates = array_reverse($dates); // ascending
        return $dates;
    }

    /**
     * Get EOD candidates for watchlist scoring.
     *
     * Important (LOCKED by docs/watchlist/watchlist.md):
     * - highest_high(N)/lowest_low(N) must EXCLUDE trade_date.
     * - dv20_idr uses 20 trading days ending at trade_date (INCLUDE trade_date), min_periods=20.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEodCandidates(string $eodDate): array
    {
        // --- trading dates (prefer market_calendar; fallback to OHLC distinct dates) ---
        $prevDate = null;
        $prev20Dates = [];
        $prev50Dates = [];
        $dv20Dates = [];

        $usedCalendar = false;
        if ($this->calRepo->tableExists()) {
            // Calendar exists, but may not have coverage for the requested window.
            // We'll validate and fallback to OHLC-derived dates if coverage is insufficient.
            $usedCalendar = true;

            $prevDate = $this->calRepo->previousTradingDate($eodDate);

            if ($prevDate !== null) {
                $start20 = $this->calRepo->lookbackStartDate($prevDate, 20);
                $dates20Asc = $this->calRepo->tradingDatesBetween($start20, $prevDate);
                $prev20Dates = array_reverse($dates20Asc);

                $start50 = $this->calRepo->lookbackStartDate($prevDate, 50);
                $dates50Asc = $this->calRepo->tradingDatesBetween($start50, $prevDate);
                $prev50Dates = array_reverse($dates50Asc);
            }

            $startDv20 = $this->calRepo->lookbackStartDate($eodDate, 20);
            $dv20Asc = $this->calRepo->tradingDatesBetween($startDv20, $eodDate);
            $dv20Dates = array_reverse($dv20Asc);
        }

        // Fallback if calendar is missing coverage (common in local DBs / partial backfills).
        // We need at least 20 dates for WS lookbacks and DV20, otherwise downstream scoring will hard-fail.
        if (!$usedCalendar || $prevDate === null || count($prev20Dates) < 20 || count($dv20Dates) < 20) {
            // Fallback: infer from OHLC dates
            $prev = DB::table('ticker_ohlc_daily')
                ->where('trade_date', '<', $eodDate)
                ->max('trade_date');
            $prevDate = $prev ? (string) $prev : null;

            // Exclude eodDate for lookbacks
            $prev20Asc = $this->lastNOhlcDates($eodDate, 20, false);
            $prev20Dates = array_reverse($prev20Asc);

            $prev50Asc = $this->lastNOhlcDates($eodDate, 50, false);
            $prev50Dates = array_reverse($prev50Asc);

            // Include eodDate for dv20
            $dv20Asc = $this->lastNOhlcDates($eodDate, 20, true);
            $dv20Dates = array_reverse($dv20Asc);
        }

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

        // ROC20 base date: 20th prior trading day (oldest element in prev20Dates)
        $rocDate = null;
        if (!empty($prev20Dates)) {
            $rocDate = $prev20Dates[count($prev20Dates) - 1];
        }

        // dv20_idr: average traded value (close * volume) for last 20 trading days (include eodDate)
        $dv20Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, CASE WHEN COUNT(*) >= 20 THEN AVG(close * volume) ELSE NULL END as dv20_idr, CASE WHEN COUNT(*) >= 20 THEN AVG(close * volume) ELSE NULL END as turnover20_idr, COUNT(*) as dv20_n')
            ->whereIn('trade_date', $dv20Dates)
            ->groupBy('ticker_id');

        // highest_high(20) over prior 20 trading days (exclude eodDate)
        $hh20Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MAX(high) as hh20')
            ->whereIn('trade_date', $prev20Dates)
            ->groupBy('ticker_id');

        // Intraday Light helpers
        $hh10Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MAX(high) as hh10')
            ->whereIn('trade_date', $prev10Dates)
            ->groupBy('ticker_id');

        $ll3Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MIN(low) as ll3')
            ->whereIn('trade_date', $prev3Dates)
            ->groupBy('ticker_id');

        $ll10Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MIN(low) as ll10')
            ->whereIn('trade_date', $prev10Dates)
            ->groupBy('ticker_id');

        $ll50Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MIN(low) as ll50')
            ->whereIn('trade_date', $prev50Dates)
            ->groupBy('ticker_id');

        // Dividend Swing helper: highest_high(50) prior 50 trading days (exclude eodDate)
        $hh50Sub = DB::table('ticker_ohlc_daily')
            ->selectRaw('ticker_id, MAX(high) as hh50')
            ->whereIn('trade_date', $prev50Dates)
            ->groupBy('ticker_id');

        // lowest_low(5) prior 5 trading days (exclude eodDate)
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
                    ->where('ti.trade_date', '=', $eodDate)
                    ->where('ti.is_deleted', '=', 0);
            })
            ->leftJoin('ticker_signals_daily as ts', function ($j) use ($eodDate) {
                $j->on('ts.ticker_id', '=', 't.ticker_id')
                    ->where('ts.trade_date', '=', $eodDate)
                    ->where('ts.is_deleted', '=', 0);
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
            })
            ->leftJoinSub($ll10Sub, 'll10', function ($j) {
                $j->on('ll10.ticker_id', '=', 't.ticker_id');
            })
            ->leftJoinSub($ll50Sub, 'll50', function ($j) {
                $j->on('ll50.ticker_id', '=', 't.ticker_id');
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

            // Previous day OHLC (for gap + candle flags)
            DB::raw('od_prev.open as prev_open'),
            DB::raw('od_prev.high as prev_high'),
            DB::raw('od_prev.low as prev_low'),
            DB::raw('od_prev.close as prev_close'),

            // score_total selalu dihitung per-policy di watchlist snapshot (bukan compute-eod).
            DB::raw('NULL as score_total'),

            'ts.decision_code',
            'ts.signal_code',
            'ts.signal_age_days',
            'ti.ma20', 'ti.ma50', 'ti.ma200',
            'ti.rsi14',
            'ti.atr14',
            DB::raw('CASE WHEN ti.atr14 IS NOT NULL AND od.close > 0 THEN (ti.atr14 / od.close) ELSE NULL END as atr_pct'),
            'ti.vol_sma20',
            'ti.vol_ratio',
            'ti.support_20d',
            'ti.resistance_20d',

            'ts.volume_label_code',

            // Liquidity (LOCKED naming: dv20_idr)
            DB::raw('dv.dv20_idr as dv20_idr'),
            DB::raw('dv.dv20_idr as dv20'),
            DB::raw('dv.turnover20_idr as turnover20_idr'),
            DB::raw('dv.dv20_n as dv20_n'),

            // Lookback helpers
            DB::raw('hh.hh20 as hh20'),
            DB::raw('hh50.hh50 as hh50'),
            DB::raw('ll.ll5 as ll5'),

            // Intraday Light helpers
            DB::raw('hh10.hh10 as hh10'),
            DB::raw('ll3.ll3 as ll3'),
            DB::raw('ll10.ll10 as ll10'),
            DB::raw('ll50.ll50 as ll50'),
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

        $q->where('t.is_deleted', 0)
            // Treat zero/NULL OHLC as missing. Watchlist preopen contract expects real EOD bars.
            ->whereNotNull('od.close')
            ->where('od.open', '>', 0)
            ->where('od.high', '>', 0)
            ->where('od.low', '>', 0)
            ->where('od.close', '>', 0)
            ->orderBy('t.ticker_code', 'asc');

        // IMPORTANT: return DTO objects, not arrays.
        // CandidateDerivedMetricsBuilder::enrich() expects CandidateInput.
        // Using Collection::toArray() would call CandidateInput::toArray() and
        // convert each DTO into an array, causing a TypeError.
        return $q->get()
            ->map(function ($r) {
                return new CandidateInput((array) $r);
            })
            ->values()
            ->all();
    }
}
