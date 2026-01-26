<?php

namespace Tests\Unit\Pricing;

use App\Trade\Pricing\FeeConfig;
use App\Trade\Pricing\FeeModel;
use PHPUnit\Framework\TestCase;

class FeeModelTest extends TestCase
{
    private function model(): FeeModel
    {
        // Must match config/trade.php default fees
        $cfg = new FeeConfig(
            0.0015, // buy fee
            0.0025, // sell fee
            0.0,
            0.0,
            0.0005 // slippage
        );
        return new FeeModel($cfg);
    }

    public function testEffectiveBuyIsAbovePriceAndEffectiveSellIsBelowPrice(): void
    {
        $m = $this->model();
        $this->assertGreaterThan(100.0, $m->effectiveBuyPrice(100.0));
        $this->assertLessThan(100.0, $m->effectiveSellPrice(100.0));
    }

    public function testNetPnlPerShareAndBreakeven(): void
    {
        $m = $this->model();
        $entry = 9000.0;

        $this->assertLessThan(0.0, $m->netPnlPerShare($entry, $entry));

        $be = $m->breakevenExitPrice($entry);
        $this->assertGreaterThan($entry, $be);

        $pnl = $m->netPnlPerShare($entry, $be);
        $this->assertLessThan(1e-6 * $entry, abs($pnl));
    }
}
