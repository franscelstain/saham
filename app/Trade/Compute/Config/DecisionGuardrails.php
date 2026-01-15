<?php

namespace App\Trade\Compute\Config;

final class DecisionGuardrails
{
    public float $rsiMaxBuy;
    public float $rsiWarn;
    public float $minVolRatioBuy;
    public float $minVolRatioConfirm;

    public function __construct(
        float $rsiMaxBuy = 70.0,
        float $rsiWarn = 66.0,
        float $minVolRatioBuy = 1.5,
        float $minVolRatioConfirm = 1.0
    ) {
        $this->rsiMaxBuy = $rsiMaxBuy;
        $this->rsiWarn = $rsiWarn;
        $this->minVolRatioBuy = $minVolRatioBuy;
        $this->minVolRatioConfirm = $minVolRatioConfirm;
    }

    public static function fromArray(array $cfg): self
    {
        return new self(
            (float)($cfg['rsi_max_buy'] ?? 70),
            (float)($cfg['rsi_warn'] ?? 66),
            (float)($cfg['min_vol_ratio_buy'] ?? 1.5),
            (float)($cfg['min_vol_ratio_confirm'] ?? 1.0),
        );
    }
}
