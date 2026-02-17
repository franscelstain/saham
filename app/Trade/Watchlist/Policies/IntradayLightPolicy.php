<?php

namespace App\Trade\Watchlist\Policies;

use App\Trade\Watchlist\WatchlistEngine;

/**
 * INTRADAY_LIGHT PLAN policy.
 * Spec: docs/watchlist/policy/intraday_light.md
 */
class IntradayLightPolicy implements WatchlistPolicyInterface
{
    public function code(): string
    {
        return 'INTRADAY_LIGHT';
    }


    public function enrichPlanRow(array &$row, array $opts, array $policyMeta, WatchlistEngine $engine): void
    {
        if (!isset($row['plan']) || !is_array($row['plan'])) $row['plan'] = [];
        if (!isset($row['plan']['block_codes']) || !is_array($row['plan']['block_codes'])) $row['plan']['block_codes'] = [];
        if (!isset($row['plan']['is_eligible_new_entry'])) $row['plan']['is_eligible_new_entry'] = true;
        if (!isset($row['reason_codes']) || !is_array($row['reason_codes'])) $row['reason_codes'] = [];

        $levels = is_array($row['levels'] ?? null) ? (array)$row['levels'] : [];
        $entry = $levels['entry_trigger_price'] ?? null;
        $sl   = $levels['stop_loss_price'] ?? null;
        $tp1  = $levels['take_profit_1_price'] ?? ($levels['tp1_price'] ?? null);

        if ($entry === null || $sl === null || $tp1 === null) {
            $row['plan']['is_eligible_new_entry'] = false;
            $row['plan']['block_codes'][] = 'IL_LEVELS_INCOMPLETE';
            $row['reason_codes'][] = 'IL_LEVELS_INCOMPLETE';
        }

        $rval = ($entry !== null && $sl !== null && $tp1 !== null)
            ? $engine->rrRatio((int)$entry, (int)$sl, (int)$tp1)
            : null;

        if ($rval !== null && $rval < 1.6) {
            $row['reason_codes'][] = 'IL_MIN_TRADE_VIABILITY_FAIL';
            $row['plan']['is_eligible_new_entry'] = false;
            $row['plan']['block_codes'][] = 'IL_MIN_TRADE_VIABILITY_FAIL';
        }

        $row['plan']['block_codes'] = array_values(array_unique($row['plan']['block_codes']));
        $row['reason_codes'] = array_values(array_unique($row['reason_codes']));
    }

    public function defaultActionWindows(array $session): array
    {
        return ['09:20-10:15', '13:35-14:15', '15:15-close'];
    }

