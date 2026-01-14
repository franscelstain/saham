<?php

final class TradePerf
{
    public static function tickerChunk(): int
    {
        return (int) config('trade.perf.ticker_chunk', 200);
    }

    public static function httpPool(): int
    {
        return (int) config('trade.perf.http_pool', 15);
    }
}
