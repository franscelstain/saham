<?php

namespace App\Trade\MarketData\Config;

final class ImportEodHoldRules
{
    public float $holdDisagreeRatioMin;
    public int $holdDisagreeCountMin;

    public float $minDayCoverageRatio;
    public int $minPointsPerDay;

    public int $holdLowCoverageDaysMin;

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
}
