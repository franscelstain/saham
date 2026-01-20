<?php

namespace App\Services\Watchlist;

use App\DTO\Watchlist\CandidateInput;
use App\DTO\Watchlist\FilterOutcome;

/**
 * SRP: bentuk output JSON (array) untuk UI.
 * Tidak mengurus query DB, planning, expiry, ranking calculation.
 */
class WatchlistPresenter
{
    /**
     * @param CandidateInput $c
     * @param FilterOutcome $outcome
     * @param string $setupStatus
     * @param array $plan
     * @param array $expiry
     */
    public function baseRow(CandidateInput $c, FilterOutcome $outcome, string $setupStatus, array $plan, array $expiry): array
    {
        $gapPct = null;
        if ($c->prevClose !== null && $c->prevClose > 0 && $c->open !== null) {
            $gapPct = (($c->open - $c->prevClose) / $c->prevClose) * 100.0;
        }

        $atrPct = null;
        if ($c->atr14 !== null && $c->close > 0) {
            $atrPct = ($c->atr14 / $c->close) * 100.0;
        }

        $rangePct = null;
        if ($c->high !== null && $c->low !== null && $c->close > 0) {
            $rangePct = (($c->high - $c->low) / $c->close) * 100.0;
        }

        return [
            'tickerId' => $c->tickerId,
            'code' => $c->code,
            'name' => $c->name,

            // spec-friendly aliases (snake_case)
            'ticker_id' => $c->tickerId,
            'ticker' => $c->code,
            'company_name' => $c->name,

            'close' => $c->close,
            'ma20' => $c->ma20,
            'ma50' => $c->ma50,
            'ma200' => $c->ma200,
            'rsi' => $c->rsi,

            'open' => $c->open,
            'high' => $c->high,
            'low' => $c->low,

            'vol_sma20' => $c->volSma20,
            'vol_ratio' => $c->volRatio,
            'prev_close' => $c->prevClose,
            'gap_pct' => $gapPct,
            'atr_pct' => $atrPct,
            'range_pct' => $rangePct,

            'volume' => $c->volume,
            'valueEst' => $c->valueEst,
            'tradeDate' => $c->tradeDate,

            'volume_est' => $c->volume,
            'value_est' => $c->valueEst,
            'trade_date' => $c->tradeDate,

            // liquidity proxy (dv20)
            'dv20' => $c->dv20,
            'liq_bucket' => $c->liqBucket,

            // corporate action gate (heuristic)
            'corp_action_suspected' => (bool) $c->corpActionSuspected,
            'corp_action_ratio' => $c->corpActionRatio,

            // previous candle
            'prev_open' => $c->prevOpen,
            'prev_high' => $c->prevHigh,
            'prev_low' => $c->prevLow,

            // candle structure flags (ratios 0..1)
            'candle_body_pct' => $c->candleBodyPct,
            'candle_upper_wick_pct' => $c->candleUpperWickPct,
            'candle_lower_wick_pct' => $c->candleLowerWickPct,
            'is_inside_day' => $c->isInsideDay,
            'engulfing_type' => $c->engulfingType,
            'is_long_upper_wick' => $c->isLongUpperWick,
            'is_long_lower_wick' => $c->isLongLowerWick,

            // raw codes
            'decisionCode' => $c->decisionCode,
            'signalCode' => $c->signalCode,
            'volumeLabelCode' => $c->volumeLabelCode,

            // labels
            'decisionLabel' => $c->decisionLabel,
            'signalLabel' => $c->signalLabel,
            'volumeLabel' => $c->volumeLabel,

            'setupStatus' => $setupStatus,
            'reasons' => array_map(fn($r) => $r->code, $outcome->passed()),

            'setup_status' => $setupStatus,
            'passes_hard_filter' => true,

            'plan' => $plan,

            'expiryStatus' => $expiry['expiryStatus'] ?? 'N/A',
            'isExpired' => (bool) ($expiry['isExpired'] ?? false),
            'signalAgeDays' => $c->signalAgeDays,
            'signalFirstSeenDate' => $c->signalFirstSeenDate,

            'expiry_status' => $expiry['expiryStatus'] ?? 'N/A',
            'is_expired' => (bool) ($expiry['isExpired'] ?? false),
            'signal_age_days' => $c->signalAgeDays,
            'signal_first_seen_date' => $c->signalFirstSeenDate,
        ];
    }

    public function attachRank(array $row, array $rank): array
    {
        $row['rankScore'] = $rank['score'] ?? 0;
        $row['rankReasonCodes'] = $rank['codes'] ?? [];

        // komponen skor (ringan) untuk UI/debug
        if (isset($rank['breakdown'])) {
            $row['scoreBreakdown'] = $rank['breakdown'];
            $row['score_breakdown'] = $rank['breakdown'];
        }

        if (!empty($rank['reasons']) && config('trade.watchlist.explain_verbose', false)) {
            $row['rankReasons'] = $rank['reasons'];
        }

        return $row;
    }
}
