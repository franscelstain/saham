<?php

namespace App\Services\MarketData\Providers;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Services\MarketData\Contracts\OhlcEodProvider;

class YahooOhlcEodProvider implements OhlcEodProvider
{
    private Client $http;

    public function __construct()
    {
        $baseUrl = (string) config('trade.market_data.providers.yahoo.base_url', 'https://query1.finance.yahoo.com');
        $timeout = (int) config('trade.market_data.providers.yahoo.timeout', 20);

        $this->http = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => $timeout,
        ]);
    }

    public function name(): string
    {
        return 'yahoo';
    }

    public function fetchDaily(string $symbol, string $startDate, string $endDate): array
    {
        $tz = (string) config('trade.market_data.ohlc_eod.timezone', config('trade.compute.eod_timezone', 'Asia/Jakarta'));

        // Yahoo download uses unix seconds.
        // period2 is exclusive-ish; safest: endDate endOfDay + 1 day startOfDay
        $p1 = Carbon::parse($startDate, $tz)->startOfDay()->timestamp;
        $p2 = Carbon::parse($endDate, $tz)->endOfDay()->addSecond()->timestamp;

        $path = "/v7/finance/download/{$symbol}";
        $query = [
            'period1' => $p1,
            'period2' => $p2,
            'interval' => '1d',
            'events' => 'history',
            'includeAdjustedClose' => 'true',
        ];

        $ua = (string) config('trade.market_data.providers.yahoo.user_agent', 'Mozilla/5.0');
        $retry = (int) config('trade.market_data.providers.yahoo.retry', 2);
        $sleepMs = (int) config('trade.market_data.providers.yahoo.retry_sleep_ms', 250);

        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $resp = $this->http->request('GET', $path, [
                    'headers' => [
                        'User-Agent' => $ua,
                        'Accept' => 'text/csv',
                    ],
                    'query' => $query,
                ]);

                $csv = (string) $resp->getBody();
                return $this->parseCsv($csv);

            } catch (GuzzleException $e) {
                if ($attempt > $retry + 1) {
                    throw $e;
                }
                usleep($sleepMs * 1000);
            }
        }
    }

    private function parseCsv(string $csv): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($csv));
        if (!$lines || count($lines) < 2) return [];

        // Header: Date,Open,High,Low,Close,Adj Close,Volume
        $out = [];
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $cols = str_getcsv($line);
            if (count($cols) < 7) {
                continue;
            }

            $date = (string) $cols[0];
            if ($date === '' || $date === 'null') {
                continue;
            }

            // Yahoo sometimes returns "null" fields for missing data
            if (
                $cols[1] === 'null' || $cols[2] === 'null' || $cols[3] === 'null' ||
                $cols[4] === 'null' || $cols[5] === 'null' || $cols[6] === 'null'
            ) {
                continue;
            }

            $out[] = [
                'trade_date' => $date,
                'open' => (float) $cols[1],
                'high' => (float) $cols[2],
                'low'  => (float) $cols[3],
                'close'=> (float) $cols[4],
                'adj_close' => (float) $cols[5],
                'volume' => (int) $cols[6],
            ];
        }

        return $out;
    }
}
