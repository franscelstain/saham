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
                'od.volume',
            ])
            ->orderBy('od.ticker_id')
            ->orderBy('od.trade_date');

        // cursor() = streaming, not loading all rows
        foreach ($q->cursor() as $row) {
            yield $row;
        }
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
