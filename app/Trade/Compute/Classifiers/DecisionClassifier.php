<?php

namespace App\Trade\Compute\Classifiers;

use App\Trade\Compute\Config\DecisionGuardrails;

class DecisionClassifier
{
    private DecisionGuardrails $g;

    public function __construct(DecisionGuardrails $guardrails)
    {
        $this->g = $guardrails;
    }

    /**
     * decision_code:
     * 1 False Breakout / Batal
     * 2 Hindari
     * 3 Hati - Hati
     * 4 Perlu Konfirmasi
     * 5 Layak Beli
     */
    public function classify(array $m): int
    {
        $signal = (int) ($m['signal_code'] ?? 0);
        $volLbl = (int) ($m['volume_label'] ?? 1);

        $close = isset($m['close']) ? (float)$m['close'] : 0.0;
        if ($close <= 0) return 2;

        $rsi = (isset($m['rsi14']) && $m['rsi14'] !== null) ? (float)$m['rsi14'] : null;
        $volRatio = (isset($m['vol_ratio']) && $m['vol_ratio'] !== null) ? (float)$m['vol_ratio'] : null;

        $ma20  = (isset($m['ma20'])  && $m['ma20']  !== null) ? (float)$m['ma20']  : null;
        $ma50  = (isset($m['ma50'])  && $m['ma50']  !== null) ? (float)$m['ma50']  : null;
        $ma200 = (isset($m['ma200']) && $m['ma200'] !== null) ? (float)$m['ma200'] : null;

        $res = (isset($m['resistance_20d']) && $m['resistance_20d'] !== null) ? (float)$m['resistance_20d'] : null;

        // ===== HARD OVERRIDES (anti kontradiksi) =====
        if ($signal === 10) return 1;                 // False Breakout
        if ($signal === 9 || $volLbl === 8) return 2; // Climax/Euphoria
        if ($signal === 8) return 3;                  // Distribution

        // RSI guard
        if ($rsi !== null && $rsi >= $this->g->rsiMaxBuy) return 3;

        // helper gates
        $volOkBuy =
            ($volRatio !== null && $volRatio >= $this->g->minVolRatioBuy)
            || ($volLbl >= 6);

        $volOkConfirm =
            ($volRatio !== null && $volRatio >= $this->g->minVolRatioConfirm)
            || ($volLbl >= 5);

        $rsiWarn = ($rsi !== null && $rsi >= $this->g->rsiWarn);

        $trendOk = false;
        if ($ma20 !== null && $ma50 !== null) {
            $trendOk = ($close > $ma20) && ($ma20 >= $ma50);
            if ($ma200 !== null) {
                $trendOk = $trendOk && ($ma50 >= $ma200);
            }
        }

        $isBreakout = ($res !== null) ? ($close > $res) : false;

        // ===== MAIN LOGIC =====
        // Layak Beli: trend/breakout + volume memadai + tidak warn RSI
        if (($isBreakout || $trendOk) && $volOkBuy && !$rsiWarn) {
            return 5;
        }

        // Perlu Konfirmasi: ada signal positif tapi belum memenuhi semua gate
        if (($signal === 4 || $signal === 5 || $signal === 6 || $signal === 7 || $signal === 3 || $signal === 2) && $volOkConfirm) {
            return 4;
        }

        // Kalau trend ada tapi volume masih kurang → konfirmasi / hati-hati
        if (($isBreakout || $trendOk) && !$volOkConfirm) {
            return 4;
        }

        // Default aman
        return 2;
    }
}
