<?php

namespace App\Repositories;

use App\DTO\Watchlist\CandidateInput;
use Illuminate\Support\Facades\DB;

class WatchlistRepository
{
    public function getEodCandidates(): array
    {
        $rows = DB::table('ticker_indicators_daily as ti')
            ->join('tickers as t', 't.ticker_id', '=', 'ti.ticker_id')
            ->where('ti.trade_date', $this->getLatestEodDate())
            ->select([
                't.ticker_id',
                't.ticker_code',
                't.company_name',
                'ti.close',
                'ti.ma20',
                'ti.ma50',
                'ti.ma200',
                'ti.rsi14',
                'ti.volume',

                'ti.score_total',
                'ti.decision_code',
                'ti.signal_code',
                'ti.volume_label_code',

                'ti.trade_date',
                DB::raw('(ti.close * ti.volume) as value_est'),
            ])
            ->get();

        return $rows->map(fn($r) => new CandidateInput((array) $r))->all();
    }

    protected function getLatestEodDate(): string
    {
        return DB::table('ticker_indicators_daily')
            ->where('is_deleted', 0)
            ->max('trade_date');
    }
}
