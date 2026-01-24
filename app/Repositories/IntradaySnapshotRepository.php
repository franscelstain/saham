<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: snapshot harga preopen/intraday untuk eksekusi (INTRADAY_LIGHT & guards).
 */
class IntradaySnapshotRepository
{
    /**
     * @return array<int,array{ticker_id:int,open_or_last_exec:float|null,spread_pct:float|null}>
     */
    public function snapshotsByTicker(string $tradeDate): array
    {
        if (!$this->tableExists('watchlist_intraday_snapshots')) {
            return [];
        }

        $rows = DB::table('watchlist_intraday_snapshots')
            ->where('trade_date', $tradeDate)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $tid = (int)($r->ticker_id ?? 0);
            if ($tid <= 0) continue;
            $out[$tid] = [
                'ticker_id' => $tid,
                'open_or_last_exec' => isset($r->open_or_last_exec) ? (float)$r->open_or_last_exec : null,
                'spread_pct' => isset($r->spread_pct) ? (float)$r->spread_pct : null,
            ];
        }
        return $out;
    }

    public function hasAnySnapshot(string $tradeDate): bool
    {
        if (!$this->tableExists('watchlist_intraday_snapshots')) return false;
        return DB::table('watchlist_intraday_snapshots')->where('trade_date', $tradeDate)->limit(1)->exists();
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
