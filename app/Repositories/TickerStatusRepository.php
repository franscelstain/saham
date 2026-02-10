<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRP: read ticker status (special notations, suspension, trading mechanism) for a trade date.
 * Contract: docs/watchlist/watchlist.md Section 2.6
 *
 * Anti salah tafsir:
 * - Watchlist hanya memakai status yang tepat pada trade_date yang sedang dieksekusi.
 * - Jika tidak ada row untuk trade_date tsb, watchlist menganggap DEFAULT=REGULAR (bukan UNKNOWN).
 * - UNKNOWN hanya boleh muncul jika row hari ini memang bertanda UNKNOWN / kualitas data buruk.
 */
class TickerStatusRepository
{

private function dateColumn(): string
{
    // docs/watchlist/schema.md: column may be `asof_date` (preferred) or legacy `trade_date`.
    try {
        if (Schema::hasColumn('ticker_status_daily', 'asof_date')) return 'asof_date';
    } catch (\Throwable $e) { /* ignore */ }
    return 'trade_date';
}

    /**
     * Return status rows STRICTLY for the provided tradeDate.
     *
     * NOTE:
     * - This function DOES NOT provide carry-forward/as-of behavior.
     * - Missing status for a ticker is handled by the WatchlistEngine as DEFAULT=REGULAR.
     *
     * @return array<int,array{
     *   ticker_id:int,
     *   status_asof_trade_date:string,
     *   status_quality:string, // OK|UNKNOWN
     *   is_suspended:bool,
     *   special_notations:array<int,string>,
     *   trading_mechanism:string
     * }>
     */
    public function statusByTickerOnDate(string $tradeDate): array
    {
        if (!$this->tableExists('ticker_status_daily')) return [];

        $dc = $this->dateColumn();

        // best-effort: status_quality column is optional (older DBs). Default to OK.
        $cols = ['ticker_id', 'is_suspended', 'special_notations', 'trading_mechanism'];
        try {
            if (Schema::hasColumn('ticker_status_daily', 'status_quality')) $cols[] = 'status_quality';
        } catch (\Throwable $e) { /* ignore */ }

        $rows = DB::table('ticker_status_daily')
            ->where($dc, $tradeDate)
            ->get($cols);

        $out = [];
        foreach ($rows as $r) {
            $tid = (int)($r->ticker_id ?? 0);
            if ($tid <= 0) continue;

            $q = strtoupper(trim((string)($r->status_quality ?? 'OK')));
            if ($q !== 'OK' && $q !== 'UNKNOWN') $q = 'OK';

            $out[$tid] = [
                'ticker_id' => $tid,
                'status_asof_trade_date' => (string)$tradeDate,
                'status_quality' => $q,
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
