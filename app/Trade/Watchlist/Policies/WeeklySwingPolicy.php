<?php

namespace App\Trade\Watchlist\Policies;

use App\Trade\Watchlist\WatchlistEngine;

/**
 * WEEKLY_SWING PLAN policy.
 * Spec: docs/watchlist/policy/weekly_swing.md
 */
class WeeklySwingPolicy implements WatchlistPolicyInterface
{
    public function code(): string
    {
        return 'WEEKLY_SWING';
    }


    public function enrichPlanRow(array &$row, array $opts, array $policyMeta, WatchlistEngine $engine): void
    {
        // WeeklySwing: evaluate viability only when capital is provided.
        $capitalTotal = $opts['capital_idr'] ?? ($opts['capital_total'] ?? null); // legacy fallback
        if (!isset($row['plan']) || !is_array($row['plan'])) $row['plan'] = [];
        if (!isset($row['plan']['trade_viability']) || !is_array($row['plan']['trade_viability'])) {
            $row['plan']['trade_viability'] = ['evaluated' => false, 'is_viable' => null, 'reason_codes' => []];
        }

        $row['plan']['trade_viability']['evaluated'] = ($capitalTotal !== null);
        if ($capitalTotal === null) {
            // Capital not provided: viability is not evaluated (contract-safe: do not block grouping).
            $row['plan']['trade_viability']['is_viable'] = null;
            $row['plan']['trade_viability']['reason_codes'] = ['WS_VIABILITY_NOT_EVALUATED'];
            return;
        }

        $levels = is_array($row['levels'] ?? null) ? (array)$row['levels'] : [];
        $sizing = is_array($row['sizing'] ?? null) ? (array)$row['sizing'] : [];

        $lotSize = (int)($sizing['lot_size'] ?? 100);
        $entry = $levels['entry_trigger_price'] ?? null;

        $isViable = true;
        $reasons = [];

        $lotsRec = $sizing['lots_recommended'] ?? null;
        $minLots = (int)($policyMeta['min_lots'] ?? 1);
        if ($lotsRec !== null && (int)$lotsRec < $minLots) {
            $isViable = false;
            $reasons[] = 'WS_MIN_LOTS_FAIL';
        }

        $profitNet = $sizing['profit_tp2_net'] ?? null;
        $edge = ($entry !== null)
            ? $engine->netEdgePct((int)$entry, $lotSize, is_int($profitNet) ? (int)$profitNet : null)
            : null;

        $minEdge = (float)($policyMeta['min_net_edge_pct'] ?? 0.0);
        if ($edge !== null && $edge < $minEdge) {
            $isViable = false;
            $reasons[] = 'WS_MIN_NET_EDGE_FAIL';
        }

        $row['plan']['trade_viability']['is_viable'] = $isViable;
        $row['plan']['trade_viability']['reason_codes'] = array_values(array_unique($reasons));
    }

    public function defaultActionWindows(array $session): array
    {
        // Prefer explicit, deterministic windows (avoid the noisiest first minutes).
        return ['09:20-10:30', '13:35-14:30', '14:30-close'];
    }

