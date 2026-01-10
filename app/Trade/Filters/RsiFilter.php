<?php

namespace App\Trade\Filters;

use App\DTO\Trade\RuleResult;
use App\DTO\Watchlist\CandidateInput;

class RsiFilter
{
    private float $max;

    public function __construct(float $max = 70.0)
    {
        $this->max = $max;
    }

    public function check(CandidateInput $c): RuleResult
    {
        $pass = ($c->rsi <= $this->max);

        return new RuleResult(
            $pass,
            $pass ? 'RSI_OK' : 'RSI_TOO_HIGH',
            $pass ? 'RSI dalam batas aman' : 'RSI terlalu tinggi'
        );
    }
}
