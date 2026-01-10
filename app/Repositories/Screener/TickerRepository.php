<?php

namespace App\Repositories\Screener;

use Illuminate\Support\Facades\DB;

class TickerRepository
{
    public function countTickers(string $search = ''): int
    {
        $q = DB::table('tickers as t')
            ->where('t.is_deleted', 0);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($w) use ($like) {
                $w->where('t.ticker_code', 'like', $like)
                  ->orWhere('t.company_name', 'like', $like);
            });
        }

        return (int) $q->count();
    }

    public function getTickersLatestOhlc(int $page, int $size, string $search, string $sort, string $dir): array
    {
        $offset = ($page - 1) * $size;

        // whitelist sort to avoid SQL injection
        $sortMap = [
            'ticker_code'  => 't.ticker_code',
            'company_name' => 't.company_name',
            'trade_date'   => 'd.trade_date',
            'close'        => 'd.close',
            'volume'       => 'd.volume',
        ];
        $orderBy = $sortMap[$sort] ?? 't.ticker_code';

        $latestDateSub = DB::table('ticker_ohlc_daily')
            ->select('ticker_id', DB::raw('MAX(trade_date) as max_trade_date'))
            ->groupBy('ticker_id');

        $q = DB::table('tickers as t')
            ->leftJoinSub($latestDateSub, 'm', function ($join) {
                $join->on('m.ticker_id', '=', 't.ticker_id');
            })
            ->leftJoin('ticker_ohlc_daily as d', function ($join) {
                $join->on('d.ticker_id', '=', 't.ticker_id')
                     ->on('d.trade_date', '=', 'm.max_trade_date');
            })
            ->where('t.is_deleted', 0);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($w) use ($like) {
                $w->where('t.ticker_code', 'like', $like)
                  ->orWhere('t.company_name', 'like', $like);
            });
        }

        $rows = $q->select([
                't.ticker_id',
                't.ticker_code',
                't.company_name',
                't.company_logo',
                'd.trade_date',
                'd.open',
                'd.high',
                'd.low',
                'd.close',
                'd.volume',
            ])
            ->orderByRaw($orderBy . ' ' . $dir)
            ->limit($size)
            ->offset($offset)
            ->get();

        return $rows->map(function ($r) {
            return [
                'ticker_id'    => (int) $r->ticker_id,
                'ticker_code'  => (string) $r->ticker_code,
                'company_name' => (string) ($r->company_name ?? ''),
                'company_logo' => (string) ($r->company_logo ?? ''),
                'trade_date'   => $r->trade_date ? (string) $r->trade_date : null,
                'open'         => $r->open !== null ? (float) $r->open : null,
                'high'         => $r->high !== null ? (float) $r->high : null,
                'low'          => $r->low !== null ? (float) $r->low : null,
                'close'        => $r->close !== null ? (float) $r->close : null,
                'volume'       => $r->volume !== null ? (int) $r->volume : null,
            ];
        })->all();
    }

    public function listAllTickerCodes(): array
    {
        return DB::table('tickers')
            ->where('is_deleted', 0)
            ->orderBy('ticker_code', 'asc')
            ->pluck('ticker_code')
            ->map(fn($x) => (string) $x)
            ->all();
    }
}
