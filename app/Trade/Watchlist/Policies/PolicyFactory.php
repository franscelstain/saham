<?php

namespace App\Trade\Watchlist\Policies;

class PolicyFactory
{
    /**
     * Canonical list of supported watchlist policy codes.
     *
     * Used by docs/tests parity checks to ensure the docs folder contains
     * exactly the policy docs for all known policies (and nothing else).
     */
    public static function knownPolicies(): array
    {
        return [
            'WEEKLY_SWING',
            'DIVIDEND_SWING',
            'INTRADAY_LIGHT',
            'POSITION_TRADE',
            'NO_TRADE',
        ];
    }

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
