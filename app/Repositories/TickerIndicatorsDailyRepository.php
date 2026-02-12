<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TickerIndicatorsDailyRepository
{
    /**
     * Kolom yang memang disimpan di ticker_indicators_daily.
     * NOTE: kolom signal/decision dipindah ke ticker_signals_daily.
     */
    private const COLUMNS = [
        'ticker_id', 'trade_date',

        // basic
        'open', 'high', 'low', 'close', 'adj_close', 'volume',
        'basis_used', 'price_used', 'volume_used',

        // MA/RSI/ATR
        'ma20', 'ma50', 'ma200',
        'rsi14', 'atr14',

        // volume stats
        'vol_sma20', 'vol_ratio',

        // support/resistance
        'support_20d', 'resistance_20d',

        // core rollups (see docs/compute_eod.md + migration)
        'dv20_idr', 'atr14_pct', 'hh20', 'll5', 'roc20',

        // corporate action hint
        'ca_hint', 'ca_event',

        // validity
        'is_valid', 'invalid_reason',

        // meta
        'source', 'created_at', 'updated_at', 'is_deleted',
    ];

    public function upsert(array $row): void
    {
        $this->upsertMany([$row], 1);
    }

    public function upsertMany(array $rows, int $chunkSize = 500): int
    {
        if (empty($rows)) return 0;

        $chunkSize = max(1, $chunkSize);

        $rows = array_values(array_filter(array_map(fn($r) => $this->normalizeRow($r), $rows)));
        if (empty($rows)) return 0;

        // update semua kolom kecuali key + created_at
        $update = array_values(array_diff(self::COLUMNS, [
            'ticker_id',
            'trade_date',
            'created_at',
        ]));

        $total = 0;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table('ticker_indicators_daily')->upsert(
                $chunk,
                ['ticker_id', 'trade_date'],
                $update
            );
            $total += count($chunk);
        }

        return $total;
    }

    private function normalizeRow($row): ?array
    {
        $r = is_array($row) ? $row : (array) $row;

        $tid = isset($r['ticker_id']) ? (int) $r['ticker_id'] : 0;
        $date = isset($r['trade_date']) ? (string) $r['trade_date'] : null;
        if ($tid <= 0 || !$date) return null;

        // keep only known columns; anything else (signal/score) is dropped here.
        $out = ['ticker_id' => $tid, 'trade_date' => $date];
        foreach (self::COLUMNS as $col) {
            if ($col === 'ticker_id' || $col === 'trade_date') continue;
            if (array_key_exists($col, $r)) $out[$col] = $r[$col];
        }

        // set timestamps if not provided
        $out['created_at'] = $out['created_at'] ?? now();
        $out['updated_at'] = $out['updated_at'] ?? now();

        return $out;
    }
}
