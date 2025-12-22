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
            'base_uri' => config('services.yahoo.base_uri'),
            'timeout'  => (int) config('services.yahoo.timeout', 20),
        ]);
    }

    /**
     * @return array[] rows: date, open, high, low, close, adj_close, volume
     */
    public function historical(string $symbol, Carbon $start, Carbon $end, string $interval = '1d'): array
    {
        // Yahoo pakai unix seconds UTC.
        // period2 disarankan exclusive -> tambah 1 hari biar inclusive.
        $startUtc = Carbon::createFromFormat('Y-m-d', $start->toDateString(), 'UTC')->startOfDay();
        $endUtc   = Carbon::createFromFormat('Y-m-d', $end->toDateString(), 'UTC')->startOfDay()->addDay();

        $period1 = $startUtc->timestamp;
        $period2 = $endUtc->timestamp;

        try {
            // Pakai http_errors=false supaya 4xx tidak jadi exception.
            $res = $this->http->get("/v8/finance/chart/{$symbol}", [
                'http_errors' => false,
                'query' => [
                    'interval' => $interval,
                    'period1'  => $period1,
                    'period2'  => $period2,
                ],
            ]);
        } catch (GuzzleException $e) {
            // Network/timeout/dns -> ini fatal
            throw new RuntimeException("Yahoo request failed: {$e->getMessage()}", 0, $e);
        }

        $status = $res->getStatusCode();
        $body   = (string) $res->getBody();
        $json   = json_decode($body, true);

        // Jika response bukan JSON valid
        if (!is_array($json)) {
            throw new RuntimeException("Yahoo returned non-JSON response (HTTP {$status}).");
        }

        // Yahoo kadang kirim error di chart.error
        $desc = $json['chart']['error']['description'] ?? null;

        // Handle status non-200
        if ($status !== 200) {
            // Kasus kamu: 400 "Data doesn't exist for startDate..."
            if ($status === 400 && is_string($desc) && stripos($desc, "Data doesn't exist for startDate") !== false) {
                return [];
            }

            // 404/invalid symbol kadang juga no data (tergantung Yahoo)
            if (in_array($status, [404], true)) {
                return [];
            }

            // selain itu anggap fatal
            $msg = $desc ?: ("Yahoo error HTTP {$status}");
            throw new RuntimeException($msg);
        }

        // Jika status 200 tapi chart.error ada
        if (is_string($desc) && $desc !== '') {
            if (stripos($desc, "Data doesn't exist for startDate") !== false) {
                return [];
            }
            throw new RuntimeException("Yahoo error: {$desc}");
        }

        $result = $json['chart']['result'][0] ?? null;
        if (!$result) return [];

        $timestamps = $result['timestamp'] ?? [];
        $quote      = $result['indicators']['quote'][0] ?? null;
        $adjClose   = $result['indicators']['adjclose'][0]['adjclose'] ?? null;

        if (empty($timestamps) || !is_array($quote)) return [];

        $rows = [];
        foreach ($timestamps as $i => $ts) {
            $close = $quote['close'][$i] ?? null;
            if ($close === null) continue; // skip bar bolong

            $rows[] = [
                'trade_date' => Carbon::createFromTimestampUTC((int) $ts)->toDateString(),
                'open'       => $quote['open'][$i] ?? null,
                'high'       => $quote['high'][$i] ?? null,
                'low'        => $quote['low'][$i] ?? null,
                'close'      => $close,
                'adj_close'  => is_array($adjClose) ? ($adjClose[$i] ?? null) : null,
                'volume'     => $quote['volume'][$i] ?? null,
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
