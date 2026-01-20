<?php

namespace App\Trade\Watchlist;

use App\DTO\Watchlist\CandidateInput;
use App\DTO\Watchlist\FilterOutcome;

/**
 * SRP: menghasilkan advice tambahan untuk payload pre-open (setup_type, confidence, timing, checklist).
 */
class WatchlistAdviceService
{
    private SetupTypeClassifier $setupType;
    private WatchlistTimingEngine $timing;
    private WatchlistReasonCodeBuilder $reasonBuilder;

    public function __construct()
    {
        $this->setupType = new SetupTypeClassifier();
        $this->timing = new WatchlistTimingEngine();
        $this->reasonBuilder = new WatchlistReasonCodeBuilder();
    }

    /**
     * @param CandidateInput $c
     * @param FilterOutcome $outcome
     * @param string $setupStatus
     * @param array $row base row (sudah ada plan/rank)
     * @param string $dow Mon/Tue/Wed/Thu/Fri
     * @param string $marketRegime risk_on/neutral/risk_off
     */
    public function advise(CandidateInput $c, FilterOutcome $outcome, string $setupStatus, array $row, string $dow, string $marketRegime): array
    {
        $setupType = $this->setupType->classify($c);

        $risk = $this->riskFlags($c, $marketRegime);
        $timing = $this->timing->advise($dow, $setupType, $risk);
        $reason = $this->reasonBuilder->build($c, $setupType, $setupStatus, $risk, $dow);

        $confidence = $this->confidence((float)($row['watchlist_score'] ?? $row['rankScore'] ?? 0), $risk, $setupType);

        return [
            'setup_type' => $setupType,
            'confidence' => $confidence,
            'entry_windows' => $timing['entry_windows'],
            'avoid_windows' => $timing['avoid_windows'],
            'entry_style' => $timing['entry_style'],
            'size_multiplier' => $timing['size_multiplier'],
            'max_positions_today' => $timing['max_positions_today'],
            'reason_codes' => $this->mergeReasonCodes($outcome, $row, $reason['reason_codes'] ?? []),
            'timing_summary' => (string)($reason['timing_summary'] ?? ''),
            'pre_buy_checklist' => $this->checklist($setupType, $risk),
        ];
    }

    private function riskFlags(CandidateInput $c, string $marketRegime): array
    {
        $prev = $c->prevClose ?? null;
        $gapPct = null;
        if ($prev && $prev > 0 && $c->open !== null) {
            $gapPct = abs(($c->open - $prev) / $prev);
        }

        $atrPct = null;
        if ($c->atr14 !== null && $c->close > 0) {
            $atrPct = ($c->atr14 / $c->close);
        }

        // thresholds sederhana, bisa di-tune nanti
        $gapHigh = ($gapPct !== null && $gapPct >= 0.03);
        $volHigh = ($atrPct !== null && $atrPct >= 0.06);

        // liquidity low: untuk match (bukan hard filter). Gunakan dv20 / liq_bucket, bukan valueEst harian.
        $liqBucket = (string)($c->liqBucket ?? '');
        $dv20 = $c->dv20;

        // Default: bucket C dianggap low untuk match.
        // Bucket B bisa dianggap low kalau dv20 di bawah ambang minimal match.
        $dv20LowMin = (float) config('trade.watchlist.liq.dv20_low_match_min', (float) config('trade.watchlist.liq.dv20_b_min', 5000000000));
        $liqLow = ($liqBucket === 'C') || ($liqBucket === 'B' && $dv20 !== null && $dv20 > 0 && $dv20 < $dv20LowMin);

        return [
            'gap_risk_high' => $gapHigh,
            'volatility_high' => $volHigh,
            'liq_low' => $liqLow,
            'market_risk_off' => ($marketRegime === 'risk_off'),
            'corp_action_suspected' => (bool) $c->corpActionSuspected,
        ];
    }

    private function confidence(float $score, array $risk, string $setupType): string
    {
        // base from score (docs/WATCHLIST.md)
        // High >= 82, Medium 72-81, Low < 72
        $c = 'Low';
        if ($score >= 82) $c = 'High';
        elseif ($score >= 72) $c = 'Medium';

        // downgrade for obvious risk
        if (!empty($risk['market_risk_off']) || !empty($risk['gap_risk_high']) || !empty($risk['volatility_high'])) {
            if ($c === 'High') $c = 'Medium';
            elseif ($c === 'Medium') $c = 'Low';
        }

        // corporate action suspected = hard NO TRADE (confidence Low)
        if (!empty($risk['corp_action_suspected'])) {
            $c = 'Low';
        }

        if ($setupType === 'Reversal') {
            if ($c === 'High') $c = 'Medium';
        }

        return $c;
    }

    private function checklist(string $setupType, array $risk): array
    {
        $items = [
            'Cek spread & antrian bid/ask (hindari spread melebar).',
            'Pastikan candle follow-through sesuai setup (jangan FOMO saat spike).',
            'Pasang SL otomatis sesuai plan (jangan ditunda).',
        ];

        if (!empty($risk['corp_action_suspected'])) {
            array_unshift($items, 'Corporate action suspected (split/reverse split) → SKIP trade sampai data ternormalisasi.');
        }

        if ($setupType === 'Breakout') {
            $items[] = 'Tunggu close/hold di atas resistance intraday (minimal 5-10 menit).';
        } elseif ($setupType === 'Pullback') {
            $items[] = 'Cari rejection wicks di area MA/support (jangan buy saat jatuh).';
        } elseif ($setupType === 'Reversal') {
            $items[] = 'Wajib konfirmasi: higher low + volume masuk (kalau tidak, skip).';
        }

        if (!empty($risk['gap_risk_high'])) {
            $items[] = 'Gap besar: hindari pre-open & 30 menit pertama; tunggu spread normal.';
        }

        return array_values(array_unique($items));
    }

    private function mergeReasonCodes(FilterOutcome $outcome, array $row, array $extra): array
    {
        $codes = [];

        // from hard filter passed rules
        foreach ((array)$outcome->passed() as $r) {
            if (!empty($r->code)) $codes[] = (string)$r->code;
        }

        // from rank codes
        foreach ((array)($row['rankReasonCodes'] ?? []) as $c) {
            $codes[] = (string)$c;
        }

        foreach ($extra as $c) {
            $codes[] = (string)$c;
        }

        // map old codes -> doc-level codes (keep both if needed)
        $mapped = [];
        foreach ($codes as $c) {
            $mapped[] = $this->mapReasonCode($c);
        }

        // remove empties & dupes
        $mapped = array_values(array_filter($mapped, fn($v) => $v !== ''));
        return array_values(array_unique($mapped));
    }

    private function mapReasonCode(string $code): string
    {
        // normalize
        $code = strtoupper(trim($code));
        $map = [
            'TREND_OK' => 'TREND_STRONG',
            'LIQ_OK' => 'LIQ_OK',
            'RSI_OK' => 'RSI_OK',
        ];

        return $map[$code] ?? $code;
    }
}
