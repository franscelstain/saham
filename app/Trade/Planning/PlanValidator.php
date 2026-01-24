<?php

namespace App\Trade\Planning;

class PlanValidator
{
    private PlanningPolicy $policy;

    public function __construct(PlanningPolicy $policy)
    {
        $this->policy = $policy;
    }

    public function validate(TradePlan $p): array
    {
        $errors = [];

        if (!($p->entry > 0 && $p->sl > 0 && $p->tp1 > 0 && $p->tp2 > 0 && $p->be > 0)) {
            $errors[] = 'PRICE_NON_POSITIVE';
        }

        if (!($p->sl < $p->entry)) {
            $errors[] = 'SL_NOT_BELOW_ENTRY';
        }

        if (!($p->tp1 > $p->entry && $p->tp2 > $p->entry)) {
            $errors[] = 'TP_NOT_ABOVE_ENTRY';
        }

        if (!($p->tp2 >= $p->tp1)) {
            $errors[] = 'TP2_BELOW_TP1';
        }

        if (!($p->be >= $p->entry)) {
            $errors[] = 'BE_BELOW_ENTRY';
        }

        // RR minimal (net)
        $minRR = $this->policy->minRrTp2();
        if ($p->rrTp2 < $minRR) {
            $errors[] = 'RR_TP2_TOO_LOW';
        }

        return $errors;
    }
}
