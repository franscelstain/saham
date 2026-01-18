<?php

namespace App\Repositories\MarketData;

use Illuminate\Support\Facades\DB;

class CanonicalEodRepository
{
    public function countByRunId(int $runId): int
    {
        if ($runId <= 0) return 0;

        return (int) DB::table('md_canonical_eod')
            ->where('run_id', $runId)
            ->count();
    }

    public function countByRunIdAndDate(int $runId, string $tradeDate): int
    {
        if ($runId <= 0) return 0;

        return (int) DB::table('md_canonical_eod')
            ->where('run_id', $runId)
            ->where('trade_date', $tradeDate)
            ->count();
    }

    public function countsByRunIdGroupedByDate(int $runId, array $tradeDates): array
    {
        if ($runId <= 0 || empty($tradeDates)) return [];

        $rows = DB::table('md_canonical_eod')
            ->selectRaw('trade_date, COUNT(*) AS cnt')
            ->where('run_id', $runId)
            ->whereIn('trade_date', $tradeDates)
            ->groupBy('trade_date')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r->trade_date] = (int)$r->cnt;
        }
        return $map;
    }

    /**
     * Streaming canonical rows by run_id in chunks (memory-safe).
     * Pakai chunk() supaya tidak tergantung nama PK (lebih aman).
     *
     * @param callable $fn function(\Illuminate\Support\Collection $rows): void
     */
    public function chunkByRunId(int $runId, int $batch, callable $fn): void
    {
        if ($runId <= 0) return;
        if ($batch <= 0) $batch = 2000;

        DB::table('md_canonical_eod')
            ->where('run_id', $runId)
            ->orderBy('ticker_id')
            ->orderBy('trade_date')
            ->chunk($batch, $fn);
    }

    public function deleteByRunId(int $runId): void
    {
        DB::table('md_canonical_eod')
            ->where('run_id', $runId)
            ->delete();
    }

    /**
     * Load canonical close per ticker untuk 1 tanggal (dipakai validator).
     *
     * @param int[] $tickerIds
     * @return array<int,array{close:float|null, chosen_source:string|null, adj_close:float|null}>
     */
    public function loadByRunAndDate(int $runId, string $tradeDate, array $tickerIds): array
    {
        if ($runId <= 0 || empty($tickerIds)) return [];

        $rows = DB::table('md_canonical_eod')
            ->select('ticker_id', 'close', 'chosen_source', 'adj_close')
            ->where('run_id', $runId)
            ->where('trade_date', $tradeDate)
            ->whereIn('ticker_id', $tickerIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $tid = (int) ($r->ticker_id ?? 0);
            if ($tid <= 0) continue;

            $out[$tid] = [
                'close' => $r->close !== null ? (float) $r->close : null,
                'chosen_source' => $r->chosen_source !== null ? (string) $r->chosen_source : null,
                'adj_close' => $r->adj_close !== null ? (float) $r->adj_close : null,
            ];
        }

        return $out;
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
