<?php

namespace App\Services\MarketData\Contracts;

interface OhlcEodProvider
{
    /**
     * Fetch OHLC EOD untuk 1 ticker dalam rentang tanggal.
     *
     * Return rows:
     * [
     *   ['trade_date'=>'YYYY-MM-DD','open'=>..., 'high'=>..., 'low'=>..., 'close'=>..., 'volume'=>..., 'adj_close'=>...],
     *   ...
     * ]
     */
    public function fetchDaily(string $symbol, string $startDate, string $endDate): array;

    public function name(): string;
}
