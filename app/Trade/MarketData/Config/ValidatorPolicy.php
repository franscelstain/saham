<?php

namespace App\Trade\MarketData\Config;

/**
 * ValidatorPolicy
 *
 * Konfigurasi untuk Phase 7 (validator subset) agar service tidak baca config() langsung.
 */
final class ValidatorPolicy
{
    /** @var int */
    private $maxTickers;

    /** @var int */
    private $dailyCallLimit;

    /** @var float */
    private $disagreeMajorPct;

    public function __construct(int $maxTickers, int $dailyCallLimit, float $disagreeMajorPct)
    {
        $this->maxTickers = max(1, $maxTickers);
        $this->dailyCallLimit = max(1, $dailyCallLimit);
        $this->disagreeMajorPct = max(0.0, $disagreeMajorPct);
    }

    public function maxTickers(): int
    {
        return $this->maxTickers;
    }

    public function dailyCallLimit(): int
    {
        return $this->dailyCallLimit;
    }

    /**
     * Percent threshold, e.g. 1.5 means 1.5%.
     */
    public function disagreeMajorPct(): float
    {
        return $this->disagreeMajorPct;
    }
}
