<?php

namespace App\Services;

use App\Repositories\IntradayRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class YahooIntradayService
{
    /** @var IntradayRepository */
    private $repo;

    /** @var Client */
    private $http;

    public function __construct(IntradayRepository $repo)
    {
        $this->repo = $repo;

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
        $tickers = $this->repo->getActiveTickers($tickerCode);

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

        foreach ($tickers as $t) {
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
                    'last_bar_at'   => $snap['last_bar_at_wib'],
                    'snapshot_at'   => $now->toDateTimeString(),

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
}
