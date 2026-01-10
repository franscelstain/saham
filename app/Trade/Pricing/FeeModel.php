<?php

namespace App\Trade\Pricing;

class FeeModel
{
    private float $buyRate;
    private float $sellRate;
    private float $extraBuyRate;
    private float $extraSellRate;
    private float $slippageRate;

    public function __construct()
    {
        $this->buyRate = (float) config('trade.fees.buy_rate', 0.0015);
        $this->sellRate = (float) config('trade.fees.sell_rate', 0.0025);
        $this->extraBuyRate = (float) config('trade.fees.extra_buy_rate', 0.0);
        $this->extraSellRate = (float) config('trade.fees.extra_sell_rate', 0.0);
        $this->slippageRate = (float) config('trade.fees.slippage_rate', 0.0005);
    }

    public function effectiveBuyPrice(float $price): float
    {
        // price yang “real” setelah slippage + fee buy
        $p = $price * (1.0 + $this->slippageRate);
        $p = $p * (1.0 + $this->buyRate + $this->extraBuyRate);
        return $p;
    }

    public function effectiveSellPrice(float $price): float
    {
        // net proceeds per share setelah slippage + fee sell
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
     * We solve approximately: effectiveSellPrice(x) = effectiveBuyPrice(entry)
     */
    public function breakevenExitPrice(float $entryPrice): float
    {
        $buyCost = $this->effectiveBuyPrice($entryPrice);

        // invert effectiveSellPrice approximately:
        // buyCost = x*(1-slip)*(1-sellFee)  => x = buyCost / ((1-slip)*(1-sellFee))
        $den = (1.0 - $this->slippageRate) * (1.0 - ($this->sellRate + $this->extraSellRate));
        if ($den <= 0) return $entryPrice;

        return $buyCost / $den;
    }
}
