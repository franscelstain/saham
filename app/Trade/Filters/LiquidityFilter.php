<?php

namespace App\Trade\Filters;

use App\DTO\Trade\RuleResult;
use App\DTO\Watchlist\CandidateInput;

class LiquidityFilter
{
    private float $minValueEst;

    public function __construct(float $minValueEst)
    {
        $this->minValueEst = $minValueEst;
    }

    public function check(CandidateInput $c): RuleResult
    {
        $pass = ($c->valueEst >= $this->minValueEst);

        return new RuleResult(
            $pass,
            $pass ? 'LIQ_OK' : 'LIQ_TOO_LOW',
            $pass ? 'Likuiditas memenuhi' : 'Likuiditas terlalu rendah'
        );
    }
}
