<?php

namespace Tests\Unit\Compute;

use App\Trade\Compute\Classifiers\DecisionClassifier;
use App\Trade\Compute\Config\DecisionGuardrails;
use PHPUnit\Framework\TestCase;

class DecisionClassifierTest extends TestCase
{
    private function cls(): DecisionClassifier
    {
        return new DecisionClassifier(new DecisionGuardrails());
    }

    public function testFalseBreakoutMapsToDecision1(): void
    {
        $this->assertSame(1, $this->cls()->classify([
            'close' => 100,
            'signal_code' => 10,
            'volume_label' => 4,
        ]));
    }

    public function testClimaxOrVolumeLabel8MapsToAvoid2(): void
    {
        $this->assertSame(2, $this->cls()->classify([
            'close' => 100,
            'signal_code' => 9,
            'volume_label' => 4,
        ]));

        $this->assertSame(2, $this->cls()->classify([
            'close' => 100,
            'signal_code' => 4,
            'volume_label' => 8,
        ]));
    }

    public function testDistributionMapsToCaution3(): void
    {
        $this->assertSame(3, $this->cls()->classify([
            'close' => 100,
            'signal_code' => 8,
            'volume_label' => 6,
        ]));
    }

    public function testRsiAboveMaxBuyMapsToCaution3(): void
    {
        $this->assertSame(3, $this->cls()->classify([
            'close' => 100,
            'signal_code' => 4,
            'volume_label' => 6,
            'rsi14' => 70.0,
        ]));
    }

    public function testBreakoutWithVolumeAndNoRsiWarnIsBuy5(): void
    {
        $this->assertSame(5, $this->cls()->classify([
            'close' => 105,
            'signal_code' => 4,
            'volume_label' => 6,
            'vol_ratio' => 1.6,
            'rsi14' => 60.0,
            'resistance_20d' => 100.0,
            'ma20' => 100.0,
            'ma50' => 95.0,
            'ma200' => 80.0,
        ]));
    }

    public function testPositiveSignalButNotEnoughVolumeReturns4(): void
    {
        $this->assertSame(4, $this->cls()->classify([
            'close' => 105,
            'signal_code' => 4,
            'volume_label' => 4,
            'vol_ratio' => 1.1,
            'rsi14' => 55.0,
            'ma20' => 100.0,
            'ma50' => 95.0,
        ]));
    }
}
