<?php

namespace App\Trade\Compute\Classifiers;

class DecisionClassifier
{
    /**
     * Decision code (1..5):
     * 1 False Breakout / Batal
     * 2 Hindari
     * 3 Hati - Hati
     * 4 Perlu Konfirmasi
     * 5 Layak Beli
     *
     * Prinsip:
     * - Decision ini EOD-based (bukan entry intraday).
     * - Tidak boleh "Layak Beli" kalau trend belum rapi.
     * - RSI terlalu tinggi => minimal "Perlu Konfirmasi" atau "Hati-hati" (anti-chase).
     * - Breakout tanpa volume => "Perlu Konfirmasi".
     */
    public function classify(array $m): int
    {
        // Required metrics
        $close = $m['close'] ?? null;
        $ma20  = $m['ma20'] ?? null;
        $ma50  = $m['ma50'] ?? null;
        $ma200 = $m['ma200'] ?? null;
        $rsi   = $m['rsi14'] ?? null;
        $volRatio = $m['vol_ratio'] ?? null;
        $res20 = $m['resistance_20d'] ?? null;
        $high  = $m['high'] ?? null;

        if ($close === null || $ma20 === null || $ma50 === null || $ma200 === null || $rsi === null) {
            // data kurang => jangan kasih sinyal agresif
            return 4; // Perlu Konfirmasi
        }

        $close = (float)$close;
        $ma20 = (float)$ma20; $ma50 = (float)$ma50; $ma200 = (float)$ma200;
        $rsi = (float)$rsi;

        $trendOk = ($close > $ma20) && ($ma20 > $ma50) && ($ma50 > $ma200);

        // RSI guardrails (anti-chase)
        $rsiMaxBuy = (float) config('trade.watchlist.rsi_max', 70);
        $rsiWarn   = (float) config('trade.compute.rsi_warn', 66);

        $rsiOver = $rsi > $rsiMaxBuy;
        $rsiHot  = $rsi > $rsiWarn;

        // Volume guardrails
        $minVolRatioBuy = (float) config('trade.compute.min_vol_ratio_buy', 1.5);
        $minVolRatioConfirm = (float) config('trade.compute.min_vol_ratio_confirm', 1.0);

        $vr = ($volRatio === null) ? null : (float)$volRatio;
        $volStrong = ($vr !== null) && ($vr >= $minVolRatioBuy);
        $volOk = ($vr !== null) && ($vr >= $minVolRatioConfirm);

        // Breakout / false breakout (pakai resistance_20d bila tersedia)
        $isBreakout = false;
        $isFalseBreakout = false;

        if ($res20 !== null && $high !== null) {
            $res20 = (float)$res20;
            $high = (float)$high;

            $isBreakout = $close > $res20;
            $isFalseBreakout = ($high > $res20) && ($close <= $res20);
        }

        if ($isFalseBreakout) return 1;        // jebakan
        if (!$trendOk) return 2;               // trend belum beres => Hindari (watchlist hard filter nanti juga buang)

        if ($rsiOver) return 3;                // terlalu panas => Hati-hati

        // Trend ok, RSI aman:
        if ($isBreakout) {
            // breakout tanpa volume kuat => butuh konfirmasi
            if ($volStrong && !$rsiHot) return 5; // Layak Beli: breakout kuat, tidak chase
            return 4; // Perlu Konfirmasi
        }

        // bukan breakout:
        // bila volume ok dan rsi tidak hot => masih bisa layak (setup uptrend)
        if ($volOk && !$rsiHot) return 5;

        // sisanya tetap perlu konfirmasi
        return 4;
    }
}
