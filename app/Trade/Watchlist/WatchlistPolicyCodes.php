<?php

namespace App\Trade\Watchlist;

final class WatchlistPolicyCodes
{
    public const WEEKLY_SWING   = 'WEEKLY_SWING';
    public const DIVIDEND_SWING = 'DIVIDEND_SWING';
    public const INTRADAY_LIGHT = 'INTRADAY_LIGHT';
    public const POSITION_TRADE = 'POSITION_TRADE';
    public const NO_TRADE       = 'NO_TRADE';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::WEEKLY_SWING,
            self::DIVIDEND_SWING,
            self::INTRADAY_LIGHT,
            self::POSITION_TRADE,
            self::NO_TRADE,
        ];
    }

    public static function isValid(string $code): bool
    {
        $c = strtoupper(trim($code));
        return in_array($c, self::all(), true);
    }
}
