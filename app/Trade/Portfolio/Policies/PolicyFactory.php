<?php

namespace App\Trade\Portfolio\Policies;

use App\Repositories\MarketCalendarRepository;

class PolicyFactory
{
    private MarketCalendarRepository $cal;
    /** @var array<string,mixed> */
    private array $cfg;

    /**
     * @param array<string,mixed> $cfg
     */
    public function __construct(MarketCalendarRepository $cal, array $cfg)
    {
        $this->cal = $cal;
        $this->cfg = $cfg;
    }

    public function make(string $strategyCode): ?PortfolioPolicy
    {
        $code = strtoupper(trim($strategyCode));
        if ($code === '') return null;

        if ($code === 'WEEKLY_SWING') {
            return new WeeklySwingPolicy($this->cal, (array)($this->cfg['weekly_swing'] ?? []));
        }
        if ($code === 'DIVIDEND_SWING') {
            return new DividendSwingPolicy($this->cal, (array)($this->cfg['dividend_swing'] ?? []));
        }

        // Unknown strategies: no policy enforcement (still records fills/positions)
        return null;
    }
}
