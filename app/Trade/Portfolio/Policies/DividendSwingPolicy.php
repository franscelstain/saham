<?php

namespace App\Trade\Portfolio\Policies;

use App\DTO\Portfolio\TradeInput;
use App\Repositories\MarketCalendarRepository;

/**
 * DividendSwingPolicy
 *
 * Minimal differences vs WeeklySwingPolicy:
 * - cooldown default can be longer
 * - averaging down is still disallowed by default
 */
class DividendSwingPolicy extends WeeklySwingPolicy
{
    /**
     * @param array<string,mixed> $cfg
     */
    public function __construct(MarketCalendarRepository $cal, array $cfg)
    {
        parent::__construct($cal, $cfg);
    }
}
