<?php

namespace App\Trade\Support;

final class TradePerf
{
    /** @var TradePerfConfig|null */
    private static $cfg;

    public static function init(TradePerfConfig $cfg): void
    {
        self::$cfg = $cfg;
    }

    public static function tickerChunk(): int
    {
        return self::$cfg ? self::$cfg->tickerChunk() : 200;
    }

    public static function httpPoolSize(): int
    {
        return self::$cfg ? self::$cfg->httpPool() : 20;
    }

    public static function httpTimeoutSec(): int
    {
        return self::$cfg ? self::$cfg->httpTimeoutSec() : 20;
    }

    public static function httpRetries(): int
    {
        return self::$cfg ? self::$cfg->httpRetries() : 2;
    }

    public static function httpRetrySleepMs(): int
    {
        return self::$cfg ? self::$cfg->httpRetrySleepMs() : 300;
    }
}
