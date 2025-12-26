<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use RuntimeException;

class YahooFinanceService
{
    /** @var \GuzzleHttp\Client */
    private $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => config('services.yahoo.base_uri', 'https://query1.finance.yahoo.com'),
            'timeout'  => (int) config('services.yahoo.timeout', 20),
        ]);
    }

    /**
     * @return array[] rows: date, open, high, low, close, adj_close, volume
     */
    public function historical(string $symbol, Carbon $start, Carbon $end, string $interval = '1d'): array
    {
        // Yahoo pakai unix seconds UTC. period2 disarankan exclusive => +1 hari biar inclusive.
        $period1 = $start->copy()->startOfDay()->timestamp;
        $period2 = $end->copy()->addDay()->startOfDay()->timestamp;

        try {
            $res = $this->http->get("/v8/finance/chart/{$symbol}", [
                'http_errors' => false,
                'query' => [
                    'period1' => $period1,
                    'period2' => $period2,
                    'interval' => $interval,
                    'events' => 'history',
                    'includeAdjustedClose' => 'true',
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Yahoo historical request failed: ".$e->getMessage(), 0, $e);
        }

        if ($res->getStatusCode() !== 200) {
            return [];
        }

        $json = json_decode((string) $res->getBody(), true);
        $result = $json['chart']['result'][0] ?? null;
        if (!$result) return [];

        $ts    = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? null;
        $adj   = $result['indicators']['adjclose'][0]['adjclose'] ?? null;

        if (empty($ts) || !is_array($quote)) return [];

        $opens  = $quote['open'] ?? [];
        $highs  = $quote['high'] ?? [];
        $lows   = $quote['low'] ?? [];
        $closes = $quote['close'] ?? [];
        $vols   = $quote['volume'] ?? [];

        $rows = [];
        for ($i = 0; $i < count($ts); $i++) {
            // skip bar null
            if (!isset($closes[$i]) || $closes[$i] === null) continue;

            $date = Carbon::createFromTimestampUTC((int)$ts[$i])->toDateString();

            $rows[] = [
                'date'      => $date,
                'open'      => isset($opens[$i])  && $opens[$i]  !== null ? (float)$opens[$i]  : null,
                'high'      => isset($highs[$i])  && $highs[$i]  !== null ? (float)$highs[$i]  : null,
                'low'       => isset($lows[$i])   && $lows[$i]   !== null ? (float)$lows[$i]   : null,
                'close'     => (float)$closes[$i],
                'adj_close' => (is_array($adj) && isset($adj[$i]) && $adj[$i] !== null) ? (float)$adj[$i] : null,
                'volume'    => isset($vols[$i])   && $vols[$i]   !== null ? (int)$vols[$i]   : null,
            ];
        }

        return $rows;
    }

    public function toCsvString(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['Date','Open','High','Low','Close','Adj Close','Volume']);

        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['date'],
                $r['open'],
                $r['high'],
                $r['low'],
                $r['close'],
                $r['adj_close'],
                $r['volume'],
            ]);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return (string) $csv;
    }

    /**
     * Ambil nama perusahaan dari symbol via chart meta.
     * Return: ['symbol' => 'BBCA.JK', 'shortName' => '...', 'longName' => '...']
     */
    public function companyName(string $symbol): array
    {
        $res = $this->http->get("/v8/finance/chart/{$symbol}", [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0',
                'Accept'     => 'application/json',
            ],
            'query' => [
                // meta tetap ada meski range kecil
                'interval' => '1d',
                'range'    => '5d',
            ],
        ]);

        $json = json_decode((string) $res->getBody(), true);
        if (!is_array($json)) {
            throw new RuntimeException('Invalid JSON from Yahoo');
        }

        $chart = $json['chart'] ?? null;
        if (!$chart) {
            throw new RuntimeException('Missing chart payload');
        }

        if (!empty($chart['error'])) {
            $msg = $chart['error']['description'] ?? 'Unknown chart error';
            throw new RuntimeException("Yahoo chart error: {$msg}");
        }

        $meta = $chart['result'][0]['meta'] ?? [];

        return [
            'symbol'    => $meta['symbol'] ?? $symbol,
            'shortName' => $meta['shortName'] ?? null,
            'longName'  => $meta['longName'] ?? null,
        ];
    }

    /**
     * Ambil nama perusahaan untuk banyak symbol (loop).
     */
    public function companyNames(array $symbols): array
    {
        $out = [];
        foreach ($symbols as $s) {
            $s = trim((string) $s);
            if ($s === '') continue;

            try {
                $out[] = $this->companyName($s);
            } catch (\Throwable $e) {
                $out[] = [
                    'symbol' => $s,
                    'shortName' => null,
                    'longName' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $out;
    }
}