    public function apply(array $x, array $reasonCodes, WatchlistEngine $engine): array
    {
        // PLAN-only rules (EOD). CONFIRM rules are handled elsewhere.
        $drop = false;
        $blockCodes = [];
        $entryStyle = 'Default';
        $confidence = 'High';

        $setup = (string)($x['setup_type'] ?? 'Base');
        $atrPct = $x['atr_pct'] ?? null;
        $close = (float)($x['close'] ?? 0);

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

        // Universal sanity
        if ($close <= 0) {
            $drop = true;
            $reasonCodes[] = 'GL_INVALID_PRICE';
            return $this->policyRes($drop, 0.0, 'WATCH_ONLY', 'Low', $reasonCodes, ['GL_INVALID_PRICE']);
        }

        $minDv20   = PolicyDefaults::WS_MIN_DV20_IDR;
        $minAtrPct = PolicyDefaults::WS_MIN_ATR_PCT;
        $maxAtrPct = PolicyDefaults::WS_MAX_ATR_PCT;
        $maxTickPct= PolicyDefaults::WS_MAX_TICK_PCT;
        $minRr     = PolicyDefaults::WS_MIN_RR;
        $tp2RMult  = PolicyDefaults::WS_TP2_R_MULT;

        $ma20 = $x['ma20'] ?? null;
        $ma50 = $x['ma50'] ?? null;
        $dv20 = $x['dv20'] ?? null;
        $hh20 = $x['hh20'] ?? null;
        $ll5  = $x['ll5'] ?? null;
        $roc20 = $x['roc20'] ?? null;
        $signalCode = $x['signal_code'] ?? null;
        $volRatio = $x['vol_ratio'] ?? null;

        // tick/atr derived
        $tickRef = (int) $engine->tickSize(max(1.0, $close));
        $tickPct = ($close > 0) ? ($tickRef / $close) : null;
        $atrPctF = ($atrPct !== null && is_numeric($atrPct)) ? (float)$atrPct : null;

        // resistance_20 = highest_high(20) + tick (tick ladder at hh20 price)
        $resistance20 = null;
        $setupOverride = null;
        if ($hh20 !== null && is_numeric($hh20)) {
            $hh20f = (float)$hh20;
            $tickHh = (int) $engine->tickSize(max(1.0, $hh20f));
            $resistance20 = $hh20f + $tickHh;
            // setup_type: BREAKOUT if close >= resistance_20 - tick => close >= hh20
            $setupOverride = ($close >= ($resistance20 - $tickHh)) ? 'Breakout' : 'Pullback';
            $entryStyle = ($setupOverride === 'Breakout') ? 'Breakout' : 'Pullback';
        } else {
            $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
            $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
        }

        // Trend/structure gate: (close>=ma20 && ma20>=ma50) OR (close>=resistance20 && ma20>=ma50)
        if ($ma20 === null || $ma50 === null || !is_numeric($ma20) || !is_numeric($ma50) || $resistance20 === null) {
            $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
            $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
        } else {
            $ma20f = (float)$ma20;
            $ma50f = (float)$ma50;
            $condA = ($close >= $ma20f) && ($ma20f >= $ma50f);
            $condB = ($close >= $resistance20) && ($ma20f >= $ma50f);
            if (!$condA && !$condB) {
                $reasonCodes[] = 'WS_TREND_GATE_FAIL';
                $blockCodes[] = 'WS_TREND_GATE_FAIL';
            }
        }

        // Liquidity/volatility/tick gates
        if ($dv20 === null || !is_numeric($dv20)) {
            $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
            $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
        } elseif ((float)$dv20 < $minDv20) {
            $reasonCodes[] = 'WS_MIN_DV20_IDR';
            $blockCodes[] = 'WS_MIN_DV20_IDR';
        }

        if ($atrPctF === null) {
            $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
            $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
        } else {
            if ($atrPctF < $minAtrPct) { $reasonCodes[] = 'WS_MIN_ATR_PCT'; $blockCodes[] = 'WS_MIN_ATR_PCT'; }
            if ($atrPctF > $maxAtrPct) { $reasonCodes[] = 'WS_MAX_ATR_PCT'; $blockCodes[] = 'WS_MAX_ATR_PCT'; }
        }

        if ($tickPct === null) {
            $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
            $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
        } elseif ((float)$tickPct > $maxTickPct) {
            $reasonCodes[] = 'WS_MAX_TICK_PCT';
            $blockCodes[] = 'WS_MAX_TICK_PCT';
        }

        // IMPORTANT (docs/tests): policy hard-rule fails MUST NOT silently DROP the ticker from PREOPEN.
        // Instead, surface it in groups.* (usually WATCH_ONLY) with eligibility_block_codes populated,
        // so the user can see *why* it is blocked.
        //
        // DROP is reserved for cases where PLAN cannot be formed at all (e.g., invalid stop/rr).
        // Here, we continue building levels + scoring even if there are block codes.
        $drop = false;

        // Build plan levels (deterministic, EOD-only)
        $levels = $this->buildWeeklySwingLevels(
            (string)($setupOverride ?? $setup),
            [
                'close' => $close,
                'low' => (float)($x['low'] ?? 0),
                'hh20' => $hh20 !== null && is_numeric($hh20) ? (float)$hh20 : null,
                'll5' => $ll5 !== null && is_numeric($ll5) ? (float)$ll5 : null,
            ],
            $minRr,
            $tp2RMult,
            $engine
        );

        // Binding checks: STOP/R invalid OR RR(tp1) too low => DROP
        $entry = $levels['entry_trigger_price'] ?? null;
        $sl = $levels['stop_loss_price'] ?? null;
        $tp1 = $levels['tp1_price'] ?? null;
        $tick = $levels['tick_size'] ?? null;

        if ($entry === null || $sl === null || $tp1 === null || $tick === null) {
            $drop = true;
            $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
            $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
        } else {
            $R = (float)$entry - (float)$sl;
            $tickF = max(1.0, (float)$tick);
            if ($R <= 0.0) {
                $drop = true;
                $reasonCodes[] = 'WS_R_INVALID_NONPOSITIVE';
                $blockCodes[] = 'WS_R_INVALID_NONPOSITIVE';
            } elseif ($R < $tickF) {
                $drop = true;
                $reasonCodes[] = 'WS_R_INVALID_LT_TICK';
                $blockCodes[] = 'WS_R_INVALID_LT_TICK';
            } else {
                $rrEst = ((float)$tp1 - (float)$entry) / $R;
                if ($rrEst + 1e-9 < $minRr) {
                    $drop = true;
                    $reasonCodes[] = 'WS_RR_TOO_LOW';
                    $blockCodes[] = 'WS_RR_TOO_LOW';
                }
            }
        }

        // Score only if eligible and not dropped.
        $scoreTotal = 0.0;
        if (!$drop && empty($blockCodes)) {
            $sPattern = $this->wsPatternScoreFromSignal($signalCode);
            $closeVsMa20 = ($ma20 !== null && is_numeric($ma20) && (float)$ma20 > 0) ? (($close / (float)$ma20) - 1.0) : null;
            $sTrend = $closeVsMa20 !== null ? $this->norm01($closeVsMa20, -0.02, 0.05) : 0.0;
            $sMomentum = ($roc20 !== null && is_numeric($roc20)) ? $this->norm01((float)$roc20, -0.03, 0.12) : 0.0;
            $sVolume = ($volRatio !== null && is_numeric($volRatio)) ? $this->norm01((float)$volRatio, 1.0, 3.0) : 0.0;

            $stopPct = ($entry !== null && (float)$entry > 0 && $sl !== null && (float)$sl < (float)$entry)
                ? (((float)$entry - (float)$sl) / (float)$entry)
                : null;
            $invStop = $stopPct !== null ? (1.0 - $this->norm01($stopPct, 0.01, 0.08)) : 0.0;
            $invAtr = $atrPctF !== null ? (1.0 - $this->norm01($atrPctF, 0.01, 0.12)) : 0.0;
            $sRisk = $this->clamp01(0.5 * $invStop + 0.5 * $invAtr);

            $scoreTotal = $this->clamp01(
                0.30 * $sPattern +
                0.25 * $sTrend +
                0.20 * $sMomentum +
                0.15 * $sVolume +
                0.10 * $sRisk
            );
        }

        $confidence = ($scoreTotal >= 0.75) ? 'High' : (($scoreTotal >= 0.55) ? 'Med' : 'Low');

        $res = $this->policyRes($drop, $scoreTotal * 100.0, $entryStyle, $confidence, $reasonCodes, $blockCodes);
        $res['score_total'] = $scoreTotal;
        if ($setupOverride !== null) $res['setup_type'] = $setupOverride;
        $res['levels'] = $levels;
        $res['derived'] = [
            'dv20_idr' => ($dv20 !== null && is_numeric($dv20)) ? (int)round((float)$dv20) : null,
            'atr_pct' => $atrPctF !== null ? round($atrPctF, 4) : null,
            'tick_pct' => $tickPct !== null ? round((float)$tickPct, 6) : null,
            'roc20' => ($roc20 !== null && is_numeric($roc20)) ? round((float)$roc20, 4) : null,
            'rvol20' => ($volRatio !== null && is_numeric($volRatio)) ? round((float)$volRatio, 4) : null,
        ];
        return $res;
    }

