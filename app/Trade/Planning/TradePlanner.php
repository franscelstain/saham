<?php

namespace App\Trade\Planning;

use App\Trade\Pricing\TickRule;
use App\Trade\Pricing\FeeModel;

class TradePlanner
{
    private TickRule $tick;
    private FeeModel $fee;

    public function __construct(TickRule $tick, FeeModel $fee)
    {
        $this->tick = $tick;
        $this->fee = $fee;
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

        $entryMode = (string) config('trade.planning.entry_mode', 'BREAKOUT');
        $bufferTicks = (int) config('trade.planning.entry_buffer_ticks', 1);

        // ENTRY
        $entry = $close;
        if ($entryMode === 'BREAKOUT' && $resist !== null) {
            // entry di atas resistance + buffer ticks (preopen order)
            $entry = $this->tick->addTicks((float)$resist, $bufferTicks);
        }

        // Round entry UP (biar order buy nggak ketolak)
        $entry = $this->tick->roundUp($entry);

        // SL
        $slMode = (string) config('trade.planning.sl_mode', 'ATR');
        $sl = $this->calcSl($slMode, $entry, $atr, $support, $low);

        // SL harus di-round DOWN (biar stop price valid dan lebih konservatif)
        $sl = $this->tick->roundDown($sl);

        // Risk per share (gross)
        $risk = max(1.0, $entry - $sl);

        // TP targets as R-multiple (gross), then tick round DOWN (sell target -> lebih realistis)
        $tp1R = (float) config('trade.planning.tp1_r_mult', 1.0);
        $tp2R = (float) config('trade.planning.tp2_r_mult', 2.0);

        $tp1 = $this->tick->roundDown($entry + ($risk * $tp1R));
        $tp2 = $this->tick->roundDown($entry + ($risk * $tp2R));

        // BE (fee-aware)
        $be = $this->fee->breakevenExitPrice($entry);
        $be = $this->tick->roundUp($be); // BE target sebaiknya up

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
            // SL sedikit di bawah support (1 tick)
            $base = (float)$support;
            return $this->tick->subTicks($base, 1);
        }

        if ($mode === 'PCT') {
            $pct = (float) config('trade.planning.sl_pct', 0.03);
            return $entry * (1.0 - $pct);
        }

        // default ATR
        $mult = (float) config('trade.planning.sl_atr_mult', 1.5);
        if ($atr !== null) {
            return $entry - ((float)$atr * $mult);
        }

        // fallback kalau ATR belum ada: pakai low hari ini (konservatif)
        return min($low, $entry * 0.97);
    }

    private function calcNetRR(float $entry, float $sl, float $tp): float
    {
        $loss = abs($this->fee->netPnlPerShare($entry, $sl)); // ini sebenarnya negatif, kita abs
        $gain = $this->fee->netPnlPerShare($entry, $tp);

        if ($loss <= 0.0) return 0.0;
        return round($gain / $loss, 2);
    }
}
