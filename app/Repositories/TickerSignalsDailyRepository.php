<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TickerSignalsDailyRepository
{
    /**
     * Columns that belong to ticker_signals_daily.
     * Keep this list tight so compute-eod output can't silently drift.
     */
    public const COLUMNS = [
        'ticker_id',
        'trade_date',
        'decision_code',
        'signal_code',
        'volume_label_code',
        'signal_first_seen_date',
        'signal_age_days',
        'is_valid',
        'invalid_reason',
        'source',
        'is_deleted',
        'created_at',
        'updated_at',
    ];

    /**
     * Fetch previous snapshot used by SignalAgeTracker.
     *
     * @return array<int,array{signal_code:?int,signal_first_seen_date:?string}>
     */
    public function getPrevSnapshotMany(string $prevTradeDate, array $tickerIds): array
    {
        if (empty($tickerIds)) return [];

        $rows = DB::table('ticker_signals_daily')
            ->select('ticker_id', 'signal_code', 'signal_first_seen_date')
            ->where('is_deleted', 0)
            ->where('trade_date', $prevTradeDate)
            ->whereIn('ticker_id', $tickerIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->ticker_id] = [
                'signal_code' => $r->signal_code === null ? null : (int) $r->signal_code,
                'signal_first_seen_date' => $r->signal_first_seen_date === null ? null : (string) $r->signal_first_seen_date,
            ];
        }

        return $out;
    }

    /**
     * Upsert many rows.
     * Unique key: (ticker_id, trade_date)
     */
    public function upsertMany(array $rows): void
    {
        if (empty($rows)) return;

        $now = now();
        $normalized = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $row = [];
            foreach (self::COLUMNS as $c) {
                if (array_key_exists($c, $r)) $row[$c] = $r[$c];
            }
            $row['created_at'] = $row['created_at'] ?? $now;
            $row['updated_at'] = $row['updated_at'] ?? $now;
            $row['is_deleted'] = $row['is_deleted'] ?? 0;
            $row['source'] = $row['source'] ?? 'compute-eod';
            $normalized[] = $row;
        }

        if (empty($normalized)) return;

        // update columns (exclude unique key + created_at)
        $updateCols = array_values(array_diff(self::COLUMNS, ['ticker_id', 'trade_date', 'created_at']));

        DB::table('ticker_signals_daily')->upsert(
            $normalized,
            ['ticker_id', 'trade_date'],
            $updateCols
        );
    }
}
