<?php

namespace App\Trade\Compute\Classifiers;

use App\Trade\Compute\Config\PatternThresholds;

class PatternClassifier
{
    private PatternThresholds $t;

    public function __construct(PatternThresholds $thresholds)
    {
        $this->t = $thresholds;
    }

    /**
     * signal_code map:
     * 0 Unknown
     * 1 Base / Sideways
     * 2 Early Uptrend
     * 3 Accumulation
     * 4 Breakout
     * 5 Strong Breakout
     * 6 Breakout Retest
     * 7 Pullback Healthy
     * 8 Distribution
     * 9 Climax / Euphoria
     * 10 False Breakout
     */
    public function classify(array $m): int
    {
        $close = isset($m['close']) ? (float)$m['close'] : 0.0;
        if ($close <= 0) return 0;

        $open = isset($m['open']) ? (float)$m['open'] : $close;
        $high = isset($m['high']) ? (float)$m['high'] : $close;
        $low  = isset($m['low'])  ? (float)$m['low']  : $close;

        $ma20  = isset($m['ma20'])  ? (float)$m['ma20']  : 0.0;
        $ma50  = isset($m['ma50'])  ? (float)$m['ma50']  : 0.0;
        $ma200 = isset($m['ma200']) ? (float)$m['ma200'] : 0.0;

        $rsi = (isset($m['rsi14']) && $m['rsi14'] !== null) ? (float)$m['rsi14'] : null;

        $volRatio = (isset($m['vol_ratio']) && $m['vol_ratio'] !== null) ? (float)$m['vol_ratio'] : null;

        $support = (isset($m['support_20d']) && $m['support_20d'] !== null) ? (float)$m['support_20d'] : null;
        $res     = (isset($m['resistance_20d']) && $m['resistance_20d'] !== null) ? (float)$m['resistance_20d'] : null;

        $range = max(1e-9, $high - $low);
        $pos = ($close - $low) / $range;           // 0..1
        $nearHigh = $pos >= 0.75;
        $nearLow  = $pos <= 0.35;

        $body = abs($close - $open);
        $bodyPct = $body / max(1e-9, $close);

        $volStrong = ($volRatio !== null && $volRatio >= $this->t->volStrong);
        $volBurst  = ($volRatio !== null && $volRatio >= $this->t->volBurst);

        $maStackBull = ($ma20 > 0 && $ma50 > 0 && $ma200 > 0 && $ma20 > $ma50 && $ma50 > $ma200);
        $earlyUp = ($ma20 > 0 && $ma50 > 0 && $close > $ma20 && $close > $ma50 && $ma20 >= $ma50);

        // breakout logic
        $isBreakout = false;
        $isFalseBreakout = false;
        $nearRes = false;

        if ($res !== null) {
            $isBreakout = $close > $res;

            // high tembus, tapi close balik (jebakan)
            $isFalseBreakout = ($high > $res) && ($close <= $res);

            // "near resistance" untuk retest (dengan toleransi 1.5%)
            $nearRes = abs($close - $res) / max(1e-9, $res) <= 0.015;
        }

        // ---------- 10 False Breakout ----------
        if ($isFalseBreakout) {
            return 10;
        }

        // ---------- 9 Climax / Euphoria ----------
        // kondisi umum: RSI sangat tinggi + volume strong (atau burst) + candle besar/close near high
        if ($rsi !== null && $rsi >= 80.0 && ($volStrong || $volBurst) && ($nearHigh || $bodyPct >= 0.03)) {
            return 9;
        }

        // ---------- 5 Strong Breakout ----------
        if ($isBreakout && $volStrong && $nearHigh && ($rsi === null || $rsi < 80.0)) {
            return 5;
        }

        // ---------- 4 Breakout ----------
        if ($isBreakout && $volBurst) {
            return 4;
        }

        // ---------- 6 Breakout Retest ----------
        // syarat minimal: sebelumnya sudah "di atas res" akan sulit dibuktikan tanpa state,
        // jadi kita pakai heuristik: close dekat resistance + trend masih ok + tidak breakdown + volume tidak mati
        if ($res !== null && $nearRes && $maStackBull && $close >= $res * 0.985) {
            // retest lebih valid kalau close di atas MA20 atau RSI masih sehat
            if (($ma20 > 0 && $close >= $ma20) || ($rsi !== null && $rsi >= 45.0)) {
                return 6;
            }
        }

        // ---------- 8 Distribution ----------
        // volume tinggi tapi close lemah / rejection (pos rendah atau close jauh dari high)
        if (($volBurst || $volStrong) && ($nearLow || $pos < 0.55)) {
            return 8;
        }

        // ---------- 7 Pullback Healthy ----------
        // uptrend bullish tapi koreksi wajar dekat MA20 / support, RSI sehat
        if ($maStackBull) {
            $nearMa20 = ($ma20 > 0) ? (abs($close - $ma20) / max(1e-9, $ma20) <= 0.02) : false;
            $nearSupport = false;
            if ($support !== null) {
                $nearSupport = abs($close - $support) / max(1e-9, $support) <= 0.03;
            }

            $rsiOk = ($rsi === null) || ($rsi >= 40.0 && $rsi <= 70.0);

            if (($nearMa20 || $nearSupport) && $rsiOk) {
                return 7;
            }
        }

        // ---------- 3 Accumulation ----------
        // base/sideways tapi volume menguat + close cukup kuat (near high)
        // (tanpa MA stack bullish kuat)
        if (!$maStackBull && $volBurst && $nearHigh) {
            return 3;
        }

        // ---------- 2 Early Uptrend ----------
        if ($earlyUp) {
            // hindari kalau sudah breakout (sudah ke-return di atas)
            // dan hindari euphoria
            if ($rsi === null || $rsi < 75.0) {
                return 2;
            }
        }

        // ---------- 1 Base / Sideways ----------
        return 1;
    }
}
