<?php

namespace App\Services\Trade;

use App\Trade\Planning\PlanValidator;
use App\Trade\Planning\TradePlanner;
use App\Trade\Pricing\FeeModel;
use App\Trade\Pricing\TickRule;

class TradePlanService
{
    private TradePlanner $planner;
    private PlanValidator $validator;

    public function __construct(TickRule $tick, FeeModel $fee, PlanValidator $validator)
    {
        $this->planner = new TradePlanner($tick, $fee);
        $this->validator = $validator;
    }

    public function build(array $metrics): array
    {
        $plan = $this->planner->make($metrics);

        return array_merge(
            $plan->toArray(),
            ['errors' => $this->validator->validate($plan)]
        );
    }

    public function buildFromCandidate($c): array
    {
        $metrics = [
            'close' => (float) $c->close,
            'high' => (float) ($c->high ?? $c->close),
            'low' => (float) ($c->low ?? $c->close),
            'atr14' => $c->atr14 ?? null,
            'support_20d' => ($c->support20d ?? ($c->support_20d ?? null)),
            'resistance_20d' => ($c->resistance20d ?? ($c->resistance_20d ?? null)),
        ];

        return $this->build($metrics);
    }
}
