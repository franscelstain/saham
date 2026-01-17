<?php

namespace App\Trade\MarketData\Config;

final class ImportPolicy
{
    /** @var int */
    private $lookbackTradingDays;

    /** @var float */
    private $coverageMinPct;

    public function __construct(int $lookbackTradingDays, float $coverageMinPct)
    {
        $this->lookbackTradingDays = max(1, (int) $lookbackTradingDays);
        $this->coverageMinPct = (float) $coverageMinPct;
    }

    public function lookbackTradingDays(): int { return $this->lookbackTradingDays; }
    public function coverageMinPct(): float { return $this->coverageMinPct; }
}
