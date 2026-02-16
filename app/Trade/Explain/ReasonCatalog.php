<?php

namespace App\Trade\Explain;

use App\Trade\Explain\LabelCatalog;

class ReasonCatalog
{
    /**
     * Generic message resolver used by watchlist strict contract.
     * Keep this stable and user-facing.
     */
    public static function getMessage(string $code = ''): string
    {
        $code = trim($code);
        if ($code === '') return '';

	        // Watchlist global / confirm reasons (docs/watchlist/*)
        $wl = [
            'GL_EOD_NOT_READY' => 'EOD canonical belum siap.',

            // Global / data quality
            'GL_TICKER_STATUS_UNKNOWN' => 'Status ticker hari ini UNKNOWN (sumber status menyatakan tidak pasti / data buruk).',
	            'GL_TICKER_STATUS_DEFAULTED_REGULAR' => 'Status ticker hari ini tidak tersedia; di-default menjadi REGULAR.',

            // Weekly Swing gates
            'WS_TREND_GATE_FAIL' => 'Weekly Swing: trend utama tidak memenuhi syarat.',

            'CF_OK' => 'Sesuai guard CONFIRM.',

            // --- CONFIRM (strict) ---
            'CF_PLAN_INPUT_MISSING' => 'Data PLAN minimum tidak lengkap.',
            'CF_LIVE_INPUT_MISSING' => 'Data LIVE minimum tidak lengkap.',
            'CF_LIVE_BOOK_INVALID' => 'Data orderbook tidak valid.',
            'CF_LIVE_SNAPSHOT_STALE' => 'Snapshot LIVE tidak sinkron atau terlalu tua.',
            'CF_NOT_IN_ENTRY_WINDOW' => 'Di luar entry window.',
            'CF_IN_AVOID_WINDOW' => 'Sedang berada di avoid window.',
            'CF_BREAKOUT_TOO_EXTENDED' => 'Breakout terlalu jauh (overextended) dari entry.',
            'CF_BREAKOUT_BELOW_ENTRY' => 'Breakout belum mencapai entry (masih di bawah).',
            'CF_GAP_UP_BLOCK' => 'Gap-up terlalu besar dibanding prev close PLAN.',
            'CF_SPREAD_TOO_WIDE' => 'Spread terlalu lebar.',
            'CF_BOOK_TOO_THIN' => 'Depth orderbook terlalu tipis (Top-N lots di bawah minimum).',
            'CF_MAX_RETRY_REACHED' => 'Batas retry DELAY tercapai.',

            // Price intent / audit
            'CF_PRICE_AT_ASK1_WITHIN_CAP' => 'Harga di ask1 dan masih dalam batas cap.',
            'CF_PRICE_CLAMPED_TO_PLAN_LIMIT' => 'Harga dijepit ke plan limit (lebih konservatif).',
            'CF_PRICE_CLAMPED_TO_CAP' => 'Harga dijepit ke price cap (anti ngejar).',
            'CF_NO_BOOK' => 'Orderbook tidak lengkap.',
            'CF_SPREAD_BLOCK' => 'Spread terlalu lebar.',
            'CF_CHASE_BLOCK' => 'Harga ask melewati price cap.',
            'CF_SKIP_NO_LOTS' => 'Tidak ada lots untuk tranche ini.',
            'CF_TRANCHE_SKIPPED' => 'Tranche di-skip (invalid / tidak feasible untuk dieksekusi).',
            'CF_NO_SLICES' => 'Tidak ada tranche yang bisa dievaluasi.',

            'CF_DECISION_NOT_APPROVE' => 'Keputusan bukan APPROVE; eksekusi diblokir.',

            // Intraday Light (docs/watchlist/policy/intraday_light.md)
            'IL_CONFIRM_REQUIRED' => 'Wajib dilakukan CONFIRM intraday sebelum eksekusi.',
            'IL_DATA_INCOMPLETE' => 'Data belum lengkap untuk Intraday Light.',
            'IL_LIQ_TOO_LOW' => 'Likuiditas (DV20) di bawah minimum Intraday Light.',
            'IL_VOL_BAND_FAIL' => 'Volatilitas (ATR%) di luar band Intraday Light.',
            'IL_VOL_BAND_LOW' => 'ATR% terlalu rendah (gerak terlalu sempit).',
            'IL_VOL_BAND_HIGH' => 'ATR% terlalu tinggi (terlalu liar).',
            'IL_NO_SETUP' => 'Tidak ada setup Intraday Light yang valid (breakout/continuation).',
            'IL_STOP_TOO_WIDE' => 'Stop terlalu lebar untuk Intraday Light.',
            'IL_RR_TOO_LOW' => 'RR terlalu rendah untuk Intraday Light.',
            'IL_R_INVALID_R_LE_0' => 'R tidak valid (<= 0).',
            'IL_R_INVALID_R_LT_TICK' => 'R tidak valid (< tick).',
            'IL_VOL_CONFIRM' => 'Volume cukup untuk konfirmasi.',
            'IL_VOL_STRONG' => 'Volume kuat (konfirmasi lebih tinggi).',
            'IL_CLEAN_CANDLE' => 'Candle bersih (close dekat high, wick kecil).',
            'IL_RSI_OVERHEAT' => 'RSI terlalu panas (risk blow-off).',
            'IL_BLOWOFF_RISK' => 'Risiko blow-off (terlalu overbought).',
            'IL_CHAOS_RISK' => 'Risiko chaos (ATR tinggi + wick besar).',
        ];
        if (isset($wl[$code])) return $wl[$code];

        // Ranking/scorecard reason codes (and legacy gates).
        $msg = self::rankReasonMessage($code, []);
        if ($msg !== '' && $msg !== $code) return $msg;

        // Last-resort: humanize unknown codes so UI never shows raw code.
        return self::humanizeUnknown($code);
    }

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

    /**
     * Last-resort message for unknown codes.
     * LOCKED: UI must not show raw machine code as the user-facing message.
     */
    private static function humanizeUnknown(string $code): string
    {
        $c = trim($code);
        if ($c === '') return 'Alasan tidak diketahui.';

        // Prefix helpers
        $prefix = '';
        if (strpos($c, 'WS_') === 0) $prefix = 'Weekly Swing: ';
        elseif (strpos($c, 'DS_') === 0) $prefix = 'Dividend Swing: ';
        elseif (strpos($c, 'PT_') === 0) $prefix = 'Position Trade: ';
        elseif (strpos($c, 'IL_') === 0) $prefix = 'Intraday Light: ';
        elseif (strpos($c, 'NT_') === 0) $prefix = 'No Trade: ';
        elseif (strpos($c, 'RECO_') === 0) $prefix = 'Rekomendasi: ';
        elseif (strpos($c, 'GL_') === 0) $prefix = 'Data: ';

        // Humanize body
        $body = $c;
        // drop known prefixes so sentence reads better
        foreach (['WS_', 'DS_', 'PT_', 'IL_', 'NT_', 'RECO_', 'GL_'] as $p) {
            if (strpos($body, $p) === 0) {
                $body = substr($body, strlen($p));
                break;
            }
        }

        $body = strtolower(str_replace('_', ' ', $body));
        $body = trim($body);
        if ($body === '') return 'Alasan tidak diketahui.';

        // keep it one short sentence
        return $prefix . ucfirst($body) . '.';
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
