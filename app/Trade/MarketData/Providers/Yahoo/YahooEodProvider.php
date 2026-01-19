<?php

namespace App\Trade\MarketData\Providers\Yahoo;

use App\Trade\MarketData\Providers\Contracts\EodProvider;
use App\DTO\MarketData\RawBar;
use App\DTO\MarketData\ProviderFetchResult;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

final class YahooEodProvider implements EodProvider
{
    /** @var array */
    private $cfg;

    /** @var Client */
    private $http;

    public function __construct(array $cfg = [], ?Client $http = null)
    {
        $this->cfg = $cfg;
        $this->http = $http ?: new Client([
            'timeout' => isset($cfg['timeout']) ? (float) $cfg['timeout'] : 20.0,
            'connect_timeout' => isset($cfg['connect_timeout']) ? (float) $cfg['connect_timeout'] : 10.0,
            'headers' => [
                'User-Agent' => $cfg['user_agent'] ?? 'TradeAxis/MarketData',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function name(): string
    {
        return 'yahoo';
    }

    public function mapTickerCodeToSymbol(string $tickerCode): string
    {
        $t = strtoupper(trim($tickerCode));
        if ($t === '') return '';

        $suffix = (string) ($this->cfg['suffix'] ?? '.JK');
        if ($suffix !== '' && substr($t, -strlen($suffix)) === $suffix) return $t;

        return $t . $suffix;
    }

    public function fetch(string $symbol, string $from, string $to): ProviderFetchResult
    {
        $symbol = trim($symbol);
        if ($symbol === '') return new ProviderFetchResult([], 'BAD_SYMBOL', 'Empty symbol');

        $retry = (int) ($this->cfg['retry'] ?? 0);
        $sleepMs = (int) ($this->cfg['retry_sleep_ms'] ?? 200);

        $attempts = max(1, $retry + 1);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $res = $this->fetchOnce($symbol, $from, $to);

            if ($res->errorCode === null) {
                return $res; // success
            }

            // retry only for transient errors
            $transient = in_array($res->errorCode, ['NET_ERROR','BAD_JSON','NO_RESULT'], true)
                || (strpos((string)$res->errorCode, 'HTTP_5') === 0);

            if (!$transient || $attempt === $attempts) {
                return $res;
            }

            usleep(max(0, $sleepMs) * 1000);
        }

        return new ProviderFetchResult([], 'ERROR', 'Unexpected retry loop exit');
    }

    /**
     * Fetch many symbols concurrently (best-effort) to speed up 900-ticker runs.
     *
     * Notes:
     * - This still uses 1 request per symbol (Yahoo chart endpoint), but runs them in parallel.
     * - Retry is handled in waves (attempt-1 pool, then retry transient failures, etc.).
     *
     * @param array<int,string> $symbols
     * @return array<string,ProviderFetchResult> map symbol => result
     */
    public function fetchMany(array $symbols, string $from, string $to, int $concurrency = 15): array
    {
        $symbols = array_values(array_unique(array_filter(array_map('strval', $symbols))));
        $out = [];
        if (!$symbols) return $out;

        $retry = (int) ($this->cfg['retry'] ?? 0);
        $sleepMs = (int) ($this->cfg['retry_sleep_ms'] ?? 200);
        $attempts = max(1, $retry + 1);

        // Adaptive concurrency: kalau Yahoo mulai rate-limit (HTTP 429), turunkan concurrency otomatis
        // dan hormati header Retry-After bila ada.
        $curConc = max(1, (int) $concurrency);

        $pending = $symbols;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $tmp = $this->fetchManyOnce($pending, $from, $to, $curConc);

            $next = [];
            $rateLimited = 0;
            $maxRetryAfterMs = 0;
            foreach ($pending as $sym) {
                $res = $tmp[$sym] ?? new ProviderFetchResult([], 'NO_RESULT', 'No result');

                // Determine whether to retry
                $code = $res->errorCode;
                if ($code === null) {
                    $out[$sym] = $res;
                    continue;
                }

                $transient = in_array($code, ['NET_ERROR', 'BAD_JSON', 'NO_RESULT'], true)
                    || (strpos((string) $code, 'HTTP_5') === 0)
                    || $code === 'HTTP_429';

                if ($code === 'HTTP_429') {
                    $rateLimited++;
                    // Parse "Retry-After" hint dari message (dibentuk di fetchManyOnce())
                    $msg = (string) ($res->errorMessage ?? '');
                    if (preg_match('/Retry-After\s*=\s*(\d+)/i', $msg, $m)) {
                        $maxRetryAfterMs = max($maxRetryAfterMs, ((int) $m[1]) * 1000);
                    }
                }

                if ($transient && $attempt < $attempts) {
                    $next[] = $sym;
                } else {
                    $out[$sym] = $res;
                }
            }

            $pending = $next;
            if (!$pending) break;

            // Kalau banyak 429, turunkan concurrency untuk wave berikutnya agar tetap jalan.
            // Faktor 0.6 cukup agresif untuk cepat keluar dari rate-limit.
            if ($rateLimited > 0) {
                $curConc = max(1, (int) floor($curConc * 0.6));
            }

            // Sleep: gunakan max(retry_sleep_ms, Retry-After bila ada), tambah jitter kecil.
            $baseSleep = max(0, (int) $sleepMs);
            $sleep = max($baseSleep, (int) $maxRetryAfterMs);
            $jitter = $sleep > 0 ? random_int(0, min(250, $sleep)) : 0; // <= 250ms

            usleep(($sleep + $jitter) * 1000);
        }

        // Ensure all symbols are present in output
        foreach ($symbols as $sym) {
            if (!isset($out[$sym])) {
                $out[$sym] = new ProviderFetchResult([], 'NO_RESULT', 'No result');
            }
        }

        return $out;
    }

    /**
     * @param array<int,string> $symbols
     * @return array<string,ProviderFetchResult>
     */
    private function fetchManyOnce(array $symbols, string $from, string $to, int $concurrency): array
    {
        $symbols = array_values(array_unique(array_filter(array_map('strval', $symbols))));
        $out = [];
        if (!$symbols) return $out;

        // Yahoo chart API needs epoch seconds (UTC). period2 exclusive.
        $p1 = Carbon::parse($from, 'UTC')->startOfDay()->timestamp;
        $p2 = Carbon::parse($to, 'UTC')->addDay()->startOfDay()->timestamp;

        $base = $this->cfg['base_url'] ?? 'https://query1.finance.yahoo.com';

        $idxToSymbol = $symbols;

        $requests = function () use ($idxToSymbol, $base, $p1, $p2) {
            foreach ($idxToSymbol as $symbol) {
                $url = rtrim($base, '/') . '/v8/finance/chart/' . rawurlencode($symbol);
                $query = [
                    'interval' => '1d',
                    'period1'  => $p1,
                    'period2'  => $p2,
                    'events'   => 'div|split',
                    'includeAdjustedClose' => 'true',
                ];

                yield function () use ($url, $query) {
                    return $this->http->getAsync($url, ['query' => $query]);
                };
            }
        };

        $pool = new Pool($this->http, $requests(), [
            'concurrency' => max(1, $concurrency),
            'fulfilled' => function (ResponseInterface $response, $index) use (&$out, $idxToSymbol) {
                $symbol = $idxToSymbol[(int) $index] ?? '';
                if ($symbol === '') return;

                $code = (int) $response->getStatusCode();
                $body = (string) $response->getBody();

                // Yahoo rate limit: simpan hint Retry-After untuk backoff wave berikutnya.
                if ($code === 429) {
                    $ra = trim((string) $response->getHeaderLine('Retry-After'));
                    $msg = $ra !== '' ? ('Rate limited (Retry-After=' . $ra . 's)') : 'Rate limited (HTTP 429)';
                    $out[$symbol] = new ProviderFetchResult([], 'HTTP_429', $msg);
                    return;
                }

                $out[$symbol] = $this->parseYahooResponse($symbol, $code, $body);
            },
            'rejected' => function ($reason, $index) use (&$out, $idxToSymbol) {
                $symbol = $idxToSymbol[(int) $index] ?? '';
                if ($symbol === '') return;

                $msg = is_object($reason) && method_exists($reason, 'getMessage') ? $reason->getMessage() : (string) $reason;
                $out[$symbol] = new ProviderFetchResult([], 'NET_ERROR', $msg);
            },
        ]);

        // Wait for all requests
        $pool->promise()->wait();

        // Fill gaps (shouldn't happen, but safe)
        foreach ($symbols as $sym) {
            if (!isset($out[$sym])) {
                $out[$sym] = new ProviderFetchResult([], 'NO_RESULT', 'No result');
            }
        }

        return $out;
    }

    private function parseYahooResponse(string $symbol, int $code, string $body): ProviderFetchResult
    {
        if ($code < 200 || $code >= 300) {
            return new ProviderFetchResult([], 'HTTP_' . $code, 'Yahoo HTTP error');
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return new ProviderFetchResult([], 'BAD_JSON', 'Yahoo response not JSON');
        }

        $result = $json['chart']['result'][0] ?? null;
        if (!$result) {
            $err = $json['chart']['error']['description'] ?? 'No result';
            return new ProviderFetchResult([], 'NO_RESULT', (string) $err);
        }

        $ts = $result['timestamp'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];
        $adj = $result['indicators']['adjclose'][0]['adjclose'] ?? null;

        $opens  = $quote['open'] ?? [];
        $highs  = $quote['high'] ?? [];
        $lows   = $quote['low'] ?? [];
        $closes = $quote['close'] ?? [];
        $vols   = $quote['volume'] ?? [];

        $bars = [];
        $n = is_array($ts) ? count($ts) : 0;

        for ($i = 0; $i < $n; $i++) {
            $epoch = isset($ts[$i]) ? (int) $ts[$i] : null;

            $o = isset($opens[$i]) ? $opens[$i] : null;
            $h = isset($highs[$i]) ? $highs[$i] : null;
            $l = isset($lows[$i]) ? $lows[$i] : null;
            $c = isset($closes[$i]) ? $closes[$i] : null;
            $v = isset($vols[$i]) ? $vols[$i] : null;
            $a = (is_array($adj) && isset($adj[$i])) ? $adj[$i] : null;

            $bars[] = new RawBar(
                $this->name(),
                $symbol,
                $epoch,
                $o !== null ? (float) $o : null,
                $h !== null ? (float) $h : null,
                $l !== null ? (float) $l : null,
                $c !== null ? (float) $c : null,
                $a !== null ? (float) $a : null,
                $v !== null ? (int) $v : null,
                null,
                null
            );
        }

        return new ProviderFetchResult($bars, null, null);
    }

    public function fetchOnce(string $symbol, string $from, string $to): ProviderFetchResult
    {
        $symbol = trim($symbol);
        if ($symbol === '') {
            return new ProviderFetchResult([], 'BAD_SYMBOL', 'Empty symbol');
        }

        // Yahoo chart API needs epoch seconds. Use UTC midnight boundaries.
        // Make period2 exclusive: add 1 day so last date included.
        $p1 = Carbon::parse($from, 'UTC')->startOfDay()->timestamp;
        $p2 = Carbon::parse($to, 'UTC')->addDay()->startOfDay()->timestamp;

        $base = $this->cfg['base_url'] ?? 'https://query1.finance.yahoo.com';
        $url = rtrim($base, '/') . '/v8/finance/chart/' . rawurlencode($symbol);

        $query = [
            'interval' => '1d',
            'period1'  => $p1,
            'period2'  => $p2,
            'events'   => 'div|split',
            'includeAdjustedClose' => 'true',
        ];

        try {
            $resp = $this->http->request('GET', $url, ['query' => $query]);
            $code = (int) $resp->getStatusCode();
            $body = (string) $resp->getBody();
            return $this->parseYahooResponse($symbol, $code, $body);
        } catch (GuzzleException $e) {
            return new ProviderFetchResult([], 'NET_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return new ProviderFetchResult([], 'ERROR', $e->getMessage());
        }
    }
}
