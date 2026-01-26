<?php

namespace Tests\Unit\Pricing;

use App\Trade\Pricing\TickLadderConfig;
use App\Trade\Pricing\TickRule;
use PHPUnit\Framework\TestCase;

class TickRuleTest extends TestCase
{
    private function rule(): TickRule
    {
        // Must match config/trade.php 'pricing.idx_ticks'
        $ladder = [
            ['lt' => 200,  'tick' => 1],
            ['lt' => 500,  'tick' => 2],
            ['lt' => 2000, 'tick' => 5],
            ['lt' => 5000, 'tick' => 10],
            ['lt' => null, 'tick' => 25],
        ];
        return new TickRule(new TickLadderConfig($ladder));
    }

    public function testTickSizeAtBoundaries(): void
    {
        $r = $this->rule();

        $this->assertSame(1, $r->tickSize(199));
        $this->assertSame(2, $r->tickSize(200));
        $this->assertSame(2, $r->tickSize(499));
        $this->assertSame(5, $r->tickSize(500));
        $this->assertSame(5, $r->tickSize(1999));
        $this->assertSame(10, $r->tickSize(2000));
        $this->assertSame(10, $r->tickSize(4999));
        $this->assertSame(25, $r->tickSize(5000));
    }

    public function testRounding(): void
    {
        $r = $this->rule();

        // tick = 2
        $this->assertSame(200.0, $r->roundDown(201.3));
        $this->assertSame(202.0, $r->roundUp(201.3));
        $this->assertSame(202.0, $r->roundNearest(201.3));

        // tick = 5
        $this->assertSame(500.0, $r->roundDown(503.9));
        $this->assertSame(505.0, $r->roundUp(503.9));
        $this->assertSame(505.0, $r->roundNearest(503.9));
    }

    public function testAddAndSubTicks(): void
    {
        $r = $this->rule();

        // tick = 1
        $this->assertSame(200.0, $r->addTicks(199.0, 1));
        $this->assertSame(198.0, $r->subTicks(199.0, 1));

        // tick = 5
        $this->assertSame(515.0, $r->addTicks(510.0, 1));
        $this->assertSame(505.0, $r->subTicks(510.0, 1));
    }
}
