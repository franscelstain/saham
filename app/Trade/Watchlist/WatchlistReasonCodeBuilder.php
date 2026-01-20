<?php

namespace App\Trade\Watchlist;

use App\DTO\Watchlist\CandidateInput;

/**
 * SRP: build reason_codes + timing_summary (ringkas, bisa diuji).
 */
class WatchlistReasonCodeBuilder
{
    /**
     * @param CandidateInput $c
     * @param string $setupType
     * @param string $setupStatus
     * @param array{gap_risk_high:bool,volatility_high:bool,liq_low:bool,market_risk_off:bool,corp_action_suspected?:bool} $risk
     * @param string $dow
     * @return array{reason_codes:string[],timing_summary:string}
     */
    public function build(CandidateInput $c, string $setupType, string $setupStatus, array $risk, string $dow): array
    {
        $codes = [];

        // Trend / MA
        $maBull = ($c->close > $c->ma20) && ($c->ma20 > $c->ma50) && ($c->ma50 > $c->ma200);
        if ($maBull) {
            $codes[] = 'TREND_STRONG';
            $codes[] = 'MA_ALIGN_BULL';
        }

        // Volume ratio
        if ($c->volRatio !== null && $c->volRatio >= 1.5) {
            $codes[] = 'VOL_RATIO_HIGH';
        }

        // Setup bias
        if ($setupType === 'Breakout') {
            $codes[] = 'BREAKOUT_SETUP';
            $codes[] = 'BREAKOUT_CONF_BIAS';
        } elseif ($setupType === 'Pullback') {
            $codes[] = 'PULLBACK_SETUP';
            $codes[] = 'PULLBACK_ENTRY_BIAS';
        } elseif ($setupType === 'Reversal') {
            $codes[] = 'REVERSAL_RISK';
            $codes[] = 'REVERSAL_CONFIRM';
        }

        // RSI confirm state
        if ($setupStatus === 'SETUP_CONFIRM') {
            // bukan negatif, tapi kasih warning agar butuh follow-through
            $codes[] = 'BREAKOUT_CONF_BIAS';
        }

        // Risk flags
        if (!empty($risk['gap_risk_high'])) $codes[] = 'GAP_RISK_HIGH';
        if (!empty($risk['volatility_high'])) $codes[] = 'VOLATILITY_HIGH';
        if (!empty($risk['liq_low'])) $codes[] = 'LIQ_LOW_MATCH_RISK';
        if (!empty($risk['market_risk_off'])) $codes[] = 'MARKET_RISK_OFF';
        if (!empty($risk['corp_action_suspected']) || !empty($c->corpActionSuspected)) $codes[] = 'CORP_ACTION_SUSPECTED';

        // Late week penalty
        if (in_array($dow, ['Thu', 'Fri'], true)) $codes[] = 'LATE_WEEK_ENTRY_PENALTY';

        // deterministic order (keep first-seen but remove duplicates)
        $codes = array_values(array_unique($codes));

        // Timing summary (1 kalimat)
        $parts = [];
        if (!empty($risk['corp_action_suspected']) || !empty($c->corpActionSuspected)) {
            $parts[] = 'Indikasi corporate action (split/reverse split); skip trade sampai data ternormalisasi.';
        } elseif (!empty($risk['gap_risk_high']) || !empty($risk['volatility_high'])) {
            $parts[] = 'Hindari open karena gap/volatilitas tinggi; entry terbaik setelah 09:45 saat spread stabil.';
        } elseif (!empty($risk['liq_low'])) {
            $parts[] = 'Likuiditas rendah; entry lebih aman mulai 10:00 setelah antrian mereda.';
        } else {
            if ($setupType === 'Breakout') $parts[] = 'Breakout; hindari open, entry setelah 09:20 saat spread stabil.';
            elseif ($setupType === 'Pullback') $parts[] = 'Pullback; tunggu stabil, entry fleksibel 09:35+ atau sesi 2.';
            elseif ($setupType === 'Reversal') $parts[] = 'Reversal; butuh konfirmasi, hindari 30 menit pertama.';
            else $parts[] = 'Setup netral; pilih hanya jika follow-through jelas.';
        }

        if (!empty($risk['market_risk_off'])) {
            $parts[] = 'Market risk-off; kurangi ukuran posisi.';
        }

        return [
            'reason_codes' => $codes,
            'timing_summary' => implode(' ', $parts),
        ];
    }
}
