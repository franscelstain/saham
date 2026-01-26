<?php

namespace Tests\Unit\Pricing;

use App\Trade\Pricing\FeeConfig;
use App\Trade\Pricing\FeeModel;
use App\Trade\Pricing\TickLadderConfig;
use App\Trade\Pricing\TickRule;
use Tests\TestCase;

class TickFeeLotPropertyTest extends TestCase
{
    public function testTickRoundingIsIdempotentAndMonotonic(): void
    {
        $tick = new TickRule(new TickLadderConfig([
            ['lt' => 200, 'tick' => 1],
            ['lt' => 500, 'tick' => 2],
            ['lt' => 2000, 'tick' => 5],
            ['lt' => 5000, 'tick' => 10],
            ['lt' => 20000, 'tick' => 25],
            ['lt' => 50000, 'tick' => 50],
            ['tick' => 100],
        ]));

        $prices = [50, 199, 200, 499, 500, 1999, 2000, 4999, 5000, 19999, 20000, 49999, 50000, 99999];
        foreach ($prices as $p) {
            $up = $tick->roundUp((float)$p);
            $down = $tick->roundDown((float)$p);

            $this->assertGreaterThanOrEqual($p, $up, "roundUp must be >= price for $p");
            $this->assertLessThanOrEqual($p, $down, "roundDown must be <= price for $p");

            $this->assertSame($up, $tick->roundUp($up), 'roundUp must be idempotent');
            $this->assertSame($down, $tick->roundDown($down), 'roundDown must be idempotent');

            $this->assertGreaterThanOrEqual(0, $down, 'roundDown must be non-negative');
        }
    }

    public function testFeeModelBreakevenIsConsistent(): void
    {
        $cfg = new FeeConfig(0.0015, 0.0025, 0.0, 0.0, 0.001);
        $m = new FeeModel($cfg);

        $entries = [50, 200, 1000, 2500, 5000, 15000];
        foreach ($entries as $e) {
            $be = $m->breakevenExitPrice((float)$e);
            $pnl = $m->netPnlPerShare((float)$e, (float)$be);

            // Close to zero (tolerate float noise)
            $this->assertLessThan(1e-6, abs($pnl), "breakeven pnl not near zero for entry=$e");

            // Increasing exit price should increase pnl per share.
            $pnlUp = $m->netPnlPerShare((float)$e, (float)($be * 1.01));
            $this->assertGreaterThan($pnl, $pnlUp);
        }
    }
}
