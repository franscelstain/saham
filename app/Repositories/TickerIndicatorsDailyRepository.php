<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TickerIndicatorsDailyRepository
{
    public function getPrevSnapshotMany(string $prevTradeDate, array $tickerIds): array
    {
        if (empty($tickerIds)) return [];

        $rows = DB::table('ticker_indicators_daily')
            ->where('is_deleted', 0)
            ->where('trade_date', $prevTradeDate)
            ->whereIn('ticker_id', $tickerIds)
            ->select(['ticker_id', 'signal_code', 'signal_first_seen_date'])
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->ticker_id] = [
                'signal_code' => (int) ($r->signal_code ?? 0),
                'signal_first_seen_date' => $r->signal_first_seen_date ? (string) $r->signal_first_seen_date : null,
            ];
        }

        return $map;
    }

    public function upsert(array $row): void
    {
        $this->upsertMany([$row], 1);
    }

    public function upsertMany(array $rows, int $chunkSize = 500): int
    {
        if (empty($rows)) return 0;

        $chunkSize = max(1, $chunkSize);

        // update semua kolom kecuali key + created_at
        $update = array_values(array_diff(array_keys($rows[0]), [
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
}
