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

    /**
     * Stream RAW rows for a run within date range (memory-safe).
     *
     * NOTE: This is used by Phase 6 (rebuild canonical) to avoid refetch.
     *
     * @return \Generator<int,object>
     */
    public function cursorByRunAndRange(int $runId, string $from, string $to, ?int $tickerId = null)
    {
        $q = DB::table('md_raw_eod')
            ->select(
                'run_id',
                'ticker_id',
                'trade_date',
                'source',
                'open',
                'high',
                'low',
                'close',
                'adj_close',
                'volume',
                'hard_valid',
                'flags',
                'error_code'
            )
            ->where('run_id', $runId)
            ->whereBetween('trade_date', [$from, $to]);

        if ($tickerId !== null && $tickerId > 0) {
            $q->where('ticker_id', $tickerId);
        }

        // Group-friendly ordering for streaming reduce
        $q->orderBy('ticker_id')->orderBy('trade_date')->orderBy('source');

        return $q->cursor();
    }
}
