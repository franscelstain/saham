<?php

namespace App\Trade\Watchlist;

/**
 * SRP: klasifikasi market_regime dari snapshot breadth.
 * Output: risk-on / neutral / risk-off + notes.
 */
class WatchlistMarketContextService
{
    /**
     * @param array{pct_above_ma200:float|null,pct_ma_alignment:float|null,avg_rsi14:float|null,sample_size?:int} $snapshot
     * @param array{above_ma200:float,ma_alignment:float,avg_rsi14:float} $riskOn
     * @param array{above_ma200:float,ma_alignment:float,avg_rsi14:float} $riskOff
     * @return array{regime:string,notes:string}
     */
    public function classify(array $snapshot, array $riskOn, array $riskOff): array
    {
        $above = $snapshot['pct_above_ma200'] ?? null;
        $align = $snapshot['pct_ma_alignment'] ?? null;
        $rsi = $snapshot['avg_rsi14'] ?? null;

        // jika data belum cukup, fallback neutral
        if ($above === null || $align === null || $rsi === null) {
            return [
                'regime' => 'neutral',
                'notes' => 'Market breadth snapshot belum lengkap (fallback neutral).',
            ];
        }

        // risk_on
        if ($above >= (float)($riskOn['above_ma200'] ?? 55)
            && $align >= (float)($riskOn['ma_alignment'] ?? 45)
            && $rsi >= (float)($riskOn['avg_rsi14'] ?? 50)
        ) {
            return [
                'regime' => 'risk-on',
                'notes' => 'Breadth kuat: banyak ticker di atas MA200 + MA alignment + RSI rata-rata sehat.',
            ];
        }

        // risk_off
        if ($above <= (float)($riskOff['above_ma200'] ?? 40)
            && $align <= (float)($riskOff['ma_alignment'] ?? 30)
            && $rsi <= (float)($riskOff['avg_rsi14'] ?? 45)
        ) {
            return [
                'regime' => 'risk-off',
                'notes' => 'Breadth lemah: sedikit ticker di atas MA200 / MA alignment rendah / RSI rata-rata rendah.',
            ];
        }

        return [
            'regime' => 'neutral',
            'notes' => 'Breadth campuran: tidak memenuhi kriteria risk_on maupun risk_off.',
        ];
    }
}
