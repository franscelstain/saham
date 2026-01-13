<?php

namespace App\Trade\Compute\Rolling;

class RollingAtrWilder
{
    private int $n;
    private ?float $prevClose = null;

    private int $seedCount = 0;
    private float $seedTr = 0.0;
    private ?float $atr = null;

    public function __construct(int $n) { $this->n = $n; }

    public function push(float $high, float $low, float $close): void
    {
        $tr = $high - $low;

        if ($this->prevClose !== null) {
            $tr = max(
                $tr,
                abs($high - $this->prevClose),
                abs($low - $this->prevClose)
            );
        }

        if ($this->atr === null) {
            $this->seedTr += $tr;
            $this->seedCount++;
            if ($this->seedCount >= $this->n) {
                $this->atr = $this->seedTr / $this->n;
            }
        } else {
            $this->atr = (($this->atr * ($this->n - 1)) + $tr) / $this->n;
        }

        $this->prevClose = $close;
    }

    public function value(): ?float
    {
        return $this->atr;
    }
}
