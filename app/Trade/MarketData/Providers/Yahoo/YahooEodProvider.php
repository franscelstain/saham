<?php

namespace App\Trade\MarketData\Providers\Yahoo;

use App\Trade\MarketData\Providers\Contracts\EodProvider;
use App\Trade\MarketData\DTO\RawBar;
use App\Trade\MarketData\DTO\ProviderFetchResult;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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

                // Yahoo sometimes returns nulls for non-trading days inside range
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
        } catch (GuzzleException $e) {
            return new ProviderFetchResult([], 'NET_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return new ProviderFetchResult([], 'ERROR', $e->getMessage());
        }
    }
}
