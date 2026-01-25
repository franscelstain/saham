<?php

namespace App\Trade\Planning;

final class PlanningPolicyConfig
{
    public string $entryMode;
    public int $entryBufferTicks;
    public string $slMode;
    public float $slPct;
    public float $slAtrMult;
    public float $tp1R;
    public float $tp2R;
    public float $minRRTp2;
    public float $beAtR;

    public function __construct(
        string $entryMode,
        int $entryBufferTicks,
        string $slMode,
        float $slPct,
        float $slAtrMult,
        float $tp1R,
        float $tp2R,
        float $minRRTp2,
        float $beAtR
    ) {
        $this->entryMode = strtoupper(trim($entryMode)) ?: 'BREAKOUT';
        $this->entryBufferTicks = max(0, $entryBufferTicks);
        $this->slMode = strtoupper(trim($slMode)) ?: 'ATR';
        $this->slPct = max(0.0, $slPct);
        $this->slAtrMult = max(0.0, $slAtrMult);
        $this->tp1R = max(0.0, $tp1R);
        $this->tp2R = max(0.0, $tp2R);
        $this->minRRTp2 = max(0.0, $minRRTp2);
        $this->beAtR = max(0.0, $beAtR);
    }
}
