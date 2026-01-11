<?php

namespace App\Repositories;

use App\DTO\Watchlist\CandidateInput;
use Illuminate\Support\Facades\DB;

class WatchlistRepository
{
    public function getEodCandidates(): array
    {
        $eodDate = $this->getLatestEodDate();

        $rows = DB::table('ticker_indicators_daily as ti')
            ->join('tickers as t', function($join) {
                $join->on('ti.ticker_id', '=', 't.ticker_id')
                     ->where('t.is_deleted', 0);
            })
            ->where('ti.is_deleted', 0)
            ->where('ti.trade_date', $eodDate)
            ->select([
                't.ticker_id',
                't.ticker_code',
                't.company_name',

                // OHLC + indicators (enrichment)
                'ti.open',
                'ti.high',
                'ti.low',
                'ti.close',
                'ti.volume',

                'ti.ma20',
                'ti.ma50',
                'ti.ma200',
                'ti.rsi14',
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
                DB::raw('(ti.close * ti.volume) as value_est'),
            ])
            ->get();

        return $rows->map(fn($r) => new CandidateInput((array) $r))->all();
    }

    protected function getLatestEodDate(): string
    {
        return (string) DB::table('ticker_indicators_daily')
            ->where('is_deleted', 0)
            ->max('trade_date');
    }
}
