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

    /**
     * Pick first numeric value from a set of possible keys.
     * This keeps the policy tolerant to minor naming drift between
     * compute-eod / snapshot / fixtures without embedding domain defaults.
     *
     * @param array<string,mixed> $x
     * @param array<int,string> $keys
     * @return float|null
     */
    private function pickNum(array $x, array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $x) && $x[$k] !== null && is_numeric($x[$k])) {
                return (float)$x[$k];
            }
        }
        return null;
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
        $atrPct = $this->pickNum($x, ['atr_pct','atrPct','atr14_pct','atr_14_pct']);
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

        // tolerate common aliases (older fixtures / upstream stages)
        $ma20  = $this->pickNum($x, ['ma20','ma_20','sma20','sma_20','ema20','ema_20']);
        $ma50  = $this->pickNum($x, ['ma50','ma_50','sma50','sma_50','ema50','ema_50']);
        $dv20  = $this->pickNum($x, ['dv20','dv20_idr','dv_20','dv_20_idr']);
        $hh20  = $this->pickNum($x, ['hh20','hh_20','high_20','highest_high_20']);
        $ll5   = $this->pickNum($x, ['ll5','ll_5','low_5','lowest_low_5']);
        $roc20 = $this->pickNum($x, ['roc20','roc_20','roc20_pct','roc_20_pct']);
        // Accept both snake_case and legacy camelCase keys (older payloads used camelCase).
        // Some call sites may omit signal_code entirely; resolve a safe fallback.
        $signalCode = $this->resolveSignalCode($x);
        $volRatio = $this->pickNum($x, ['vol_ratio','rvol20','rvol_20','rvol20_ratio','volume_ratio']);

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

            // Scoring weights sourced from ENV/config (calibrated via backtest), normalized at runtime.
            $w = is_array($policyMeta['weights'] ?? null) ? (array)$policyMeta['weights'] : [];
            $wTrend = (float)($w['w_trend'] ?? 0.25);
            $wMom  = (float)($w['w_momentum'] ?? 0.20);
            $wVol  = (float)($w['w_volume'] ?? 0.15);
            $wBreak= (float)($w['w_breakout'] ?? 0.30); // breakout/pattern
            $wRisk = (float)($w['w_risk'] ?? 0.10);

            $sumW = max(1e-9, ($wTrend + $wMom + $wVol + $wBreak + $wRisk));
            $wnTrend = $wTrend / $sumW;
            $wnMom   = $wMom / $sumW;
            $wnVol   = $wVol / $sumW;
            $wnBreak = $wBreak / $sumW;
            $wnRisk  = $wRisk / $sumW;

            // Risk is a penalty (higher risk => lower score): subtract normalized risk component.
            $scoreTotal = $this->clamp01(
                ($wnTrend * $sTrend) +
                ($wnMom * $sMomentum) +
                ($wnVol * $sVolume) +
                ($wnBreak * $sPattern) -
                ($wnRisk * (1.0 - $sRisk))
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

    /**
     * Resolve signal_code for WeeklySwing scoring.
     *
     * Prefer explicit classifier fields if present; otherwise infer from breakout + volume context.
     * This keeps the score deterministic even when upstream omits signal fields.
     */
    private function resolveSignalCode(array $x): int
    {
        // Inputs can be flat (preferred) or nested (legacy shapes from fixtures/engine).
        // Do a shallow merge so decision_code/signal_code can be discovered reliably.
        $flat = $x;
        foreach (['signals','signal','ticker_signals','ticker_plan','eod_bar','levels','indicators'] as $k) {
            if (isset($x[$k]) && is_array($x[$k])) {
                // Keep existing top-level keys as source of truth.
                $flat = $flat + $x[$k];
            }
        }

        // Prefer decision_code if present (this is what the scoring contract expects).
        $dc = $flat['decision_code'] ?? ($flat['decisionCode'] ?? null);
        if ($dc !== null && is_numeric($dc)) {
            $dc = (int)$dc;
            // IMPORTANT: 0 means "missing" in our DTO/test fixtures.
            if ($dc > 0) return $dc;
        }

        // Then prefer explicit signal/pattern codes if present.
        $v = $flat['signal_code'] ?? ($flat['signalCode'] ?? null);
        if ($v !== null && is_numeric($v)) {
            $v = (int)$v;
            if ($v > 0) return $v;
        }

        if (isset($flat['pattern_code']) && is_numeric($flat['pattern_code'])) return (int)$flat['pattern_code'];

        // IMPORTANT: s_pattern in this project is a 0..1 score, not a code.
        // Never cast it into an integer code.

        // Derive a stable decision_code from raw fields using the same classifiers
        // used by compute-eod (PatternClassifier + VolumeLabelClassifier + DecisionClassifier).
        $derived = $this->deriveDecisionCode($flat);
        if ($derived !== null) return $derived;

        return 0;
    }

    /**
     * @return int|null
     */
    private function deriveDecisionCode(array $x): ?int
    {
        // Lite fallback for minimal inputs (used by unit score contract test):
        // close + ma20 + roc20 + vol_ratio.
        // This is intentionally simple and used whenever we do NOT have the full
        // breakout-classifier inputs (open/high/low/hh20/ll5).

        $hasCore = (
            isset($x['close'], $x['ma20'], $x['vol_ratio']) &&
            is_numeric($x['close']) && is_numeric($x['ma20']) && is_numeric($x['vol_ratio'])
        );

        $missingFull = (
            !isset($x['open']) || !is_numeric($x['open']) ||
            !isset($x['high']) || !is_numeric($x['high']) ||
            !isset($x['low'])  || !is_numeric($x['low'])  ||
            !isset($x['hh20']) || !is_numeric($x['hh20']) ||
            !isset($x['ll5'])  || !is_numeric($x['ll5'])
        );

        if ($hasCore && $missingFull) {
            $close = (float)$x['close'];
            $ma20  = (float)$x['ma20'];
            $vr    = (float)$x['vol_ratio'];
            $roc20 = (isset($x['roc20']) && is_numeric($x['roc20'])) ? (float)$x['roc20'] : 0.0;

            // strong breakout proxy: close above MA20, positive ROC, volume burst
            if ($close > $ma20 && $roc20 >= 0.05 && $vr >= 1.5) return 5; // buy5
            if ($close > $ma20 && $roc20 > 0.0) return 4; // buy4
            if ($vr >= 2.5) return 4;
            return 3; // caution
        }

        // minimal required fields
        foreach (['open','high','low','close','hh20','ll5','vol_ratio'] as $k) {
            if (!isset($x[$k]) || !is_numeric($x[$k])) return null;
        }

        $open = (float)$x['open'];
        $high = (float)$x['high'];
        $low = (float)$x['low'];
        $close = (float)$x['close'];
        $hh20 = (float)$x['hh20'];
        $ll5 = (float)$x['ll5'];
        $vr = (float)$x['vol_ratio'];

        try {
            $volLbl = (new \App\Trade\Compute\Classifiers\VolumeLabelClassifier())->classify($vr);
            if (!is_int($volLbl)) return null;

            $sig = (new \App\Trade\Compute\Classifiers\PatternClassifier(new \App\Trade\Compute\Config\PatternThresholds()))->classify([
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'hh20' => $hh20,
                'll5' => $ll5,
                'volume_label' => $volLbl,
            ]);
            if (!is_int($sig)) return null;

            $payload = [
                'close' => $close,
                'signal_code' => $sig,
                'volume_label' => $volLbl,
                'vol_ratio' => $vr,
                'resistance_20d' => $hh20,
            ];
            if (isset($x['rsi14']) && is_numeric($x['rsi14'])) $payload['rsi14'] = (float)$x['rsi14'];
            if (isset($x['ma20']) && is_numeric($x['ma20'])) $payload['ma20'] = (float)$x['ma20'];
            if (isset($x['ma50']) && is_numeric($x['ma50'])) $payload['ma50'] = (float)$x['ma50'];
            if (isset($x['ma200']) && is_numeric($x['ma200'])) $payload['ma200'] = (float)$x['ma200'];

            $dc = (new \App\Trade\Compute\Classifiers\DecisionClassifier(new \App\Trade\Compute\Config\DecisionGuardrails()))->classify($payload);
            return is_int($dc) ? $dc : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

}
