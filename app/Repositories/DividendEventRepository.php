<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: akses event dividen untuk policy DIVIDEND_SWING.
 */
class DividendEventRepository
{
    /**
     * @return array<int,array{ticker_id:int,cum_date:string|null,ex_date:string|null,cash_dividend:float|null,dividend_yield_est:float|null}>
     */
    public function eventsByTickerInWindow(string $fromDate, string $toDate): array
    {
        if (!$this->tableExists('ticker_dividend_events')) {
            return [];
        }

        // LOCKED by docs/watchlist/policy/dividend_swing.md:
        // Event window uses ex_date (T+2..T+12 trading days relative to exec_trade_date)
        $rows = DB::table('ticker_dividend_events')
            ->whereNotNull('ex_date')
            ->whereBetween('ex_date', [$fromDate, $toDate])
            ->orderBy('ex_date')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $tid = (int)($r->ticker_id ?? 0);
            if ($tid <= 0) continue;
            // pick nearest ex_date per ticker
            if (!isset($out[$tid]) || ((string)$r->ex_date) < (string)$out[$tid]['ex_date']) {
                $out[$tid] = [
                    'ticker_id' => $tid,
                    'cum_date' => $r->cum_date ? (string)$r->cum_date : null,
                    'ex_date' => $r->ex_date ? (string)$r->ex_date : null,
                    'cash_dividend' => isset($r->cash_dividend) ? (float)$r->cash_dividend : null,
                    'dividend_yield_est' => isset($r->dividend_yield_est) ? (float)$r->dividend_yield_est : null,
                ];
            }
        }
        return $out;
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
