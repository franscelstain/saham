<?php

namespace App\Trade\Watchlist\Policies;

use App\Trade\Watchlist\WatchlistEngine;

/**
 * POSITION_TRADE PLAN policy.
 * Spec: docs/watchlist/policy/position_trade.md
 */
class PositionTradePolicy implements WatchlistPolicyInterface
{
    public function code(): string
    {
        return 'POSITION_TRADE';
    }


    public function enrichPlanRow(array &$row, array $opts, array $policyMeta, WatchlistEngine $engine): void
    {
        // No extra PLAN mutation required for POSITION_TRADE at this layer.
    }

    public function defaultActionWindows(array $session): array
    {
        return ['09:20-10:30', '13:35-14:30'];
    }

    /**
     * @param array<string,mixed> $x
     * @param array<int,string> $reasonCodes
     * @return array<string,mixed>
     */
    public function apply(array $x, array $reasonCodes, WatchlistEngine $engine): array
    {
        $drop = false;
        $blockCodes = [];

        $close = (float)($x['close'] ?? 0);
        $open  = (float)($x['open'] ?? 0);
        $low   = (float)($x['low'] ?? 0);

        $requiredOk = function (array $reqKeys) use ($x): bool {
            foreach ($reqKeys as $k) {
                if (!array_key_exists($k, $x) || $x[$k] === null) return false;
            }
            if (($x['close'] ?? 0) <= 0) return false;
            return true;
        };

        // Input minimum (PLAN, EOD-only)
        $req = ['ma50','ma200','atr14','atr_pct','hh50','ll50','ll10','vol_ratio'];
        if (!$requiredOk($req)) {
            $reasonCodes[] = 'PT_DATA_INCOMPLETE';
            return $this->policyRes(false, 0.0, 'WATCH_ONLY', 'Low', $reasonCodes, ['PT_DATA_INCOMPLETE']);
        }

        $ma50     = (float)$x['ma50'];
        $ma200    = (float)$x['ma200'];
        $atr14    = (float)$x['atr14'];
        $atrPct   = (float)$x['atr_pct'];
        $hh50     = (float)$x['hh50'];
        $ll10     = (float)$x['ll10'];
        $volRatio = (float)$x['vol_ratio'];

        $c = $x['candle'] ?? null;
        $closePos = is_array($c) ? (float)($c['close_pos'] ?? 0.0) : 0.0;
        $lowerWickPct = is_array($c) ? (float)($c['lower_wick_pct'] ?? 0.0) : 0.0;

        $tick = (int)($x['tick'] ?? $engine->tickSize(max(1.0, $close)));
        if ($tick <= 0) $tick = 1;

        // Resistance/Support proxies (EOD-only, exclusive lookback assumed upstream)
        $res50 = $hh50 + (float)$tick; // resistance_50 = highest_high(50) + tick
        $support10 = $ll10;            // support_10 = lowest_low(10)

        // 2.1 Trend gate
        $trendOk = ($close > $ma200 && $ma50 > $ma200);
        if (!$trendOk) {
            $reasonCodes[] = 'PT_TREND_NOT_OK';
            $blockCodes[] = 'PT_TREND_NOT_OK';
        }

        // Setup type (LOCKED)
        $setupType = ($close >= ($res50 - (float)$tick)) ? 'BREAKOUT' : 'PULLBACK';

        // A.1 Breakout_50 validity
        $breakoutValid = (
            $close > $hh50
            && $closePos >= PolicyDefaults::PT_BREAKOUT_MIN_CLOSE_POS
            && $volRatio >= PolicyDefaults::PT_BREAKOUT_MIN_VOL_RATIO
        );

        // A.2 Pullback_to_MA50 validity
        $pullbackValid = (
            $trendOk
            && $atr14 > 0
            && abs($close - $ma50) <= (PolicyDefaults::PT_PULLBACK_MAX_MA_DIST_ATR * $atr14)
            && $close > $open
            && $lowerWickPct >= PolicyDefaults::PT_PULLBACK_MIN_LOWER_WICK_RATIO
            && $closePos >= PolicyDefaults::PT_PULLBACK_MIN_CLOSE_POS
        );

        // 2.2 Setup gate
        if (!$breakoutValid && !$pullbackValid) {
            $reasonCodes[] = 'PT_NO_SETUP';
            $blockCodes[] = 'PT_NO_SETUP';
        }

        // 2.3 Volatility feasibility
        if ($atrPct > PolicyDefaults::PT_MAX_ATR_PCT) {
            $reasonCodes[] = 'PT_VOL_TOO_HIGH';
            $blockCodes[] = 'PT_VOL_TOO_HIGH';
        }

        // PLAN Entry/Stop/TP (LOCKED)
        $entry = ($setupType === 'BREAKOUT')
            ? $this->roundToTick($res50, $tick, 'up')
            : $this->roundToTick($close, $tick, 'up');

        $stopBase = min($low, $support10) - (float)$tick;
        $stop = $this->roundToTick($stopBase, $tick, 'down');

        $R = $entry - $stop;
        if ($R <= 0) {
            $drop = true;
            $reasonCodes[] = 'PT_R_INVALID_NONPOSITIVE';
            return $this->policyRes($drop, 0.0, 'WATCH_ONLY', 'Low', $reasonCodes, ['PT_R_INVALID_NONPOSITIVE']);
        }
        if ($R < $tick) {
            $drop = true;
            $reasonCodes[] = 'PT_R_INVALID_LT_TICK';
            return $this->policyRes($drop, 0.0, 'WATCH_ONLY', 'Low', $reasonCodes, ['PT_R_INVALID_LT_TICK']);
        }

        $tp1Raw = $entry + (PolicyDefaults::PT_MIN_RR * $R);
        $tp1 = ($setupType === 'PULLBACK')
            ? $this->roundToTick(min($tp1Raw, $res50), $tick, 'down')
            : $this->roundToTick($tp1Raw, $tick, 'down');

        if ($tp1 <= $entry) {
            $drop = true;
            $reasonCodes[] = 'PT_TP1_NOT_ABOVE_ENTRY';
            return $this->policyRes($drop, 0.0, 'WATCH_ONLY', 'Low', $reasonCodes, ['PT_TP1_NOT_ABOVE_ENTRY']);
        }

        $rrEst = ($tp1 - $entry) / (float)$R;
        if ($rrEst < PolicyDefaults::PT_MIN_RR) {
            $reasonCodes[] = 'PT_RR_TOO_LOW';
            $blockCodes[] = 'PT_RR_TOO_LOW';
        }

        $stopPct = ($entry > 0) ? ((float)$R / (float)$entry) : 1.0;
        // 2.4 Stop feasibility
        if ($stopPct > PolicyDefaults::PT_MAX_STOP_PCT) {
            $reasonCodes[] = 'PT_STOP_TOO_WIDE';
            $blockCodes[] = 'PT_STOP_TOO_WIDE';
        }

        $tp2 = $this->roundToTick($entry + (PolicyDefaults::PT_TP2_R_MULT * $R), $tick, 'down');

        // Risk rules (Avoid)
        $rsi = $x['rsi14'] ?? null;
        if ($rsi !== null && is_numeric($rsi)) {
            $rsiF = (float)$rsi;
            if (
                $rsiF >= PolicyDefaults::PT_BLOWOFF_RSI
                && $volRatio >= PolicyDefaults::PT_BLOWOFF_VOL_RATIO
                && $closePos >= PolicyDefaults::PT_BLOWOFF_MIN_CLOSE_POS
            ) {
                $reasonCodes[] = 'PT_BLOWOFF_RISK';
                $blockCodes[] = 'PT_BLOWOFF_RISK';
            }
        }

        $nearResPct = ($res50 > 0 && $close > 0) ? (($res50 - $close) / $close) : null;
        if ($nearResPct !== null && $nearResPct <= PolicyDefaults::PT_NEAR_RESIST_PCT) {
            $reasonCodes[] = 'PT_NEAR_RESISTANCE';
            $blockCodes[] = 'PT_NEAR_RESISTANCE';
        }

        if ($atrPct > PolicyDefaults::PT_MAX_ATR_PCT_SHOCK) {
            $reasonCodes[] = 'PT_ATR_SHOCK';
            $blockCodes[] = 'PT_ATR_SHOCK';
        }

        // Scoring (LOCKED weights; 0..1)
        $scoreTotal = 0.0;
        if (empty($blockCodes)) {
            $trendRatio = ($ma200 > 0) ? ($ma50 / $ma200) : 0.0;
            $sTrend = $this->clamp01(($trendRatio - 0.95) / (1.10 - 0.95));

            $dist = ($close > 0) ? (($res50 - $close) / $close) : 1.0;
            // smaller dist is better (closer to resistance); invert normalized mapping
            $sStructure = 1.0 - $this->norm01Clamped($dist, -0.02, 0.03);

            $sPattern = $breakoutValid ? 1.0 : ($pullbackValid ? 0.80 : 0.0);
            $sVolume = $this->norm01Clamped($volRatio, 1.0, 3.0);

            $invStop = 1.0 - $this->norm01Clamped($stopPct, 0.01, 0.12);
            $invAtr  = 1.0 - $this->norm01Clamped($atrPct, 0.01, 0.12);
            $sRisk = $this->clamp01(0.5 * $invStop + 0.5 * $invAtr);

            $scoreTotal = $this->clamp01(
                0.30 * $sTrend
                + 0.25 * $sStructure
                + 0.15 * $sPattern
                + 0.15 * $sVolume
                + 0.15 * $sRisk
            );

            // Soft labels
            if ($trendRatio >= 1.05) $reasonCodes[] = 'PT_TREND_STRONG';
            if ($breakoutValid && $closePos >= 0.80) $reasonCodes[] = 'PT_CLOSE_STRONG';
            if ($rsi !== null && is_numeric($rsi) && (float)$rsi >= 78.0) $reasonCodes[] = 'PT_OVERHEAT';
        }

        $confidence = ($scoreTotal >= 0.75) ? 'High' : (($scoreTotal >= 0.55) ? 'Med' : 'Low');
        $setupLabel = ($setupType === 'BREAKOUT') ? 'Breakout' : 'Pullback';
        $entryStyle = ($setupType === 'BREAKOUT') ? 'BREAKOUT' : 'PULLBACK';

        $res = $this->policyRes($drop, $scoreTotal * 100.0, $entryStyle, $confidence, $reasonCodes, $blockCodes);
        $res['score_total'] = $scoreTotal;
        $res['setup_type'] = $setupLabel;
        $res['levels'] = [
            'tick_size' => $tick,
            'entry_trigger_price' => $entry,
            'stop_loss_price' => $stop,
            'tp1_price' => $tp1,
            'tp2_price' => $tp2,
            'close_price' => (int)$this->roundToTick($close, $tick, 'nearest'),
        ];
        $res['derived'] = [
            'resistance_50' => (int)round($res50),
            'support_10' => (int)round($support10),
            'atr_pct' => round($atrPct, 4),
            'rr_est' => round($rrEst, 4),
            'stop_pct' => round($stopPct, 4),
            'trend_ratio' => ($ma200 > 0) ? round($ma50 / $ma200, 4) : null,
            'vol_ratio' => round($volRatio, 4),
            'close_pos' => round($closePos, 4),
        ];

        return $res;
    }

    /**
     * @param array<int,string> $reasonCodes
     * @param array<int,string> $blockCodes
     * @return array<string,mixed>
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

    private function clamp01(float $x): float
    {
        if ($x < 0.0) return 0.0;
        if ($x > 1.0) return 1.0;
        return $x;
    }

    private function norm01Clamped(float $x, float $lo, float $hi): float
    {
        if ($hi <= $lo) return 0.0;
        $t = ($x - $lo) / ($hi - $lo);
        return $this->clamp01($t);
    }

    private function roundToTick(float $price, int $tick, string $dir): int
    {
        if ($tick <= 0) $tick = 1;
        $x = $price / $tick;
        if ($dir === 'down') return (int)(floor($x) * $tick);
        if ($dir === 'up') return (int)(ceil($x) * $tick);
        if ($dir === 'nearest') return (int)(round($x) * $tick);
        return (int)(round($x) * $tick);
    }
}
