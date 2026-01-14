<?php

namespace App\Trade\Support;

final class TradePerf
{
    public static function tickerChunk(): int
    {
        return (int) config('trade.perf.ticker_chunk', 200);
    }

    public static function httpPoolSize(): int
    {
        return (int) config('trade.perf.http_pool', 20);
    }

    public static function httpTimeoutSec(): int
    {
        return (int) config('trade.perf.http_timeout', 20);
    }

    public static function httpRetries(): int
    {
        return (int) config('trade.perf.retries', 2);
    }

    public static function httpRetrySleepMs(): int
    {
        return (int) config('trade.perf.retry_sleep_ms', 300);
    }
}
