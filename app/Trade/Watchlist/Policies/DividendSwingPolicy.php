<?php

namespace App\Trade\Watchlist\Policies;

use App\Trade\Watchlist\WatchlistEngine;

/**
 * DIVIDEND_SWING PLAN policy.
 * Locked by docs/watchlist/policy/dividend_swing.md
 */
class DividendSwingPolicy implements WatchlistPolicyInterface
{
    public function code(): string
    {
        return 'DIVIDEND_SWING';
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

        $close = (float)($x['close'] ?? 0);
        $open  = (float)($x['open'] ?? 0);
        $high  = (float)($x['high'] ?? 0);
        $low   = (float)($x['low'] ?? 0);
        $atr14 = (float)($x['atr14'] ?? 0);
        $dv20  = $x['dv20'] ?? null;
        $volRatio = $x['vol_ratio'] ?? null;
        $rsi = $x['rsi14'] ?? null;

        // --- Event gate ---
        $div = $x['div_event'] ?? null;
        $exDate = is_array($div) ? (string)($div['ex_date'] ?? '') : '';
        $execDate = (string)($x['exec_trade_date'] ?? '');

        if ($exDate === '' || $execDate === '') {
            $drop = true;
            $reasonCodes[] = 'DS_EVENT_MISSING';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_EVENT_MISSING']);
        }

        $daysToEx = $engine->tradingDaysBetween($execDate, $exDate);

        $minDays = 2;
        $maxDays = 12;
        if ($daysToEx < $minDays || $daysToEx > $maxDays) {
            $drop = true;
            $reasonCodes[] = 'DS_OUTSIDE_EVENT_WINDOW';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_OUTSIDE_EVENT_WINDOW']);
        }

        // --- Required inputs ---
        if (!$requiredOk(['ma20','ma50','atr14','hh20','hh50','ll5'])) {
            $drop = true;
            $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['GL_POLICY_INPUT_MISSING']);
        }

        $ma20 = (float)$x['ma20'];
        $ma50 = (float)$x['ma50'];
        $hh20 = (float)$x['hh20'];
        $hh50 = (float)$x['hh50'];
        $ll5  = (float)$x['ll5'];

        // --- Trend sanity ---
        $trendOk = ($close >= $ma20) || ($ma20 >= $ma50);
        if (!$trendOk) {
            $drop = true;
            $reasonCodes[] = 'DS_TREND_WEAK';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_TREND_WEAK']);
        }

        // Not-extended gate
        $maxExtendAtr = PolicyDefaults::DS_MAX_EXTEND_ATR;
        if ($atr14 > 0 && (($close - $ma20) > ($maxExtendAtr * $atr14))) {
            $drop = true;
            $reasonCodes[] = 'DS_PRICE_EXTENDED';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_PRICE_EXTENDED']);
        }

        // --- Setup checks (need at least one) ---
        $range = max(0.0, $high - $low);
        $closePos = ($range > 0) ? (($close - $low) / $range) : 0.0;
        $c = is_array($x['candle'] ?? null) ? (array)$x['candle'] : [];
        $lowerWick = (float)($c['lower_wick_pct'] ?? 0.0);

        $breakoutMinVol = PolicyDefaults::DS_BREAKOUT_MIN_VOL_RATIO;
        $breakoutOk = ($close > $hh20)
            && ($closePos >= PolicyDefaults::DS_BREAKOUT_MIN_CLOSE_POS)
            && ($volRatio !== null && is_numeric($volRatio) && (float)$volRatio >= $breakoutMinVol);

        $pbMaxMaDistAtr = PolicyDefaults::DS_PULLBACK_MAX_MA_DIST_ATR;
        $pbMinLowerWick = PolicyDefaults::DS_PULLBACK_MIN_LOWER_WICK;
        $pbMinClosePos  = PolicyDefaults::DS_PULLBACK_MIN_CLOSE_POS;
        $pullbackOk = $trendOk
            && ($atr14 > 0)
            && (abs($close - $ma20) <= ($pbMaxMaDistAtr * $atr14))
            && ($close > $open)
            && ($lowerWick >= $pbMinLowerWick)
            && ($closePos >= $pbMinClosePos);