    /**
     * Deterministic policy result schema (same as engine).
     */
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

    private function wsPatternScoreFromSignal($signalCode): float
    {
        $s = is_numeric($signalCode) ? (int)$signalCode : 0;
        $map = [
            10 => 0.0,
            9 => 0.1,
            8 => 0.2,
            7 => 0.8,
            6 => 0.85,
            5 => 1.0,
            4 => 0.9,
            3 => 0.7,
            2 => 0.6,
            1 => 0.4,
            0 => 0.3,
        ];
        return $this->clamp01($map[$s] ?? 0.3);
    }

    /**
     * WEEKLY_SWING deterministic levels (EOD-only).
     */
    private function buildWeeklySwingLevels(string $setupType, array $ctx, float $minRr, float $tp2RMult, WatchlistEngine $engine): array
    {
        $close = (float)($ctx['close'] ?? 0);
        $low = (float)($ctx['low'] ?? 0);
        $hh20 = $ctx['hh20'] ?? null;
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

        // resistance_20 = hh20 + tick(hh20)
        $resistance20 = null;
        if ($hh20 !== null && is_numeric($hh20) && (float)$hh20 > 0) {
            $hh20f = (float)$hh20;
            $tickHh = (float)$engine->tickSize(max(1.0, $hh20f));
            $resistance20 = $hh20f + $tickHh;
        }

        $isBreakout = (strcasecmp($setupType, 'Breakout') === 0);

        // Entry
        if ($isBreakout && $resistance20 !== null) {
            $entry = (float)$engine->roundUp($resistance20);
        } else {
            $entry = (float)$engine->roundUp($close);
        }

        // Stop: round_down(min(low, ll5) - tick)
        $minLow = $low;
        if ($ll5 !== null && is_numeric($ll5) && (float)$ll5 > 0) {
            $minLow = min($minLow, (float)$ll5);
        }
        $tickMin = (float)$engine->tickSize(max(1.0, $minLow));
        $stopRaw = $minLow - $tickMin;
        $sl = (float)$engine->roundDown($stopRaw);

        // Targets
        $tp1 = null;
        $tp2 = null;
        $R = $entry - $sl;
        if ($entry > 0 && $sl > 0 && $R > 0) {
            $tp1 = (float)$engine->roundDown($entry + ($minRr * $R));
            $tp2 = (float)$engine->roundDown($entry + ($tp2RMult * $R));
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
