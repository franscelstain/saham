<?php

namespace App\Trade\Support;

/**
 * TradePerfConfig
 *
 * Wrapper config untuk TradePerf agar helper performa tidak baca config() langsung.
 */
final class TradePerfConfig
{
    private int $tickerChunk;
    private int $httpPool;
    private int $httpTimeoutSec;
    private int $httpRetries;
    private int $httpRetrySleepMs;

    public function __construct(
        int $tickerChunk,
        int $httpPool,
        int $httpTimeoutSec,
        int $httpRetries,
        int $httpRetrySleepMs
    ) {
        $this->tickerChunk = max(1, $tickerChunk);
        $this->httpPool = max(1, $httpPool);
        $this->httpTimeoutSec = max(1, $httpTimeoutSec);
        $this->httpRetries = max(0, $httpRetries);
        $this->httpRetrySleepMs = max(0, $httpRetrySleepMs);
    }

    public function tickerChunk(): int
    {
        return $this->tickerChunk;
    }

    public function httpPool(): int
    {
        return $this->httpPool;
    }

    public function httpTimeoutSec(): int
    {
        return $this->httpTimeoutSec;
    }

    public function httpRetries(): int
    {
        return $this->httpRetries;
    }

    public function httpRetrySleepMs(): int
    {
        return $this->httpRetrySleepMs;
    }
}
