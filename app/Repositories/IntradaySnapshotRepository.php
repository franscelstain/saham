<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: snapshot harga preopen/intraday untuk eksekusi (INTRADAY_LIGHT & guards).
 *
 * NOTE: This repository is also the persistence layer for CONFIRM retry budget state
 * (confirm_retry_count, confirm_last_checked_at, confirm_next_check_at).
 */
class IntradaySnapshotRepository
{
    /**
     * Return intraday snapshots keyed by ticker_id.
     *
     * Columns are intentionally nullable because v1 supports manual / partial snapshots.
     *
     * @return array<int,array{ticker_id:int,checked_at:string|null,bid1:float|null,ask1:float|null,last:float|null,open:float|null,open_or_last_exec:float|null,spread_pct:float|null,confirm_retry_count:int|null,confirm_last_checked_at:string|null,confirm_next_check_at:string|null,updated_at:string|null}>
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
                'checked_at' => !empty($r->checked_at) ? (string)$r->checked_at : null,
                'bid1' => isset($r->bid1) ? (float)$r->bid1 : null,
                'ask1' => isset($r->ask1) ? (float)$r->ask1 : null,
                'last' => isset($r->last) ? (float)$r->last : null,
                'open' => isset($r->open) ? (float)$r->open : null,
                'open_or_last_exec' => isset($r->open_or_last_exec) ? (float)$r->open_or_last_exec : null,
                'spread_pct' => isset($r->spread_pct) ? (float)$r->spread_pct : null,

                // Retry state (nullable for backward compat)
                'confirm_retry_count' => isset($r->confirm_retry_count) ? (int)$r->confirm_retry_count : null,
                'confirm_last_checked_at' => !empty($r->confirm_last_checked_at) ? (string)$r->confirm_last_checked_at : null,
                'confirm_next_check_at' => !empty($r->confirm_next_check_at) ? (string)$r->confirm_next_check_at : null,

                'updated_at' => !empty($r->updated_at) ? (string)$r->updated_at : null,
            ];
        }
        return $out;
    }

    public function hasAnySnapshot(string $tradeDate): bool
    {
        if (!$this->tableExists('watchlist_intraday_snapshots')) return false;
        return DB::table('watchlist_intraday_snapshots')->where('trade_date', $tradeDate)->limit(1)->exists();
    }

    /**
     * Persist retry budget state for a given ticker snapshot.
     *
     * Fail-soft: returns false when table missing or update touches 0 rows.
     */
    public function updateConfirmRetryState(string $tradeDate, int $tickerId, int $retryCount, ?string $lastCheckedAt, ?string $nextCheckAt): bool
    {
        if ($tickerId <= 0) return false;
        if (!$this->tableExists('watchlist_intraday_snapshots')) return false;

        try {
            $upd = DB::table('watchlist_intraday_snapshots')
                ->where('trade_date', $tradeDate)
                ->where('ticker_id', $tickerId)
                ->update([
                    'confirm_retry_count' => (int)$retryCount,
                    'confirm_last_checked_at' => $lastCheckedAt,
                    'confirm_next_check_at' => $nextCheckAt,
                    'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
                ]);
            return $upd > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Upsert intraday snapshot for (trade_date, ticker_id).
     *
     * @param array<string,mixed> $fields
     */
    public function upsertSnapshot(string $tradeDate, int $tickerId, string $tickerCode, array $fields): void
    {
        if ($tickerId <= 0) return;
        if (!$this->tableExists('watchlist_intraday_snapshots')) return;

        $now = now();
        $payload = [
            'trade_date' => $tradeDate,
            'ticker_id' => $tickerId,
            'ticker_code' => strtoupper(trim($tickerCode)),
            'updated_at' => $now,
        ];
        // whitelist columns to avoid silent schema drift
        $allow = [
            'checked_at','bid1','ask1','last','open','open_or_last_exec','spread_pct',
            'bid2','bid3','ask2','ask3',
            'bid_lots1','bid_lots2','bid_lots3','ask_lots1','ask_lots2','ask_lots3',
        ];
        foreach ($allow as $k) {
            if (array_key_exists($k, $fields)) {
                $payload[$k] = $fields[$k];
            }
        }

        DB::table('watchlist_intraday_snapshots')->updateOrInsert(
            ['trade_date' => $tradeDate, 'ticker_id' => $tickerId],
            array_merge(['created_at' => $now], $payload)
        );
    }
}
