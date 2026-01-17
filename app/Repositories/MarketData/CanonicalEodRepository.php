<?php

namespace App\Repositories\MarketData;

use Illuminate\Support\Facades\DB;

class CanonicalEodRepository
{
    public function deleteByRunId(int $runId): void
    {
        DB::table('md_canonical_eod')
            ->where('run_id', $runId)
            ->delete();
    }

    /**
     * Upsert canonical per (run_id,ticker_id,trade_date).
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public function upsertMany(array $rows, int $batch = 2000): void
    {
        if (!$rows) return;

        $chunks = array_chunk($rows, max(1, $batch));
        foreach ($chunks as $c) {
            // Laravel 8 supports upsert
            DB::table('md_canonical_eod')->upsert(
                $c,
                ['run_id', 'ticker_id', 'trade_date'],
                ['chosen_source','reason','flags','open','high','low','close','adj_close','volume','built_at']
            );
        }
    }
}
