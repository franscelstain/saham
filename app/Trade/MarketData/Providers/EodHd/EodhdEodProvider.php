<?php

namespace App\Trade\MarketData\Providers\EodHd;

use App\Trade\MarketData\Providers\Contracts\EodProvider;
use App\DTO\MarketData\ProviderFetchResult;
use App\DTO\MarketData\RawBar;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * EODHD provider
 *
 * Intended usage in TradeAxis:
 * - Validator / sampling provider (limited daily API calls).
 * - Do NOT use for full 900-ticker daily import.
 */
final class EodhdEodProvider implements EodProvider
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
        return 'eodhd';
    }

    public function mapTickerCodeToSymbol(string $tickerCode): string
    {
        $t = strtoupper(trim($tickerCode));
        if ($t === '') return '';

        // EODHD uses exchange suffix too (IDX usually BBCA.JK)
        $suffix = (string) ($this->cfg['suffix'] ?? '.JK');
        if ($suffix !== '' && substr($t, -strlen($suffix)) === $suffix) return $t;

        return $t . $suffix;
    }

    public function fetch(string $symbol, string $from, string $to): ProviderFetchResult
    {
        $symbol = trim($symbol);
        if ($symbol === '') return new ProviderFetchResult([], 'BAD_SYMBOL', 'Empty symbol');

        $apiKey = (string) ($this->cfg['api_token'] ?? '');
        if ($apiKey === '') {
            return new ProviderFetchResult([], 'NO_API_KEY', 'EODHD api_key is empty');
        }

        $retry = (int) ($this->cfg['retry'] ?? 0);
        $sleepMs = (int) ($this->cfg['retry_sleep_ms'] ?? 200);
        $attempts = max(1, $retry + 1);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $res = $this->fetchOnce($symbol, $from, $to, $apiKey);

            if ($res->errorCode === null) {
                return $res;
            }

            // retry only transient
            $transient = in_array($res->errorCode, ['NET_ERROR','BAD_JSON','NO_RESULT','HTTP_429'], true)
                || (strpos((string) $res->errorCode, 'HTTP_5') === 0);

            if (!$transient || $attempt === $attempts) {
                return $res;
            }

            usleep(max(0, $sleepMs) * 1000);
        }

        return new ProviderFetchResult([], 'ERROR', 'Unexpected retry loop exit');
    }

    private function fetchOnce(string $symbol, string $from, string $to, string $apiKey): ProviderFetchResult
    {
        $base = (string) ($this->cfg['base_url'] ?? 'https://eodhd.com/api');
        $url = rtrim($base, '/') . '/eod/' . rawurlencode($symbol);

        $query = [
            'from' => $from,
            'to' => $to,
            'api_token' => $apiKey,
            'fmt' => 'json',
        ];

        try {
            $resp = $this->http->request('GET', $url, ['query' => $query]);
            $code = (int) $resp->getStatusCode();
            $body = (string) $resp->getBody();

            if ($code < 200 || $code >= 300) {
                return new ProviderFetchResult([], 'HTTP_' . $code, 'EODHD HTTP error');
            }

            $json = json_decode($body, true);
            if (!is_array($json)) {
                return new ProviderFetchResult([], 'BAD_JSON', 'EODHD response not JSON');
            }

            // EODHD returns an array of rows.
            // Example row keys: date, open, high, low, close, adjusted_close (optional), volume.
            $bars = [];
            foreach ($json as $row) {
                if (!is_array($row)) continue;

                $date = (string) ($row['date'] ?? '');
                if ($date === '') continue;

                $epoch = Carbon::parse($date, 'UTC')->startOfDay()->timestamp;

                $o = array_key_exists('open', $row) ? $row['open'] : null;
                $h = array_key_exists('high', $row) ? $row['high'] : null;
                $l = array_key_exists('low', $row) ? $row['low'] : null;
                $c = array_key_exists('close', $row) ? $row['close'] : null;

                $adj = null;
                if (array_key_exists('adjusted_close', $row)) $adj = $row['adjusted_close'];
                if ($adj === null && array_key_exists('adj_close', $row)) $adj = $row['adj_close'];

                $v = array_key_exists('volume', $row) ? $row['volume'] : null;

                $bars[] = new RawBar(
                    $this->name(),
                    $symbol,
                    $epoch,
                    $o !== null ? (float) $o : null,
                    $h !== null ? (float) $h : null,
                    $l !== null ? (float) $l : null,
                    $c !== null ? (float) $c : null,
                    $adj !== null ? (float) $adj : null,
                    $v !== null ? (int) $v : null,
                    null,
                    null
                );
            }

            if (!$bars) {
                return new ProviderFetchResult([], 'NO_RESULT', 'EODHD returned empty bars');
            }

            return new ProviderFetchResult($bars, null, null);
        } catch (GuzzleException $e) {
            return new ProviderFetchResult([], 'NET_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return new ProviderFetchResult([], 'ERROR', $e->getMessage());
        }
    }
}
