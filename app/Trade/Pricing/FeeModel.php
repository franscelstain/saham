<?php

namespace App\Trade\Pricing;

class FeeModel
{
    private float $buyRate;
    private float $sellRate;
    private float $extraBuyRate;
    private float $extraSellRate;
    private float $slippageRate;

    public function __construct(FeeConfig $cfg)
    {
        $this->buyRate = $cfg->buyRate();
        $this->sellRate = $cfg->sellRate();
        $this->extraBuyRate = $cfg->extraBuyRate();
        $this->extraSellRate = $cfg->extraSellRate();
        $this->slippageRate = $cfg->slippageRate();
    }

    public function effectiveBuyPrice(float $price): float
    {
        $p = $price * (1.0 + $this->slippageRate);
        $p = $p * (1.0 + $this->buyRate + $this->extraBuyRate);
        return $p;
    }

    public function effectiveSellPrice(float $price): float
    {
        $p = $price * (1.0 - $this->slippageRate);
        $p = $p * (1.0 - ($this->sellRate + $this->extraSellRate));
        return $p;
    }

    /**
     * Net profit per share (sell - buy) in IDR/share, fee-aware.
     */
    public function netPnlPerShare(float $entryPrice, float $exitPrice): float
    {
        $buyCost = $this->effectiveBuyPrice($entryPrice);
        $sellNet = $this->effectiveSellPrice($exitPrice);
        return $sellNet - $buyCost;
    }

    /**
     * Breakeven exit price such that net pnl ~= 0.
     */
    public function breakevenExitPrice(float $entryPrice): float
    {
        $buyCost = $this->effectiveBuyPrice($entryPrice);

        $den = (1.0 - $this->slippageRate) * (1.0 - ($this->sellRate + $this->extraSellRate));
        if ($den <= 0) return $entryPrice;

        return $buyCost / $den;
    }
}
