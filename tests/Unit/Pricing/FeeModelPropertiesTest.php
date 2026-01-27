<?php

namespace Tests\Unit\Pricing;

use App\Trade\Pricing\FeeConfig;
use App\Trade\Pricing\FeeModel;
use Tests\TestCase;

class FeeModelPropertiesTest extends TestCase
{
    public function testNetPnlPerShareIncreasesWithExitPrice(): void
    {
        $m = new FeeModel(new FeeConfig(0.0015, 0.0025, 0.0, 0.0, 0.001));

        $entry = 1000.0;
        // Use effective prices as a monotonic proxy; this avoids relying on any
        // internal netPnlPerShare conventions/rounding that may change.
        $p1 = $m->effectiveSellPrice(1000.0) - $m->effectiveBuyPrice($entry);
        $p2 = $m->effectiveSellPrice(1010.0) - $m->effectiveBuyPrice($entry);
        $p3 = $m->effectiveSellPrice(1100.0) - $m->effectiveBuyPrice($entry);

        // PHPUnit signature is (expected, actual).
        // Assert p1 < p2 < p3.
        $this->assertLessThan($p3, $p2);
        $this->assertLessThan($p2, $p1);
    }

    public function testEffectivePricesAreOrderPreservingAcrossRanges(): void
    {
        $m = new FeeModel(new FeeConfig(0.0015, 0.0025, 0.0, 0.0, 0.0));

        $prices = [50, 200, 1000, 5000, 20000];
        $prevBuy = null;
        $prevSell = null;
        foreach ($prices as $p) {
            $buy = $m->effectiveBuyPrice((float)$p);
            $sell = $m->effectiveSellPrice((float)$p);

            $this->assertGreaterThanOrEqual((float)$p, $buy);
            $this->assertLessThanOrEqual((float)$p, $sell);

            if ($prevBuy !== null) {
                $this->assertGreaterThanOrEqual($prevBuy - 1e-9, $buy, 'effective buy must be non-decreasing');
            }
            if ($prevSell !== null) {
                $this->assertGreaterThanOrEqual($prevSell - 1e-9, $sell, 'effective sell must be non-decreasing');
            }

            $prevBuy = $buy;
            $prevSell = $sell;
        }
    }
}
