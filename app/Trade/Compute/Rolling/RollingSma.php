<?php

namespace App\Trade\Compute\Rolling;

class RollingSma
{
    private int $n;
    private float $sum = 0.0;
    private array $q = [];

    public function __construct(int $n) { $this->n = $n; }

    public function push(float $v): void
    {
        $this->q[] = $v;
        $this->sum += $v;

        if (count($this->q) > $this->n) {
            $out = array_shift($this->q);
            $this->sum -= $out;
        }
    }

    public function value(): ?float
    {
        return count($this->q) >= $this->n ? ($this->sum / $this->n) : null;
    }

    public function count(): int { return count($this->q); }
}
