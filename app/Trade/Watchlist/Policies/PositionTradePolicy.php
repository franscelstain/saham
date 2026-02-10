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

    public function apply(array $x, array $reasonCodes, WatchlistEngine $engine): array
    {
        $drop = false;
        $blockCodes = [];
        $entryStyle = (string)($x['entry_style'] ?? 'Default');
        $confidence = (string)($x['confidence'] ?? 'High');
        $score = (float)($x['score'] ?? 0.0);

        // Liquidity bucket is derived upstream (universe filter) as liq_bucket: A|B|C|...
        $liq = strtoupper((string)($x['liq_bucket'] ?? ''));
        if (!in_array($liq, ['A','B'], true)) {
            $drop = true;
            $reasonCodes[] = 'PT_LIQ_TOO_LOW';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['PT_LIQ_TOO_LOW']);
        }

        $requiredOk = function (array $reqKeys) use ($x): bool {
            foreach ($reqKeys as $k) {
                if (!array_key_exists($k, $x) || $x[$k] === null) return false;
            }
            if (($x['close'] ?? 0) <= 0) return false;
            return true;
        };

        // Trend gate (PT uses ma200)
        if (!$requiredOk(['ma200','ma50'])) {
            $drop = true;
            $reasonCodes[] = 'PT_MISSING_TREND_INPUT';
            return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['PT_MISSING_TREND_INPUT']);
        }

        $close = (float)$x['close'];
        $ma200 = (float)$x['ma200'];
        $ma50  = (float)$x['ma50'];
        $setup = (string)($x['setup_type'] ?? '');

        $trendOk = ($close > $ma200 && $ma50 > $ma200);
        if (!$trendOk && $setup === 'Breakout') {
            $reasonCodes[] = 'PT_BREAKOUT_BLOCK_TREND_NOT_OK';
            $blockCodes[] = 'PT_BREAKOUT_BLOCK_TREND_NOT_OK';
        }

        $rsi = $x['rsi14'] ?? null;
        if ($rsi !== null && is_numeric($rsi) && (float)$rsi >= 78.0) {
            $score -= 6.0;
            $reasonCodes[] = 'PT_RSI_OVERHEAT';
        }

        $c = $x['candle'] ?? null;
        if (is_array($c)) {
            $upper = (float)($c['upper_wick_pct'] ?? 0);
            $closeNearHigh = (bool)($c['close_near_high'] ?? false);
            if ($upper >= 0.55 && !$closeNearHigh) {
                $score -= 6.0;
                $reasonCodes[] = 'PT_WICK_DISTRIBUTION';
            }
        }

        if ($setup === 'Reversal') {
            $reasonCodes[] = 'PT_REVERSAL_DISABLED_DEFAULT';
            $blockCodes[] = 'PT_REVERSAL_DISABLED_DEFAULT';
        } elseif (!in_array($setup, ['Breakout','Pullback','Continuation'], true)) {
            $blockCodes[] = 'PT_SETUP_NOT_ALLOWED';
        }

        $reasonCodes[] = 'PT_CONFIRM_OPTIONAL';
        return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes);
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
}
