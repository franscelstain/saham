<?php

namespace App\Services;

use App\Repositories\IntradayRepository;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class YahooIntradayService
{
    private $repo;
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
     * Return: ['last_price'=>..., 'volume_so_far'=>..., 'open_price'=>..., 'high_price'=>..., 'low_price'=>...]
     */
    public function fetchSnapshot(string $symbol, string $interval = '1m'): ?array
    {
        try {
            $res = $this->http->get("/v8/finance/chart/{$symbol}", [
                'http_errors' => false,
                'query' => [
                    'range' => '1d',
                    'interval' => $interval,
                    'includePrePost' => 'false',
                ],
            ]);
        } catch (GuzzleException $e) {
            // network/timeout
            throw new \RuntimeException("Yahoo intraday request failed: ".$e->getMessage(), 0, $e);
        }

        if ($res->getStatusCode() !== 200) {
            return null; // no data / symbol invalid / dll (biar service tidak meledak)
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

        // last_price = close bar terakhir yang tidak null
        $lastIdx = null;
        for ($i = count($ts) - 1; $i >= 0; $i--) {
            if (isset($closes[$i]) && $closes[$i] !== null) { $lastIdx = $i; break; }
        }
        if ($lastIdx === null) return null;

        $lastPrice = (float) $closes[$lastIdx];

        // volume_so_far = sum volume bar 1d
        $volSoFar = 0;
        foreach ($vols as $v) {
            if ($v !== null) $volSoFar += (int) $v;
        }

        // optional: open/high/low hari ini dari bar 1d
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
            'last_price'    => $lastPrice,
            'volume_so_far' => $volSoFar,
            'open_price'    => $openToday,
            'high_price'    => $highToday,
            'low_price'     => $lowToday,
        ];
    }

    /**
     * Capture snapshot untuk semua ticker aktif / atau 1 ticker.
     */
    public function capture(?string $tickerCode = null, string $interval = '1m'): array
    {
        $tickers = $this->repo->getActiveTickers($tickerCode);

        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $nowStr = $now->format('Y-m-d H:i:s');

        $stats = [
            'today' => $today,
            'snapshot_at' => $nowStr,
            'processed' => 0,
            'saved' => 0,
            'no_data' => 0,
            'failed' => 0,
            'failed_items' => [],
        ];

        $rowsToSave = [];

        foreach ($tickers as $t) {
            $stats['processed']++;

            $code = strtoupper(trim($t->ticker_code));
            // PHP 7.3: pakai substr, bukan str_ends_with
            $symbol = (substr($code, -3) === '.JK') ? $code : ($code . '.JK');

            try {
                $snap = $this->fetchSnapshot($symbol, $interval);

                if (!$snap) {
                    $stats['no_data']++;
                    continue;
                }

                $rowsToSave[] = [
                    'ticker_id'     => (int) $t->ticker_id,
                    'trade_date'    => $today,
                    'snapshot_at'   => $nowStr,
                    'last_price'    => $snap['last_price'],
                    'volume_so_far' => $snap['volume_so_far'],
                    'open_price'    => $snap['open_price'],
                    'high_price'    => $snap['high_price'],
                    'low_price'     => $snap['low_price'],
                    'source'        => 'yahoo',
                    'is_deleted'    => 0,
                    'created_at'    => $nowStr,
                    'updated_at'    => $nowStr,
                ];

                $stats['saved']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['failed_items'][] = [
                    'ticker_code' => $code,
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ];
                continue;
            }
        }

        // simpan bulk (lebih cepat)
        $this->repo->upsertSnapshots($rowsToSave);

        return $stats;
    }
}
