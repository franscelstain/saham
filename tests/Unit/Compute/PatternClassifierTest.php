<?php

namespace Tests\Unit\Compute;

use App\Trade\Compute\Classifiers\PatternClassifier;
use App\Trade\Compute\Config\PatternThresholds;
use PHPUnit\Framework\TestCase;

class PatternClassifierTest extends TestCase
{
    private function cls(): PatternClassifier
    {
        return new PatternClassifier(new PatternThresholds(2.0, 1.5));
    }

    public function testFalseBreakoutReturns10(): void
    {
        $m = [
            'open' => 95,
            'high' => 105,
            'low'  => 90,
            'close' => 99,
            'resistance_20d' => 100,
            'vol_ratio' => 2.2,
        ];

        $this->assertSame(10, $this->cls()->classify($m));
    }

    public function testClimaxReturns9(): void
    {
        $m = [
            'open' => 98,
            'high' => 110,
            'low'  => 97,
            'close' => 109,
            'rsi14' => 82,
            'vol_ratio' => 2.5,
            'resistance_20d' => 100,
        ];

        $this->assertSame(9, $this->cls()->classify($m));
    }

    public function testStrongBreakoutReturns5(): void
    {
        $m = [
            'open' => 99,
            'high' => 110,
            'low'  => 98,
            'close' => 109,
            'rsi14' => 70,
            'vol_ratio' => 2.0,
            'resistance_20d' => 100,
        ];

        $this->assertSame(5, $this->cls()->classify($m));
    }

    public function testBreakoutReturns4WhenBurstButNotStrong(): void
    {
        $m = [
            'open' => 99,
            'high' => 106,
            'low'  => 98,
            'close' => 105,
            'rsi14' => 65,
            'vol_ratio' => 1.6,
            'resistance_20d' => 100,
        ];

        $this->assertSame(4, $this->cls()->classify($m));
    }

    public function testDistributionReturns8(): void
    {
        $m = [
            'open' => 105,
            'high' => 110,
            'low'  => 95,
            'close' => 97,
            'vol_ratio' => 1.6,
            'resistance_20d' => 120,
        ];

        $this->assertSame(8, $this->cls()->classify($m));
    }

    public function testPullbackHealthyReturns7(): void
    {
        $m = [
            'open' => 102,
            'high' => 105,
            'low'  => 99,
            'close' => 100,
            'ma20' => 100,
            'ma50' => 95,
            'ma200' => 80,
            'rsi14' => 55,
            'vol_ratio' => 1.0,
            'support_20d' => 99,
            'resistance_20d' => 120,
        ];

        $this->assertSame(7, $this->cls()->classify($m));
    }
}
