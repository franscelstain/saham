<?php

namespace App\Trade\MarketData\Providers\Contracts;

use App\DTO\MarketData\ProviderFetchResult;

interface EodProvider
{
    public function name(): string;

    /**
     * Map internal ticker_code (ex: BBCA) to provider symbol (ex: BBCA.JK).
     */
    public function mapTickerCodeToSymbol(string $tickerCode): string;

    /**
     * Fetch EOD bars for [from..to] date range (calendar dates).
     * Provider may return fewer bars; orchestration layer will fill gaps as NO_DATA.
     */
    public function fetch(string $symbol, string $from, string $to): ProviderFetchResult;
}
