<?php

namespace App\Trade\Planning;

class TradePlan
{
    public float $entry;
    public float $sl;
    public float $tp1;
    public float $tp2;
    public float $be; // break-even exit price (fee-aware), rounded to tick later
    public float $rrTp2; // RR at TP2 (fee-aware)

    public array $meta = [];

    public function toArray(): array
    {
        return [
            'entry' => $this->entry,
            'sl' => $this->sl,
            'tp1' => $this->tp1,
            'tp2' => $this->tp2,
            'be' => $this->be,
            'rrTp2' => $this->rrTp2,
            'meta' => $this->meta,
        ];
    }
}