    public function apply(array $x, array $reasonCodes, WatchlistEngine $engine): array
    {
        $drop = false;
        $blockCodes = [];
        $entryStyle = 'Default';
        $confidence = 'High';

        $requiredOk = function (array $reqKeys) use ($x): bool {
            foreach ($reqKeys as $k) {
                if (!array_key_exists($k, $x) || $x[$k] === null) return false;
            }
            if (($x['open'] ?? 0) <= 0) return false;
            if (($x['high'] ?? 0) <= 0) return false;
            if (($x['low'] ?? 0) <= 0) return false;
            if (($x['close'] ?? 0) <= 0) return false;
            return true;
        };

        // PLAN from EOD; execute after CONFIRM
        $reasonCodes[] = 'IL_CONFIRM_REQUIRED';

        $open  = (float)($x['open'] ?? 0);
        $high  = (float)($x['high'] ?? 0);
        $low   = (float)($x['low'] ?? 0);
        $close = (float)($x['close'] ?? 0);
        $atrPct = $x['atr_pct'] ?? null;

        // Required inputs
        if (!$requiredOk(['dv20','atr_pct','vol_ratio','hh10','ll3','roc5'])) {
            $drop = true;
            $reasonCodes[] = 'IL_DATA_INCOMPLETE';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_DATA_INCOMPLETE']);
        }

        $dv20 = (float)$x['dv20'];
        if ($dv20 < 3000000000.0) {
            $drop = true;
            $reasonCodes[] = 'IL_LIQ_TOO_LOW';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_LIQ_TOO_LOW']);
        }

        $atrPctF = (float)$atrPct;
        if ($atrPctF < 0.02) {
            $drop = true;
            $reasonCodes[] = 'IL_VOL_BAND_FAIL';
            $reasonCodes[] = 'IL_VOL_BAND_LOW';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_VOL_BAND_FAIL']);
        }
        if ($atrPctF > 0.15) {
            $drop = true;
            $reasonCodes[] = 'IL_VOL_BAND_FAIL';
            $reasonCodes[] = 'IL_VOL_BAND_HIGH';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_VOL_BAND_FAIL']);
        }

        // Candle-derived
        $c = $x['candle'] ?? null;
        $upperWickPct = is_array($c) ? (float)($c['upper_wick_pct'] ?? 0.0) : 0.0;
        $range = max(1.0, (float)$high - (float)$low);
        $closePos = ((float)$close - (float)$low) / $range;
        $closePos = $this->clamp01($closePos);

        // Momentum gate (Breakout_10 OR Strong close continuation)
        $hh10 = (float)$x['hh10'];
        $ll3 = (float)$x['ll3'];
        $roc5 = (float)$x['roc5'];
        $volRatioF = (float)$x['vol_ratio'];

        $breakoutOk = ((float)$close > $hh10) && ($closePos >= 0.75) && ($volRatioF >= 1.0);
        $contOk = ($roc5 >= 0.02) && ($closePos >= 0.70) && ($volRatioF >= 1.5);

        if (!$breakoutOk && !$contOk) {
            $drop = true;
            $reasonCodes[] = 'IL_NO_SETUP';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_NO_SETUP']);
        }

        $setupOverride = $breakoutOk ? 'Breakout' : 'Continuation';
        $entryStyle = $setupOverride;

        // Build PLAN levels (EOD-based)
        $levels = $this->buildIntradayLightLevels([
            'close' => $close,
            'low' => $low,
            'll3' => $ll3,
        ], $engine);

        $entry = $levels['entry_trigger_price'] ?? null;
        $stop  = $levels['stop_loss_price'] ?? null;
        $tp1   = $levels['tp1_price'] ?? null;
        $tick  = $levels['tick_size'] ?? null;

        if (!is_numeric($entry) || !is_numeric($stop) || !is_numeric($tp1) || !is_numeric($tick) || (float)$entry <= 0 || (float)$tick <= 0) {
            $drop = true;
            $reasonCodes[] = 'IL_DATA_INCOMPLETE';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_DATA_INCOMPLETE']);
        }

        $R = (float)$entry - (float)$stop;
        if ($R <= 0) {
            $drop = true;
            $reasonCodes[] = 'IL_R_INVALID_R_LE_0';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_R_INVALID_R_LE_0']);
        }

        $rrEst = ((float)$tp1 - (float)$entry) / $R;
        if ($rrEst < 1.0) {
            $drop = true;
            $reasonCodes[] = 'IL_RR_TOO_LOW';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_RR_TOO_LOW']);
        }

        // Risk flags (avoid)
        if ($upperWickPct >= 0.55 && $closePos < 0.70) {
            $reasonCodes[] = 'IL_WICK_DISTRIBUTION';
            $blockCodes[] = 'IL_WICK_DISTRIBUTION';
        }
        if ($atrPctF >= 0.12) {
            $reasonCodes[] = 'IL_VOL_CHAOS';
            $blockCodes[] = 'IL_VOL_CHAOS';
        }

        // Scoring (0..1)
        $sSetup = $breakoutOk ? 0.85 : 0.75;
        $sVol   = $this->norm01($volRatioF, 1.0, 3.0);
        $sMom   = $this->norm01($roc5, 0.0, 0.08);
        $sPos   = $this->norm01($closePos, 0.60, 0.90);

        $stopPct = ((float)$entry > 0) ? (((float)$entry - (float)$stop) / (float)$entry) : 1.0;
        $sRisk = 1.0 - $this->norm01($stopPct, 0.01, 0.06);

        $scoreTotal = $this->clamp01(
            0.30 * $sSetup +
            0.25 * $sVol +
            0.20 * $sMom +
            0.15 * $sPos +
            0.10 * $sRisk
        );

        $confidence = ($scoreTotal >= 0.75) ? 'High' : (($scoreTotal >= 0.55) ? 'Med' : 'Low');

        $res = $this->policyRes($drop, $scoreTotal * 100.0, $entryStyle, $confidence, $reasonCodes, $blockCodes);
        $res['score_total'] = $scoreTotal;
        $res['setup_type'] = $setupOverride;
        $res['levels'] = $levels;
        $res['derived'] = [
            'dv20_idr' => (int)round($dv20),
            'atr_pct' => round($atrPctF, 4),
            'roc5' => round($roc5, 4),
            'close_pos' => round($closePos, 4),
            'upper_wick_pct' => round($upperWickPct, 4),
            'rr_est' => round($rrEst, 4),
            'stop_pct' => round($stopPct, 4),
        ];
        return $res;
    }

