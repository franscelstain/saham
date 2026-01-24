<?php

namespace App\Trade\MarketData\Config;

/**
 * ImportHoldRules
 *
 * Ambang batas untuk menahan publish canonical (CANONICAL_HELD).
 */
final class ImportHoldRules
{
    private float $holdDisagreeRatioMin;
    private int $holdDisagreeCountMin;
    private float $minDayCoverageRatio;
    private int $minPointsPerDay;
    private int $holdLowCoverageDaysMin;

    public function __construct(
        float $holdDisagreeRatioMin,
        int $holdDisagreeCountMin,
        float $minDayCoverageRatio,
        int $minPointsPerDay,
        int $holdLowCoverageDaysMin
    ) {
        $this->holdDisagreeRatioMin = max(0.0, $holdDisagreeRatioMin);
        $this->holdDisagreeCountMin = max(0, $holdDisagreeCountMin);
        $this->minDayCoverageRatio = max(0.0, min(1.0, $minDayCoverageRatio));
        $this->minPointsPerDay = max(0, $minPointsPerDay);
        $this->holdLowCoverageDaysMin = max(0, $holdLowCoverageDaysMin);
    }

    public function holdDisagreeRatioMin(): float { return $this->holdDisagreeRatioMin; }
    public function holdDisagreeCountMin(): int { return $this->holdDisagreeCountMin; }
    public function minDayCoverageRatio(): float { return $this->minDayCoverageRatio; }
    public function minPointsPerDay(): int { return $this->minPointsPerDay; }
    public function holdLowCoverageDaysMin(): int { return $this->holdLowCoverageDaysMin; }
}
