<?php

namespace App\Trade\Watchlist;

final class WatchlistSources
{
    public static function preopenContract(string $policy): string
    {
        $pol = strtolower(trim($policy));
        return 'preopen_contract' . ($pol !== '' ? '_' . $pol : '');
    }
}
