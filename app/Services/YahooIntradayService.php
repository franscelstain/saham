<?php

namespace App\Services;

use App\Repositories\Screener\WatchlistRepository;
use App\Repositories\IntradayRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class YahooIntradayService
{
    /** @var IntradayRepository */
    private $repo;
    private $watchRepo;

    /** @var Client */
    private $http;

    public function __construct(IntradayRepository $repo, WatchlistRepository $watchRepo)
    {
        $this->repo = $repo;
        $this->watchRepo = $watchRepo;

        $this->http = new Client([
            'base_uri' => config('services.yahoo.base_uri', 'https://query1.finance.yahoo.com'),
            'timeout'  => (int) config('services.yahoo.timeout', 20),
        ]);
    }

    /**
     * Ambil 1 snapshot dari Yahoo (range=1d, interval=1m) untuk 1 symbol.
     * Return:
     * [
     *   'last_price'=>float,
     *   'volume_so_far'=>int,
     *   'open_price'=>?float,
     *   'high_price'=>?float,
     *   'low_price'=>?float,
     *   'last_bar_at_wib'=> 'Y-m-d H:i:s',
     *   'trade_date_wib'=> 'Y-m-d'
     * ]
     */
    public function fetchSnapshot(string $symbol, string $interval = '1m'): ?array
    {
        $maxTry = 3;
        $try = 0;
        $lastErr = null;

        while ($try < $maxTry) {
            $try++;

            try {
                $res = $this->http->get("/v8/finance/chart/{$symbol}", [
                    'http_errors' => false,
                    'query' => [
                        'range'          => '1d',
                        'interval'       => $interval,
                        'includePrePost' => 'false',
                    ],
                ]);
            } catch (GuzzleException $e) {
                $lastErr = $e;
                // retry kecil
                usleep(150000 * $try);
                continue;
            }

            $code = $res->getStatusCode();

            // rate limit / server error -> retry
            if ($code === 429 || ($code >= 500 && $code <= 599)) {
                usleep(250000 * $try); // backoff sederhana
                continue;
            }

            if ($code !== 200) {
                return null;
            }

            $json = json_decode((string) $res->getBody(), true);
            $result = $json['chart']['result'][0] ?? null;
            if (!$result) return null;

            $ts    = $result['timestamp'] ?? [];
            $quote = $result['indicators']['quote'][0] ?? null;
            if (empty($ts) || !is_array($quote)) return null;

            $closes = $quote['close'] ?? [];
            $vols   = $quote['volume'] ?? [];
            $opens  = $quote['open'] ?? [];
            $highs  = $quote['high'] ?? [];
            $lows   = $quote['low'] ?? [];

            // last bar index yang close-nya tidak null
            $lastIdx = null;
            for ($i = count($ts) - 1; $i >= 0; $i--) {
                if (isset($closes[$i]) && $closes[$i] !== null) { $lastIdx = $i; break; }
            }
            if ($lastIdx === null) return null;

            $lastPrice = (float) $closes[$lastIdx];

            // last bar time (UTC unix) -> WIB
            $lastBarTs = (int) $ts[$lastIdx];
            $lastBarWib = Carbon::createFromTimestampUTC($lastBarTs)->setTimezone('Asia/Jakarta');
            $tradeDateWib = $lastBarWib->toDateString();

            // volume_so_far = sum volume bar 1d
            $volSoFar = 0;
            foreach ($vols as $v) {
                if ($v !== null) $volSoFar += (int) $v;
            }

            // open/high/low hari ini dari bar 1d
            $openToday = null;
            foreach ($opens as $v) { if ($v !== null) { $openToday = (float) $v; break; } }

            $highToday = null;
            foreach ($highs as $v) {
                if ($v === null) continue;
                $highToday = ($highToday === null) ? (float)$v : max($highToday, (float)$v);
            }

            $lowToday = null;
            foreach ($lows as $v) {
                if ($v === null) continue;
                $lowToday = ($lowToday === null) ? (float)$v : min($lowToday, (float)$v);
            }

            return [
                'last_price'      => $lastPrice,
                'volume_so_far'   => $volSoFar,
                'open_price'      => $openToday,
                'high_price'      => $highToday,
                'low_price'       => $lowToday,
                'last_bar_at_wib' => $lastBarWib->toDateTimeString(),
                'trade_date_wib'  => $tradeDateWib,
            ];
        }

        if ($lastErr) {
            throw new \RuntimeException("Yahoo intraday request failed: ".$lastErr->getMessage(), 0, $lastErr);
        }

        return null;
    }

    /**
     * Capture snapshot untuk semua ticker aktif / atau 1 ticker.
     * Disimpan 1 row per (ticker_id, trade_date).
     */
    public function capture(?string $tickerCode = null, string $interval = '1m'): array
    {
        // $tickers = $this->repo->getActiveTickers($tickerCode);
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $eodDate = $this->watchRepo->getEodReferenceForToday($today);
        $candidates = $this->watchRepo->getEodCandidates($eodDate);

        // WIB biar konsisten
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();

        $stats = [
            'today'        => $today,
            'snapshot_at'  => $now->toDateTimeString(),
            'processed'    => 0,
            'saved'        => 0,
            'no_data'      => 0,
            'stale'        => 0,
            'failed'       => 0,
            'failed_items' => [],
        ];

        $rowsToSave = [];

        foreach ($candidates as $t) {
            $stats['processed']++;

            $code = strtoupper(trim($t->ticker_code));
            $symbol = (substr($code, -3) === '.JK') ? $code : ($code . '.JK');

            try {
                $snap = $this->fetchSnapshot($symbol, $interval);

                if (!$snap) {
                    $stats['no_data']++;
                    continue;
                }

                // Guard #1: pastikan data Yahoo memang tanggal hari ini WIB
                if (($snap['trade_date_wib'] ?? null) !== $today) {
                    $stats['stale']++;
                    continue;
                }

                $rowsToSave[] = [
                    'ticker_id'     => (int) $t->ticker_id,
                    'trade_date'    => $today,

                    // snapshot_at = waktu CAPTURE (WIB)
                    'snapshot_at'   => $now->toDateTimeString(),
                    'last_bar_at'   => $snap['last_bar_at_wib'] ?? null,

                    'last_price'    => $snap['last_price'],
                    'volume_so_far' => $snap['volume_so_far'],
                    'open_price'    => $snap['open_price'],
                    'high_price'    => $snap['high_price'],
                    'low_price'     => $snap['low_price'],
                    'source'        => 'yahoo',
                    'is_deleted'    => 0,
                    'created_at'    => $now->toDateTimeString(),
                    'updated_at'    => $now->toDateTimeString(),
                ];

                $stats['saved']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['failed_items'][] = [
                    'ticker_code' => $code,
                    'symbol'      => $symbol,
                    'error'       => $e->getMessage(),
                ];
                continue;
            }

            // optional: jaga-jaga rate limit kalau ticker banyak
            // usleep(30000); // 30ms (kalau perlu)
        }

        $this->repo->upsertSnapshots($rowsToSave);

        return $stats;
    }

        /**
     * Capture slice (offset+limit) supaya tidak berat 900 ticker sekaligus.
     */
    public function captureSlice(int $offset, int $limit, string $interval = '1m', int $concurrency = 15): array
    {
        $tickers = $this->repo->getActiveTickersSlice($offset, $limit);
        return $this->captureTickers($tickers, $interval, $concurrency) + [
            'offset' => $offset,
            'limit'  => $limit,
        ];
    }

    /**
     * Capture untuk sekumpulan ticker (Collection berisi ticker_id, ticker_code).
     * Di sini kita pakai parallel fetch biar cepat.
     */
    public function captureTickers($tickers, string $interval = '1m', int $concurrency = 15): array
    {
        // WIB konsisten
        $now   = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();

        $stats = [
            'today'        => $today,
            'snapshot_at'  => $now->toDateTimeString(),
            'processed'    => 0,
            'saved'        => 0,
            'no_data'      => 0,
            'stale'        => 0,
            'failed'       => 0,
            'failed_items' => [],
        ];

        $items = [];
        foreach ($tickers as $t) {
            $code = strtoupper(trim($t->ticker_code));
            $symbol = (substr($code, -3) === '.JK') ? $code : ($code . '.JK');
            $items[$symbol] = $t;
        }

        $stats['processed'] = count($items);
        if ($stats['processed'] === 0) return $stats;

        // 1) parallel fetch
        [$ok, $fail] = $this->fetchSnapshotsPool(array_keys($items), $interval, $concurrency);

        // Retry operasional: hanya untuk error transient (429/5xx/timeout/koneksi)
        if (!empty($fail)) {
            $retryable = [];
            foreach ($fail as $symbol => $err) {
                if ($this->isRetryablePoolError((string) $err)) {
                    $retryable[$symbol] = $err;
                }
            }

            // retry max 2 attempt (1–2 sudah cukup untuk kasus 429/timeout)
            $maxRetry = 2;
            $attempt  = 1;

            while (!empty($retryable) && $attempt <= $maxRetry) {
                // backoff + jitter (ms)
                $sleepMs = $this->poolRetryBackoffMs($attempt, count($retryable));
                usleep($sleepMs * 1000);

                $retrySymbols = array_keys($retryable);

                // turunkan concurrency saat retry (lebih ramah Yahoo)
                $retryConcurrency = max(1, min(5, (int) ceil($concurrency / 3)));

                [$ok2, $fail2] = $this->fetchSnapshotsPool($retrySymbols, $interval, $retryConcurrency);

                // yang berhasil di retry: merge ke ok dan hapus dari fail
                foreach ($ok2 as $symbol => $snap) {
                    $ok[$symbol] = $snap;
                    unset($fail[$symbol]);
                    unset($retryable[$symbol]);
                }

                // yang masih gagal: update message, dan tentukan apakah masih retryable
                foreach ($fail2 as $symbol => $err2) {
                    $fail[$symbol] = "retry{$attempt}_failed: " . $err2;

                    if ($this->isRetryablePoolError((string) $err2)) {
                        $retryable[$symbol] = $err2;
                    } else {
                        unset($retryable[$symbol]);
                    }
                }

                $attempt++;
            }
        }

        // 2) build rows
        $rowsToSave = [];
        foreach ($ok as $symbol => $snap) {
            if (!$snap) { // no_data
                $stats['no_data']++;
                continue;
            }

            // Guard #1: pastikan data Yahoo memang tanggal hari ini WIB
            if (($snap['trade_date_wib'] ?? null) !== $today) {
                $stats['stale']++;
                continue;
            }

            $t = $items[$symbol];

            $rowsToSave[] = [
                'ticker_id'     => (int) $t->ticker_id,
                'trade_date'    => $today,
                'snapshot_at'   => $now->toDateTimeString(),
                'last_bar_at'   => $snap['last_bar_at_wib'] ?? null,

                'last_price'    => $snap['last_price'],
                'volume_so_far' => $snap['volume_so_far'],
                'open_price'    => $snap['open_price'],
                'high_price'    => $snap['high_price'],
                'low_price'     => $snap['low_price'],

                'source'        => 'yahoo',
                'is_deleted'    => 0,
                'updated_at'    => now(),
            ];
        }

        // 3) failed items
        foreach ($fail as $symbol => $err) {
            $stats['failed']++;
            $stats['failed_items'][] = [
                'symbol' => $symbol,
                'error'  => $err,
            ];
        }

        // 4) upsert
        if (!empty($rowsToSave)) {
            $this->repo->upsertSnapshots($rowsToSave);
            $stats['saved'] = count($rowsToSave);
        }

        return $stats;
    }

    /**
     * Parallel fetch Yahoo snapshots.
     * return: [okMap(symbol=>snap|null), failMap(symbol=>error)]
     */
    private function fetchSnapshotsPool(array $symbols, string $interval, int $concurrency): array
    {
        $ok   = [];
        $fail = [];

        $requests = function () use ($symbols, $interval) {
            foreach ($symbols as $symbol) {
                $url = "/v8/finance/chart/{$symbol}?range=1d&interval={$interval}&includePrePost=false";
                yield $symbol => new Request('GET', $url);
            }
        };

        $pool = new Pool($this->http, $requests(), [
            'concurrency' => max(1, $concurrency),
            'fulfilled' => function ($response, $symbol) use (&$ok, &$fail) {
                try {
                    $code = $response->getStatusCode();
                    if ($code !== 200) {
                        $fail[$symbol] = "HTTP {$code}";
                        return;
                    }

                    $json = json_decode((string) $response->getBody(), true);
                    $result = $json['chart']['result'][0] ?? null;
                    if (!$result) {
                        $ok[$symbol] = null;
                        return;
                    }

                    // reuse parsing logic: panggil fetchSnapshot() tidak (biar tidak dobel request),
                    // jadi kita copy parse minimal yang sama seperti di fetchSnapshot().
                    $ts    = $result['timestamp'] ?? [];
                    $quote = $result['indicators']['quote'][0] ?? null;
                    if (empty($ts) || !is_array($quote)) {
                        $ok[$symbol] = null;
                        return;
                    }

                    $closes = $quote['close'] ?? [];
                    $vols   = $quote['volume'] ?? [];
                    $opens  = $quote['open'] ?? [];
                    $highs  = $quote['high'] ?? [];
                    $lows   = $quote['low'] ?? [];

                    // last bar index yang close-nya tidak null
                    $lastIdx = null;
                    for ($i = count($closes) - 1; $i >= 0; $i--) {
                        if (isset($closes[$i]) && $closes[$i] !== null) { $lastIdx = $i; break; }
                    }
                    if ($lastIdx === null) { $ok[$symbol] = null; return; }

                    $lastPrice = (float) $closes[$lastIdx];
                    $lastBarTs = (int) $ts[$lastIdx];
                    $lastBarWib = Carbon::createFromTimestampUTC($lastBarTs)->setTimezone('Asia/Jakarta');
                    $tradeDateWib = $lastBarWib->toDateString();

                    $volSoFar = 0;
                    foreach ($vols as $v) { if ($v !== null) $volSoFar += (int) $v; }

                    $openToday = null;
                    foreach ($opens as $v) { if ($v !== null) { $openToday = (float) $v; break; } }

                    $highToday = null;
                    foreach ($highs as $v) { if ($v !== null) $highToday = ($highToday === null) ? (float)$v : max($highToday, (float)$v); }

                    $lowToday = null;
                    foreach ($lows as $v) { if ($v !== null) $lowToday = ($lowToday === null) ? (float)$v : min($lowToday, (float)$v); }

                    $ok[$symbol] = [
                        'last_price'       => $lastPrice,
                        'volume_so_far'    => $volSoFar,
                        'open_price'       => $openToday,
                        'high_price'       => $highToday,
                        'low_price'        => $lowToday,
                        'last_bar_at_wib'  => $lastBarWib->toDateTimeString(),
                        'trade_date_wib'   => $tradeDateWib,
                    ];
                } catch (\Throwable $e) {
                    $fail[$symbol] = $e->getMessage();
                }
            },
            'rejected' => function ($reason, $symbol) use (&$fail) {
                $fail[$symbol] = is_string($reason) ? $reason : (method_exists($reason, 'getMessage') ? $reason->getMessage() : 'request rejected');
            },
        ]);

        $pool->promise()->wait();

        // symbol yang tidak masuk ok/fail -> anggap no_data
        foreach ($symbols as $s) {
            if (!array_key_exists($s, $ok) && !array_key_exists($s, $fail)) {
                $ok[$s] = null;
            }
        }

        return [$ok, $fail];
    }

    private function isRetryablePoolError(string $err): bool
    {
        $e = strtolower($err);

        // retryable HTTP
        if (preg_match('/http\s+(429|5\d\d)/i', $err)) {
            return true;
        }

        // timeout / koneksi transient (Guzzle/cURL)
        $transient = [
            'timed out',
            'timeout',
            'cURL error 28', // operation timed out
            'cURL error 52', // empty reply
            'cURL error 56', // recv failure
            'connection reset',
            'connection refused',
            'could not resolve host',
            'name resolution',
            'server closed connection',
            'ssl',
            'too many requests',
        ];

        foreach ($transient as $needle) {
            if (strpos($e, strtolower($needle)) !== false) return true;
        }

        return false;
    }

    private function poolRetryBackoffMs(int $attempt, int $n): int
    {
        // base: 250ms, 600ms (attempt 1..2) + jitter 0..200ms + tambahan kecil kalau batch besar
        $base = ($attempt === 1) ? 250 : 600;
        $extra = min(300, (int) ($n * 5)); // n besar → tambah sedikit
        $jitter = random_int(0, 200);

        return $base + $extra + $jitter;
    }
}