    private function policyRes(bool $drop, float $score, string $entryStyle, string $confidence, array $reasonCodes, array $blockCodes): array
    {
        return [
            'drop' => $drop,
            'score' => max(0.0, $score),
            'entry_style' => $entryStyle,
            'confidence' => $confidence,
            'reason_codes' => array_values(array_unique($reasonCodes)),
            'eligibility_block_codes' => array_values(array_unique($blockCodes)),
            'size_multiplier_adj' => 1.0,
            'shift_entry_windows' => null,
        ];
    }

    private function clamp01(float $v): float
    {
        if ($v < 0.0) return 0.0;
        if ($v > 1.0) return 1.0;
        return $v;
    }

    private function norm01(float $v, float $lo, float $hi): float
    {
        if ($hi <= $lo) return 0.0;
        return $this->clamp01(($v - $lo) / ($hi - $lo));
    }

    private function buildIntradayLightLevels(array $ctx, WatchlistEngine $engine): array
    {
        $close = (float)($ctx['close'] ?? 0);
        $low = (float)($ctx['low'] ?? 0);
        $ll3 = $ctx['ll3'] ?? null;

        if ($close <= 0 || $low <= 0) {
            return [
                'entry_trigger_price' => null,
                'stop_loss_price' => null,
                'tp1_price' => null,
                'tp2_price' => null,
                'tick_size' => null,
            ];
        }

        // Entry = round_up(close)
        $entry = (float)$engine->roundUp($close);

        // Stop = round_down(min(low, ll3) - tick)
        $minLow = $low;
        if ($ll3 !== null && is_numeric($ll3) && (float)$ll3 > 0) {
            $minLow = min($minLow, (float)$ll3);
        }
        $tickMin = (float)$engine->tickSize(max(1.0, $minLow));
        $sl = (float)$engine->roundDown($minLow - $tickMin);

        $tp1 = null;
        $tp2 = null;
        $R = $entry - $sl;
        if ($entry > 0 && $sl > 0 && $R > 0) {
            $tp1 = (float)$engine->roundDown($entry + (1.0 * $R));
            $tp2 = (float)$engine->roundDown($entry + (2.0 * $R));
        }

        $tickEntry = (int)$engine->tickSize(max(1.0, $entry));

        return [
            'entry_trigger_price' => (int)round($entry),
            'stop_loss_price' => (int)round($sl),
            'tp1_price' => $tp1 !== null ? (int)round((float)$tp1) : null,
            'tp2_price' => $tp2 !== null ? (int)round((float)$tp2) : null,
            'tick_size' => $tickEntry > 0 ? $tickEntry : null,
        ];
    }
}
