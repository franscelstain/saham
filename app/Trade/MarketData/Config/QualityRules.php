<?php

namespace App\Trade\MarketData\Config;

final class QualityRules
{
    /** @var float */
    private $priceInRangeTolerance;

    /** @var float */
    private $disagreeMajorPct;

    /** @var float */
    private $gapExtremePct;

    public function __construct(float $priceInRangeTolerance, float $disagreeMajorPct, float $gapExtremePct)
    {
        $this->priceInRangeTolerance = max(0.0, (float) $priceInRangeTolerance);
        $this->disagreeMajorPct = max(0.0, (float) $disagreeMajorPct);
        $this->gapExtremePct = max(0.0, (float) $gapExtremePct);
    }

    public function tol(): float { return $this->priceInRangeTolerance; }
    public function disagreeMajorPct(): float { return $this->disagreeMajorPct; }
    public function gapExtremePct(): float { return $this->gapExtremePct; }
}