        if (!$pullbackOk && !$breakoutOk) {
            $drop = true;
            $reasonCodes[] = 'DS_NO_SETUP';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_NO_SETUP']);
        }

        // --- Plan levels (locked formulas) ---
        $tickHh = (float)$engine->tickSize(max(1.0, $hh20));
        $res20 = $hh20 + $tickHh;
        $setupOverride = ($close >= $res20) ? 'Breakout' : 'Pullback';
        $entryStyle = $setupOverride;

        $minRr = PolicyDefaults::DS_MIN_RR;
        $maxStopPct = PolicyDefaults::DS_MAX_STOP_PCT;
        $levels = $this->buildDividendSwingLevels($setupOverride, [
            'close' => $close,
            'low' => $low,
            'hh20' => $hh20,
            'hh50' => $hh50,
            'll5' => $ll5,
        ], $minRr, $engine);

        $entry = (int)($levels['entry_trigger_price'] ?? 0);
        $sl = (int)($levels['stop_loss_price'] ?? 0);
        $tp1 = (int)($levels['tp1_price'] ?? 0);
        $tick = (int)($levels['tick_size'] ?? 1);

        if ($entry <= 0 || $sl <= 0 || $tp1 <= 0 || $tick <= 0) {
            $drop = true;
            $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['GL_POLICY_INPUT_MISSING']);
        }

        $R = $entry - $sl;
        if ($R <= 0) {
            $drop = true;
            $reasonCodes[] = 'DS_R_INVALID_NONPOSITIVE';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_R_INVALID_NONPOSITIVE']);
        }
        if ($R < $tick) {
            $drop = true;
            $reasonCodes[] = 'DS_R_INVALID_LT_TICK';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_R_INVALID_LT_TICK']);
        }
        if ($tp1 <= $entry) {
            $drop = true;
            $reasonCodes[] = 'DS_TP1_NOT_ABOVE_ENTRY';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_TP1_NOT_ABOVE_ENTRY']);
        }

        $rrEst = ($tp1 - $entry) / (float)$R;
        if ($rrEst < $minRr) {
            $drop = true;
            $reasonCodes[] = 'DS_RR_TOO_LOW';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_RR_TOO_LOW']);
        }

        $stopDistPct = $entry > 0 ? ((float)$R / (float)$entry) : 1.0;
        if ($stopDistPct > $maxStopPct) {
            $drop = true;
            $reasonCodes[] = 'DS_STOP_TOO_WIDE';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_STOP_TOO_WIDE']);
        }

        // --- Risk flags (avoid) ---
        $atrPctF = ($atr14 > 0 && $close > 0) ? ($atr14 / $close) : null;
        if ($atrPctF !== null && $atrPctF >= 0.12) {
            $reasonCodes[] = 'DS_VOL_CHAOS';
            $blockCodes[] = 'DS_VOL_CHAOS';
        }

        if ($daysToEx <= 2
            && $volRatio !== null && is_numeric($volRatio) && (float)$volRatio >= 1.8
            && $rsi !== null && is_numeric($rsi) && (float)$rsi >= 75.0) {
            $reasonCodes[] = 'DS_EVENT_RUNUP_RISK';
            $blockCodes[] = 'DS_EVENT_RUNUP_RISK';
        }

        $nearResPct = ($res20 > 0 && $entry > 0) ? (($res20 - $entry) / $entry) : null;
        if ($nearResPct !== null && $nearResPct <= 0.02) {
            $reasonCodes[] = 'DS_NEAR_RESISTANCE';
            $blockCodes[] = 'DS_NEAR_RESISTANCE';
        }

        // --- Scoring (0..1) ---
        $ideal = (int)floor(($minDays + $maxDays) / 2);
        if ($daysToEx <= $ideal) {
            $sEvent = 1.0 - (abs($daysToEx - $ideal) / max(1.0, (float)($ideal - $minDays)));
        } else {
            $sEvent = 1.0 - (abs($daysToEx - $ideal) / max(1.0, (float)($maxDays - $ideal)));
        }
        $sEvent = $this->clamp01((float)$sEvent);

        $sLiq = 0.5;
        if ($dv20 !== null && is_numeric($dv20)) {
            $sLiq = $this->norm01((float)$dv20, 1e9, 2e10);
        }

        $closeVsMa20 = ($ma20 > 0) ? (($close / $ma20) - 1.0) : 0.0;
        $sTrend = $this->norm01((float)$closeVsMa20, -0.03, 0.04);

        $sVolRisk = 0.5;
        if ($atrPctF !== null) {
            $sVolRisk = 1.0 - $this->norm01((float)$atrPctF, 0.01, 0.10);
        }

        $gapPct = null;
        $prevClose = $x['prev_close'] ?? null;
        if ($prevClose !== null && is_numeric($prevClose) && (float)$prevClose > 0 && $open > 0) {
            $gapPct = (($open - (float)$prevClose) / (float)$prevClose);
        }

        $yieldEst = null;
        if (is_array($div)) {
            $cash = $div['cash_amount'] ?? null;
            if ($cash !== null && is_numeric($cash) && $close > 0) {
                $yieldEst = ((float)$cash) / $close;
            }
        }

        $scoreTotal = $this->clamp01(
            0.35 * $sEvent +
            0.20 * $sLiq +
            0.25 * $sTrend +
            0.20 * $sVolRisk
        );

        $confidence = ($scoreTotal >= 0.75) ? 'High' : (($scoreTotal >= 0.55) ? 'Med' : 'Low');

        $res = $this->policyRes($drop, $scoreTotal * 100.0, $entryStyle, $confidence, $reasonCodes, $blockCodes);
        $res['score_total'] = $scoreTotal;
        $res['setup_type'] = $setupOverride;
        $res['levels'] = $levels;
        $res['derived'] = [
            'days_to_ex' => $daysToEx,
            'ex_date' => $exDate,
            'dv20_idr' => ($dv20 !== null && is_numeric($dv20)) ? (int)round((float)$dv20) : null,
            'atr_pct' => $atrPctF !== null ? round((float)$atrPctF, 4) : null,
            'gap_pct_open' => $gapPct !== null ? round((float)$gapPct, 4) : null,
            'yield_est' => $yieldEst !== null ? round((float)$yieldEst, 4) : null,
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

    private function buildDividendSwingLevels(string $setupType, array $ctx, float $minRr, WatchlistEngine $engine): array
    {
        $close = (float)($ctx['close'] ?? 0);
        $low = (float)($ctx['low'] ?? 0);
        $hh20 = $ctx['hh20'] ?? null;
        $hh50 = $ctx['hh50'] ?? null;
        $ll5 = $ctx['ll5'] ?? null;

        if ($close <= 0 || $low <= 0) {
            return [
                'entry_trigger_price' => null,
                'stop_loss_price' => null,
                'tp1_price' => null,
                'tp2_price' => null,
                'tick_size' => null,
            ];
        }

        $res20 = null;
        if ($hh20 !== null && is_numeric($hh20) && (float)$hh20 > 0) {
            $hh20f = (float)$hh20;
            $tickHh = (float)$engine->tickSize(max(1.0, $hh20f));
            $res20 = $hh20f + $tickHh;
        }

        $res50 = null;
        if ($hh50 !== null && is_numeric($hh50) && (float)$hh50 > 0) {
            $hh50f = (float)$hh50;
            $tickHh = (float)$engine->tickSize(max(1.0, $hh50f));
            $res50 = $hh50f + $tickHh;
        }

        $isBreakout = (strcasecmp($setupType, 'Breakout') === 0);

        if ($isBreakout && $res20 !== null) {
            $entry = (float)$engine->roundUp($res20);
        } else {
            $entry = (float)$engine->roundUp($close);
        }

        $minLow = $low;
        if ($ll5 !== null && is_numeric($ll5) && (float)$ll5 > 0) {
            $minLow = min($minLow, (float)$ll5);
        }
        $tickMin = (float)$engine->tickSize(max(1.0, $minLow));
        $sl = (float)$engine->roundDown($minLow - $tickMin);

        $tp1 = null;
        $tp2 = null;
        $R = $entry - $sl;
        if ($entry > 0 && $sl > 0 && $R > 0) {
            $tp1Raw = $entry + ($minRr * $R);
            $cap = $isBreakout ? $res50 : $res20;
            if ($cap !== null && $cap > 0) {
                $tp1Raw = min($tp1Raw, (float)$cap);
            }
            $tp1 = (float)$engine->roundDown($tp1Raw);
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
