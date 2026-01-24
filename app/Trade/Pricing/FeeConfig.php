<?php

namespace App\Trade\Pricing;

/**
 * FeeConfig
 *
 * Holder nilai fee/slippage agar FeeModel tidak baca config() langsung.
 */
final class FeeConfig
{
    private float $buyRate;
    private float $sellRate;
    private float $extraBuyRate;
    private float $extraSellRate;
    private float $slippageRate;

    public function __construct(
        float $buyRate,
        float $sellRate,
        float $extraBuyRate,
        float $extraSellRate,
        float $slippageRate
    ) {
        $this->buyRate = max(0.0, $buyRate);
        $this->sellRate = max(0.0, $sellRate);
        $this->extraBuyRate = max(0.0, $extraBuyRate);
        $this->extraSellRate = max(0.0, $extraSellRate);
        $this->slippageRate = max(0.0, $slippageRate);
    }

    public function buyRate(): float { return $this->buyRate; }
    public function sellRate(): float { return $this->sellRate; }
    public function extraBuyRate(): float { return $this->extraBuyRate; }
    public function extraSellRate(): float { return $this->extraSellRate; }
    public function slippageRate(): float { return $this->slippageRate; }
}
