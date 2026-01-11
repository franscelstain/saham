<?php

namespace App\Trade\Signals;

class SignalPatternClassifier
{
    /**
     * Kembalikan signal_code (0..10).
     * Input: array metrics EOD yg sudah ada di ticker_indicators_daily.
     */
    public function classify(array $m): int
    {
        $close = (float) ($m['close'] ?? 0);
        if ($close <= 0) return 0;

        $high = (float) ($m['high'] ?? $close);
        $low  = (float) ($m['low'] ?? $close);

        $ma20  = (float) ($m['ma20'] ?? 0);
        $ma50  = (float) ($m['ma50'] ?? 0);
        $ma200 = (float) ($m['ma200'] ?? 0);

        $rsi = (float) ($m['rsi14'] ?? 0);

        $volRatio = $m['vol_ratio'] ?? null; // kalau ada
        $volRatio = $volRatio !== null ? (float) $volRatio : null;

        $support = $m['support_20d'] ?? null;
        $res     = $m['resistance_20d'] ?? null;
        $support = $support !== null ? (float) $support : null;
        $res     = $res !== null ? (float) $res : null;

        // --- Helper
        $range = max(1e-9, $high - $low);
        $pos = ($close - $low) / $range; // 0..1 close position in range
        $nearHigh = $pos >= 0.75;

        $maStackBull = ($ma20 > 0 && $ma50 > 0 && $ma200 > 0 && $ma20 > $ma50 && $ma50 > $ma200);
        $aboveMA20 = ($ma20 > 0 && $close > $ma20);
        $aboveMA50 = ($ma50 > 0 && $close > $ma50);

        $volStrong = ($volRatio !== null && $volRatio >= 2.0);
        $volBurst  = ($volRatio !== null && $volRatio >= 1.5);

        // 9) Climax/Euphoria: RSI tinggi + volume kuat + close near high
        if ($rsi >= 80 && ($volStrong || $volBurst) && $nearHigh) {
            return 9;
        }

        // 4/5) Breakout: close > resistance
        if ($res !== null && $close > $res) {
            if ($nearHigh && ($volStrong || $volBurst)) return 5; // strong breakout
            return 4; // breakout biasa
        }

        // 10) False breakout: high sempat tembus resistance tapi close balik bawah
        if ($res !== null && $high > $res && $close <= $res) {
            return 10;
        }

        // 6) Breakout retest: close dekat resistance (retest) tapi tetap di atas MA20/MA50
        if ($res !== null && abs($close - $res) / $res <= 0.01 && ($aboveMA20 || $aboveMA50)) {
            return 6;
        }

        // 7) Pullback healthy: close turun tapi masih di atas MA20/MA50 + MA stack bullish
        if ($maStackBull && ($aboveMA20 || $aboveMA50) && $pos >= 0.35 && $pos <= 0.65) {
            return 7;
        }

        // 3) Accumulation: dekat support + volume meningkat
        if ($support !== null && abs($close - $support) / $support <= 0.02 && ($volBurst || $volStrong)) {
            return 3;
        }

        // 8) Distribution: volume kuat tapi close tidak near high (pos rendah/ tengah)
        if (($volBurst || $volStrong) && $pos < 0.55) {
            return 8;
        }

        // 2) Early uptrend: MA stack mulai bagus + close di atas MA20/50 + RSI moderate
        if ($maStackBull && $aboveMA20 && $aboveMA50 && $rsi >= 50 && $rsi <= 75) {
            return 2;
        }

        // 1) Base/Sideways: fallback saat belum ada pola jelas
        return 1;
    }
}
