<?php

namespace App\Trade\Pricing;

class TickRule
{
    private array $ladder;

    public function __construct(?array $ladder = null)
    {
        $this->ladder = $ladder ?: (array) config('trade.pricing.idx_ticks', []);
    }

    public function tickSize(float $price): int
    {
        foreach ($this->ladder as $row) {
            $lt = $row['lt'] ?? null;
            $tick = (int) ($row['tick'] ?? 1);

            if ($lt === null) return $tick;
            if ($price < (float)$lt) return $tick;
        }

        return 1;
    }

    public function roundDown(float $price): float
    {
        $tick = $this->tickSize($price);
        return floor($price / $tick) * $tick;
    }

    public function roundUp(float $price): float
    {
        $tick = $this->tickSize($price);
        return ceil($price / $tick) * $tick;
    }

    public function roundNearest(float $price): float
    {
        $tick = $this->tickSize($price);
        return round($price / $tick) * $tick;
    }

    public function addTicks(float $price, int $ticks): float
    {
        $tick = $this->tickSize($price);
        return $price + ($tick * $ticks);
    }

    public function subTicks(float $price, int $ticks): float
    {
        $tick = $this->tickSize($price);
        return $price - ($tick * $ticks);
    }
}
