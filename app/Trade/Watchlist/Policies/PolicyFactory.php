<?php

namespace App\Trade\Watchlist\Policies;

class PolicyFactory
{
    public function make(string $policy): WatchlistPolicyInterface
    {
        $p = strtoupper(trim($policy));

        switch ($p) {
            case 'WEEKLY_SWING':
                return new WeeklySwingPolicy();
            case 'DIVIDEND_SWING':
                return new DividendSwingPolicy();
            case 'INTRADAY_LIGHT':
                return new IntradayLightPolicy();
            case 'POSITION_TRADE':
                return new PositionTradePolicy();
            case 'NO_TRADE':
                return new NoTradePolicy();
            default:
                // Fail-safe: unknown policy behaves like NO_TRADE.
                return new NoTradePolicy();
        }
    }
}
