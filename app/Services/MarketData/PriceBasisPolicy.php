<?php

namespace App\Services\MarketData;

final class PriceBasisPolicy
{
    public const BASIS_CLOSE = 'close';
    public const BASIS_ADJ_CLOSE = 'adj_close';

    /**
     * Decide basis for analytics/indicators
     * @return array{basis:string, price:float}
     */
    public function pickForIndicators(?float $close, ?float $adjClose): array
    {
        if ($adjClose !== null && $adjClose > 0) {
            return ['basis' => self::BASIS_ADJ_CLOSE, 'price' => (float) $adjClose];
        }

        return ['basis' => self::BASIS_CLOSE, 'price' => (float) ($close ?? 0.0)];
    }

    public function pickForTrading(?float $close): array
    {
        return ['basis' => self::BASIS_CLOSE, 'price' => (float) ($close ?? 0.0)];
    }
}
