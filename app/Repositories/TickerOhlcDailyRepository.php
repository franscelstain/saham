<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TickerOhlcDailyRepository
{
    public function getTickerIdsHavingRowOnDate(string $tradeDate, ?string $tickerCode = null): array
    {
        $q = DB::table('ticker_ohlc_daily as od')
            ->join('tickers as t', 't.ticker_id', '=', 'od.ticker_id')
            ->where('od.trade_date', $tradeDate)
            ->select('od.ticker_id')
            ->distinct()
            ->orderBy('od.ticker_id');

        if ($tickerCode !== null && $tickerCode !== '') {
            $q->where('t.ticker_code', $tickerCode);
        }

        return $q->pluck('od.ticker_id')->map(fn($v) => (int) $v)->all();
    }

    /**
     * Streaming OHLC rows ordered by ticker_id, trade_date.
     * IMPORTANT: caller should pass tickerIds chunk to keep cursor light.
     */
    public function cursorHistoryRange(string $startDate, string $endDate, array $tickerIds): \Generator
    {
        if (empty($tickerIds)) {
            return (function () { if (false) yield null; })(); // empty generator
        }

        $q = DB::table('ticker_ohlc_daily as od')
            ->whereBetween('od.trade_date', [$startDate, $endDate])
            ->whereIn('od.ticker_id', $tickerIds)
            ->select([
                'od.ticker_id',
                'od.trade_date',
                'od.open',
                'od.high',
                'od.low',
                'od.close',
                'od.adj_close',
                'od.volume',
            ])
            ->orderBy('od.ticker_id')
            ->orderBy('od.trade_date');

        // cursor() = streaming, not loading all rows
        foreach ($q->cursor() as $row) {
            yield $row;
        }
    }

    public function listForDate(string $tradeDate, ?int $runId = null): array
    {
        $q = \DB::table('ticker_ohlc_daily')
            ->select(['ticker_id', 'close', 'adj_close'])
            ->where('trade_date', $tradeDate);

        if ($runId !== null && $runId > 0) {
            $q->where('run_id', $runId);
        }

        $rows = $q->get();
        return $rows ? $rows->map(function ($r) {
            return [
                'ticker_id' => (int) $r->ticker_id,
                'close' => $r->close !== null ? (float) $r->close : null,
                'adj_close' => $r->adj_close !== null ? (float) $r->adj_close : null,
            ];
        })->all() : [];
    }

    public function mapPrevCloseVolume(string $prevDate, array $tickerIds): array
    {
        if (!$tickerIds) return [];

        $rows = DB::table('ticker_ohlc_daily')
            ->select('ticker_id', 'close', 'volume')
            ->where('trade_date', $prevDate)
            ->whereIn('ticker_id', $tickerIds)
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $tid = (int) $r->ticker_id;
            $map[$tid] = [
                'close' => $r->close !== null ? (float) $r->close : null,
                'volume' => $r->volume !== null ? (int) $r->volume : null,
            ];
        }
        return $map;
    }

    /**
     * Bulk load close+volume map for multiple dates in a single query.
     *
     * @param array<int,string> $dates
     * @param array<int,int> $tickerIds
     * @return array<string,array<int,array{close:?float,volume:?int}>>
     */
    public function mapCloseVolumeByDates(array $dates, array $tickerIds): array
    {
        $dates = array_values(array_unique(array_filter(array_map('strval', $dates))));
        if (!$dates || !$tickerIds) return [];

        $rows = DB::table('ticker_ohlc_daily')
            ->select('trade_date', 'ticker_id', 'close', 'volume')
            ->whereIn('trade_date', $dates)
            ->whereIn('ticker_id', $tickerIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $d = (string) $r->trade_date;
            $tid = (int) $r->ticker_id;

            if (!isset($out[$d])) $out[$d] = [];
            $out[$d][$tid] = [
                'close' => $r->close !== null ? (float) $r->close : null,
                'volume' => $r->volume !== null ? (int) $r->volume : null,
            ];
        }

        return $out;
    }

    public function upsertCaHints(array $rows): void
    {
        if (!$rows) return;

        \DB::table('ticker_ohlc_daily')->upsert(
            $rows,
            ['ticker_id', 'trade_date'],
            ['ca_event', 'ca_hint', 'updated_at']
        );
    }

    public function upsertMany(array $rows): int
    {
        if (empty($rows)) return 0;

        // update semua kolom kecuali key + created_at
        $update = array_values(array_diff(array_keys($rows[0]), [
            'ticker_id',
            'trade_date',
            'created_at',
        ]));

        DB::table('ticker_ohlc_daily')->upsert(
            $rows,
            ['ticker_id', 'trade_date'],
            $update
        );

        return count($rows);
    }
}
