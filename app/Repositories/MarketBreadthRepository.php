<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: ambil snapshot market breadth dari data EOD (ticker_indicators_daily).
 * Digunakan untuk market_regime: risk_on / neutral / risk_off.
 */
class MarketBreadthRepository
{
    /**
     * @return array{
     *   trade_date:string,
     *   sample_size:int,
     *   pct_above_ma200:float|null,
     *   pct_ma_alignment:float|null,
     *   avg_rsi14:float|null
     * }
     */
    public function snapshot(string $tradeDate): array
    {
        $row = DB::table('ticker_indicators_daily as ti')
            ->join('tickers as t', function ($join) {
                $join->on('ti.ticker_id', '=', 't.ticker_id')
                    ->where('t.is_deleted', 0);
            })
            ->where('ti.is_deleted', 0)
            ->where('ti.trade_date', $tradeDate)
            ->selectRaw('COUNT(*) as n_total')
            ->selectRaw('SUM(CASE WHEN ti.close IS NOT NULL AND ti.ma200 IS NOT NULL THEN 1 ELSE 0 END) as n_ma200')
            ->selectRaw('SUM(CASE WHEN ti.close IS NOT NULL AND ti.ma200 IS NOT NULL AND ti.close > ti.ma200 THEN 1 ELSE 0 END) as n_above_ma200')
            ->selectRaw('SUM(CASE WHEN ti.ma20 IS NOT NULL AND ti.ma50 IS NOT NULL AND ti.ma200 IS NOT NULL THEN 1 ELSE 0 END) as n_ma_stack_base')
            ->selectRaw('SUM(CASE WHEN ti.ma20 IS NOT NULL AND ti.ma50 IS NOT NULL AND ti.ma200 IS NOT NULL AND ti.ma20 > ti.ma50 AND ti.ma50 > ti.ma200 THEN 1 ELSE 0 END) as n_ma_alignment')
            ->selectRaw('AVG(CASE WHEN ti.rsi14 IS NOT NULL THEN ti.rsi14 ELSE NULL END) as avg_rsi14')
            ->first();

        $nTotal = (int)($row->n_total ?? 0);
        $nMa200 = (int)($row->n_ma200 ?? 0);
        $nAbove = (int)($row->n_above_ma200 ?? 0);
        $nStackBase = (int)($row->n_ma_stack_base ?? 0);
        $nAlign = (int)($row->n_ma_alignment ?? 0);

        $pctAbove = null;
        if ($nMa200 > 0) {
            $pctAbove = (float)(100.0 * $nAbove / $nMa200);
        }

        $pctAlign = null;
        if ($nStackBase > 0) {
            $pctAlign = (float)(100.0 * $nAlign / $nStackBase);
        }

        $avgRsi = $row->avg_rsi14 ?? null;
        $avgRsi = ($avgRsi === null) ? null : (float)$avgRsi;

        return [
            'trade_date' => $tradeDate,
            'sample_size' => $nTotal,
            'pct_above_ma200' => $pctAbove,
            'pct_ma_alignment' => $pctAlign,
            'avg_rsi14' => $avgRsi,
        ];
    }
}
