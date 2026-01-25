<?php

namespace App\Trade\Planning;

/**
 * PlanningPolicy
 *
 * Holder parameter planning agar TradePlanner/PlanValidator tidak baca config() langsung.
 */
final class PlanningPolicy
{
    private string $entryMode;
    private int $entryBufferTicks;
    private string $slMode;
    private float $slPct;
    private float $slAtrMult;
    private float $tp1R;
    private float $tp2R;
    private float $minRrTp2;
    private float $beAtR;

    public function __construct(
        string $entryMode,
        int $entryBufferTicks,
        string $slMode,
        float $slPct,
        float $slAtrMult,
        float $tp1R,
        float $tp2R,
        float $minRrTp2,
        float $beAtR
    ) {
        $this->entryMode = strtoupper(trim($entryMode)) ?: 'BREAKOUT';
        $this->entryBufferTicks = max(0, $entryBufferTicks);
        $this->slMode = strtoupper(trim($slMode)) ?: 'ATR';
        $this->slPct = max(0.0, $slPct);
        $this->slAtrMult = max(0.0, $slAtrMult);
        $this->tp1R = max(0.0, $tp1R);
        $this->tp2R = max(0.0, $tp2R);
        $this->minRrTp2 = max(0.0, $minRrTp2);
        $this->beAtR = max(0.0, $beAtR);
    }

    public function entryMode(): string { return $this->entryMode; }
    public function entryBufferTicks(): int { return $this->entryBufferTicks; }
    public function slMode(): string { return $this->slMode; }
    public function slPct(): float { return $this->slPct; }
    public function slAtrMult(): float { return $this->slAtrMult; }
    public function tp1R(): float { return $this->tp1R; }
    public function tp2R(): float { return $this->tp2R; }
    public function minRrTp2(): float { return $this->minRrTp2; }
    public function beAtR(): float { return $this->beAtR; }
}
