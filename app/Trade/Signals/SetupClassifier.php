<?php

namespace App\Trade\Signals;

use App\DTO\Watchlist\CandidateInput;

class SetupClassifier
{
    private float $confirmFrom;

    public function __construct(float $confirmFrom = 66.0)
    {
        $this->confirmFrom = $confirmFrom;
    }

    public function classify(CandidateInput $c): string
    {
        if ($c->rsi > $this->confirmFrom) return 'SETUP_CONFIRM';
        return 'SETUP_OK';
    }

}
