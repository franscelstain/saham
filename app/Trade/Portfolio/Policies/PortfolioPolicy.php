<?php

namespace App\Trade\Portfolio\Policies;

use App\DTO\Portfolio\TradeInput;

interface PortfolioPolicy
{
    /**
     * Validate a BUY fill against plan/policy.
     * Returns list of breach codes (empty = ok).
     *
     * @param array<string,mixed> $ctx
     * @return string[]
     */
    public function validateBuy(object $plan, TradeInput $trade, array $ctx): array;

    /**
     * Evaluate EOD valuation and optionally emit risk-management events (e.g. BE_ARMED, SL_MOVED).
     *
     * @param array<string,mixed> $pos Derived position snapshot.
     * @param array<string,mixed> $ctx
     * @return array<int,array<string,mixed>> list of event payloads to insert
     */
    public function eodRiskEvents(object $plan, array $pos, float $close, string $tradeDate, array $ctx): array;
}
