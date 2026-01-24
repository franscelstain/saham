<?php

namespace App\Trade\Explain;

use App\Trade\Explain\LabelCatalog;

class ReasonCatalog
{
    public static function rankReasonCatalog(): array
    {
        // Ini “kamus tetap” untuk UI (code => message).
        // Jangan taruh angka dinamis di sini (valueEst, rr, points), itu tetap bisa dihitung UI kalau perlu.
        return [
            // --- scoring model ---
            'TREND_C_GT_MA20' => 'Trend: Close > MA20',
            'TREND_MA20_GT_MA50' => 'Trend: MA20 > MA50',
            'TREND_MA50_GT_MA200' => 'Trend: MA50 > MA200',

            'MOM_RSI_STRONG' => 'Momentum: RSI kuat',
            'MOM_RSI_OK' => 'Momentum: RSI cukup',
            'MOM_DECISION_5_BONUS' => 'Momentum: Decision 5 bonus',
            'MOM_DECISION_4_BONUS' => 'Momentum: Decision 4 bonus',
            'MOM_SIGNAL_BONUS' => 'Momentum: Signal bonus',

            'VOL_RATIO_STRONG' => 'Volume: VolRatio kuat',
            'VOL_RATIO_OK' => 'Volume: VolRatio bagus',
            'LIQ_GE_5B' => 'Likuiditas >= 5B',
            'LIQ_GE_2B' => 'Likuiditas >= 2B',
            'LIQ_GE_1B' => 'Likuiditas >= 1B',

            'RISK_RR_GE_20' => 'Risk: RR TP2 >= 2.0',
            'RISK_RR_GE_15' => 'Risk: RR TP2 >= 1.5',
            'RISK_RR_GE_12' => 'Risk: RR TP2 >= 1.2',
            'RISK_ATR_OK' => 'Risk: ATR% terkendali',
            'RISK_GAP_OK' => 'Risk: Gap kecil',

            'MARKET_RISK_ON' => 'Market: Risk-On',
            'MARKET_NEUTRAL' => 'Market: Neutral',
            'MARKET_RISK_OFF' => 'Market: Risk-Off',

            'SETUP_OK' => 'Setup dinilai OK',
            'SETUP_CONFIRM' => 'Setup perlu konfirmasi',

            'DECISION_5' => 'Decision: Layak Beli',
            'DECISION_4' => 'Decision: Perlu Konfirmasi',

            'VOL_STRONG_BURST' => 'Volume kuat (Strong Burst)',
            'VOL_BURST' => 'Volume meningkat (Burst)',
            'VOL_EARLY' => 'Mulai ada minat (Early Interest)',

            'AGE_0' => 'Sinyal hari pertama',
            'AGE_1' => 'Sinyal hari kedua',
            'AGE_2' => 'Sinyal hari ketiga',

            'AGING' => 'Sinyal mulai menua',
            'EXPIRED' => 'Sinyal sudah basi (expired)',

            'RR_GE_20' => 'RR TP2 >= 2.0',
            'RR_GE_15' => 'RR TP2 >= 1.5',
            'RR_GE_12' => 'RR TP2 >= 1.2',
            'RR_BELOW_MIN' => 'RR di bawah minimum',
            'RR_UNKNOWN' => 'RR tidak tersedia',

            'PLAN_INVALID' => 'Trade plan tidak valid',
        ];
    }

    /**
     * Pesan human-readable untuk alasan ranking.
     *
     * Convention:
     * - $code: string enum (contoh: SETUP_OK, DECISION_5, LIQ_GE_5B, SIGNAL_7, dll)
     * - $ctx: context opsional untuk render angka (rrTp2, valueEst, min, signalCode, errors, dll)
     */
    public static function rankReasonMessage(string $code = '', array $ctx = []): string
    {
        // SIGNAL_* dynamic
        if (strpos($code, 'SIGNAL_') === 0) {
            $signalCode = (int) ($ctx['signalCode'] ?? substr($code, 7));
            $label = LabelCatalog::signalLabel($signalCode); // delegator ke LabelCatalog
            return $label ?: ('Signal: #' . $signalCode);
        }

        $catalog = self::rankReasonCatalog();
        if (isset($catalog[$code])) {
            // optional: untuk kode tertentu, tambahin ctx (contoh LIQ/RR)
            // kalau ga perlu angka dinamis, langsung return
            return $catalog[$code];
        }

        // fallback supaya tidak return null
        return $code !== '' ? $code : 'Unknown reason';
    }

    private static function fmt($n, int $dec = 0): string
    {
        return number_format((float) $n, $dec, '.', '');
    }

    /**
     * Format singkat IDR untuk angka besar (contoh: 4.34B, 850M).
     * Input: value_est (close * volume) => bukan rupiah murni, tapi “nilai transaksi estimasi”
     */
    private static function formatIdrShort(float $v): string
    {
        if ($v >= 1000000000000) return self::fmt($v / 1000000000000, 2) . 'T';
        if ($v >= 1000000000)    return self::fmt($v / 1000000000, 2) . 'B';
        if ($v >= 1000000)       return self::fmt($v / 1000000, 2) . 'M';
        if ($v >= 1000)          return self::fmt($v / 1000, 2) . 'K';
        return self::fmt($v, 0);
    }
}
