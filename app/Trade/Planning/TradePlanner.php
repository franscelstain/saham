<?php

namespace App\Trade\Planning;

use App\Trade\Pricing\TickRule;
use App\Trade\Pricing\FeeModel;

class TradePlanner
{
    private TickRule $tick;
    private FeeModel $fee;
    private PlanningPolicy $policy;

    public function __construct(TickRule $tick, FeeModel $fee, PlanningPolicy $policy)
    {
        $this->tick = $tick;
        $this->fee = $fee;
        $this->policy = $policy;
    }

    /**
     * Build plan from EOD metrics (no intraday timing).
     *
     * Expected $m keys:
     * close, high, low, atr14, support_20d, resistance_20d
     */
    public function make(array $m): TradePlan
    {
        $close = (float) ($m['close'] ?? 0);
        $high  = (float) ($m['high'] ?? 0);
        $low   = (float) ($m['low'] ?? 0);
        $atr   = $m['atr14'] ?? null;
        $support = $m['support_20d'] ?? null;
        $resist  = $m['resistance_20d'] ?? null;

        $entryMode = $this->policy->entryMode();
        $bufferTicks = $this->policy->entryBufferTicks();

        // ENTRY
        $entry = $close;
        if ($entryMode === 'BREAKOUT' && $resist !== null) {
            $entry = $this->tick->addTicks((float)$resist, $bufferTicks);
        }

        // Round entry UP (biar order buy nggak ketolak)
        $entry = $this->tick->roundUp($entry);

        // SL
        $slMode = $this->policy->slMode();
        $sl = $this->calcSl($slMode, $entry, $atr, $support, $low);
        $sl = $this->tick->roundDown($sl);

        // Risk per share (gross)
        $risk = max(1.0, $entry - $sl);

        // TP targets as R-multiple (gross)
        $tp1R = $this->policy->tp1R();
        $tp2R = $this->policy->tp2R();

        $tp1 = $this->tick->roundDown($entry + ($risk * $tp1R));
        $tp2 = $this->tick->roundDown($entry + ($risk * $tp2R));

        // BE (fee-aware)
        $be = $this->fee->breakevenExitPrice($entry);
        $be = $this->tick->roundUp($be);

        // RR TP2 (fee-aware)
        $rrTp2 = $this->calcNetRR($entry, $sl, $tp2);

        $plan = new TradePlan();
        $plan->entry = $entry;
        $plan->sl = $sl;
        $plan->tp1 = $tp1;
        $plan->tp2 = $tp2;
        $plan->be = $be;
        $plan->rrTp2 = $rrTp2;

        $plan->meta = [
            'entryMode' => $entryMode,
            'slMode' => $slMode,
            'riskPerShareGross' => $risk,
            'atr14' => $atr,
            'support20' => $support,
            'resistance20' => $resist,
        ];

        return $plan;
    }

    private function calcSl(string $mode, float $entry, $atr, $support, float $low): float
    {
        if ($mode === 'SUPPORT' && $support !== null) {
            $base = (float)$support;
            return $this->tick->subTicks($base, 1);
        }

        if ($mode === 'PCT') {
            $pct = $this->policy->slPct();
            return $entry * (1.0 - $pct);
        }

        // default ATR
        $mult = $this->policy->slAtrMult();
        if ($atr !== null) {
            return $entry - ((float)$atr * $mult);
        }

        return min($low, $entry * 0.97);
    }

    private function calcNetRR(float $entry, float $sl, float $tp): float
    {
        $loss = abs($this->fee->netPnlPerShare($entry, $sl));
        $gain = $this->fee->netPnlPerShare($entry, $tp);

        if ($loss <= 0.0) return 0.0;
        return round($gain / $loss, 2);
    }
}
