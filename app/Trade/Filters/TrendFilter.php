<?php

namespace App\Trade\Filters;

use App\DTO\Trade\RuleResult;
use App\DTO\Watchlist\CandidateInput;

class TrendFilter
{
    public function check(CandidateInput $c): RuleResult
    {
        $pass = ($c->close > $c->ma20) && ($c->ma20 > $c->ma50) && ($c->ma50 > $c->ma200);

        return new RuleResult(
            $pass,
            $pass ? 'TREND_OK' : 'TREND_FAIL',
            $pass ? 'Close>MA20>MA50>MA200' : 'Trend rule tidak terpenuhi'
        );
    }
}
