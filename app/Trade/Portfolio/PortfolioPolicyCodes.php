<?php

namespace App\Trade\Portfolio;

/**
 * PortfolioPolicyCodes
 *
 * Sumber kebenaran kode strategy policy Portfolio sesuai docs/PORTFOLIO.md.
 * (Harus selaras dengan WATCHLIST.md)
 */
final class PortfolioPolicyCodes
{
    public const WEEKLY_SWING = 'WEEKLY_SWING';
    public const DIVIDEND_SWING = 'DIVIDEND_SWING';
    public const INTRADAY_LIGHT = 'INTRADAY_LIGHT';
    public const POSITION_TRADE = 'POSITION_TRADE';
    public const NO_TRADE = 'NO_TRADE';

    /** @return array<int,string> */
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

    public static function isValid(?string $code): bool
    {
        if ($code === null) return false;
        $code = trim($code);
        if ($code === '') return false;
        return in_array($code, self::all(), true);
    }
}
