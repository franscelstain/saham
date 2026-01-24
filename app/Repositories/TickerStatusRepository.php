<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: read ticker status (special notations, suspension, trading mechanism) as-of a trade date.
 * Contract: docs/watchlist/watchlist.md Section 2.6
 */
class TickerStatusRepository
{
    /**
     * @return array<int,array{
     *   ticker_id:int,
     *   trade_date:string,
     *   is_suspended:bool,
     *   special_notations:array<int,string>,
     *   trading_mechanism:string
     * }>
     */
    public function statusByTickerOnDate(string $tradeDate): array
    {
        if (!$this->tableExists('ticker_status_daily')) return [];

        $rows = DB::table('ticker_status_daily')
            ->where('trade_date', $tradeDate)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $tid = (int)($r->ticker_id ?? 0);
            if ($tid <= 0) continue;

            $out[$tid] = [
                'ticker_id' => $tid,
                'trade_date' => (string)$tradeDate,
                'is_suspended' => (bool)($r->is_suspended ?? false),
                'special_notations' => $this->parseNotations($r->special_notations ?? null),
                'trading_mechanism' => $this->normalizeMechanism((string)($r->trading_mechanism ?? 'REGULAR')),
            ];
        }
        return $out;
    }

    /**
     * Latest known status per ticker up to (<=) tradeDate.
     *
     * Used to provide STALE vs UNKNOWN quality flags.
     *
     * @return array<int,array{
     *   ticker_id:int,
     *   status_asof_trade_date:string,
     *   status_quality:string, // OK|STALE
     *   is_suspended:bool,
     *   special_notations:array<int,string>,
     *   trading_mechanism:string
     * }>
     */
    public function statusByTickerAsOf(string $tradeDate): array
    {
        if (!$this->tableExists('ticker_status_daily')) return [];

        // subquery: max(trade_date) per ticker <= asof
        $sub = DB::table('ticker_status_daily')
            ->select([
                'ticker_id',
                DB::raw('MAX(trade_date) as mx_trade_date'),
            ])
            ->where('trade_date', '<=', $tradeDate)
            ->groupBy('ticker_id');

        $rows = DB::table('ticker_status_daily as s')
            ->joinSub($sub, 'm', function ($join) {
                $join->on('s.ticker_id', '=', 'm.ticker_id')
                    ->on('s.trade_date', '=', 'm.mx_trade_date');
            })
            ->get([
                's.ticker_id',
                's.trade_date',
                's.is_suspended',
                's.special_notations',
                's.trading_mechanism',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $tid = (int)($r->ticker_id ?? 0);
            if ($tid <= 0) continue;

            $asof = (string)($r->trade_date ?? '');
            $quality = ($asof === $tradeDate) ? 'OK' : 'STALE';

            $out[$tid] = [
                'ticker_id' => $tid,
                'status_asof_trade_date' => $asof,
                'status_quality' => $quality,
                'is_suspended' => (bool)($r->is_suspended ?? false),
                'special_notations' => $this->parseNotations($r->special_notations ?? null),
                'trading_mechanism' => $this->normalizeMechanism((string)($r->trading_mechanism ?? 'REGULAR')),
            ];
        }

        return $out;
    }

    private function normalizeMechanism(string $m): string
    {
        $m = strtoupper(trim($m));
        if ($m === 'FULL_CALL_AUCTION' || $m === 'FCA') return 'FULL_CALL_AUCTION';
        return 'REGULAR';
    }

    /**
     * @param mixed $val
     * @return array<int,string>
     */
    private function parseNotations($val): array
    {
        if ($val === null) return [];
        if (is_array($val)) {
            $out = [];
            foreach ($val as $x) {
                $s = strtoupper(trim((string)$x));
                if ($s !== '') $out[] = $s;
            }
            return array_values(array_unique($out));
        }

        $s = trim((string)$val);
        if ($s === '') return [];

        // allow "E,X" or "E|X" or JSON-ish
        $s = str_replace(['|', ';'], ',', $s);
        $parts = array_filter(array_map('trim', explode(',', $s)));
        $out = [];
        foreach ($parts as $p) {
            $p = strtoupper($p);
            if ($p !== '') $out[] = $p;
        }
        return array_values(array_unique($out));
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
