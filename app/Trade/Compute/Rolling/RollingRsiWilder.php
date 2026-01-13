<?php

namespace App\Trade\Compute\Rolling;

class RollingRsiWilder
{
    private int $n;
    private ?float $prevClose = null;

    private int $seedCount = 0;
    private float $seedGain = 0.0;
    private float $seedLoss = 0.0;

    private ?float $avgGain = null;
    private ?float $avgLoss = null;

    public function __construct(int $n) { $this->n = $n; }

    public function push(float $close): void
    {
        if ($this->prevClose === null) {
            $this->prevClose = $close;
            return;
        }

        $chg = $close - $this->prevClose;
        $gain = $chg > 0 ? $chg : 0.0;
        $loss = $chg < 0 ? abs($chg) : 0.0;

        if ($this->avgGain === null || $this->avgLoss === null) {
            $this->seedGain += $gain;
            $this->seedLoss += $loss;
            $this->seedCount++;

            if ($this->seedCount >= $this->n) {
                $this->avgGain = $this->seedGain / $this->n;
                $this->avgLoss = $this->seedLoss / $this->n;
            }
        } else {
            $this->avgGain = (($this->avgGain * ($this->n - 1)) + $gain) / $this->n;
            $this->avgLoss = (($this->avgLoss * ($this->n - 1)) + $loss) / $this->n;
        }

        $this->prevClose = $close;
    }

    public function value(): ?float
    {
        if ($this->avgGain === null || $this->avgLoss === null) return null;
        if ($this->avgLoss == 0.0) return 100.0;
        $rs = $this->avgGain / $this->avgLoss;
        return 100.0 - (100.0 / (1.0 + $rs));
    }
}
