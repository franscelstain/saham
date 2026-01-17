<?php

namespace App\Repositories\MarketData;

use Illuminate\Support\Facades\DB;

class RawEodRepository
{
    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function insertMany(array $rows, int $batch = 2000): void
    {
        if (!$rows) return;

        $chunks = array_chunk($rows, max(1, $batch));
        foreach ($chunks as $c) {
            DB::table('md_raw_eod')->insert($c);
        }
    }

    /**
     * Return aggregated rows per ticker+date within a run:
     * - sources: count distinct source
     * - min_close, max_close
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function aggregateCloseRangeByRun(int $runId)
    {
        return DB::table('md_raw_eod')
            ->selectRaw('ticker_id, trade_date, COUNT(DISTINCT source) AS sources, MIN(`close`) AS min_close, MAX(`close`) AS max_close')
            ->where('run_id', $runId)
            ->whereNotNull('close')
            ->groupBy('ticker_id', 'trade_date')
            ->havingRaw('COUNT(DISTINCT source) >= 2')
            ->get();
    }

    /**
     * Count all raw points (ticker+date+source) for coverage denominator if needed.
     */
    public function countRawPoints(int $runId): int
    {
        return (int) DB::table('md_raw_eod')->where('run_id', $runId)->count();
    }
}
