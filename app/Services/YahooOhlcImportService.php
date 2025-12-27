<?php

namespace App\Services;

use App\Repositories\TickerOhlcRepository;
use Illuminate\Support\Carbon;

class YahooOhlcImportService
{
    private $yahoo;
    private $repo;

    public function __construct(YahooFinanceService $yahoo, TickerOhlcRepository $repo)
    {
        $this->yahoo = $yahoo;
        $this->repo  = $repo;
    }

    /**
     * Import OHLC daily untuk semua ticker aktif / atau 1 ticker.
     * $start/$end default: 1 tahun terakhir s/d hari ini.
     */
    public function import(?string $tickerCode = null, ?string $start = null, ?string $end = null): array
    {
        $tz        = 'Asia/Jakarta';
        $now       = Carbon::now($tz);

        // Cutoff aman setelah market close + data settle (pilih sesuai kebutuhan)
        $cutoffStr = (string) config('screener.eod_cutoff', '16:30');

        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $cutoffStr, $m)) {
            $cutoffStr = '16:30';
            preg_match('/^(\d{1,2}):(\d{2})$/', $cutoffStr, $m);
        }

        $hh = (int) $m[1];
        $mm = (int) $m[2];

        $cutoff   = $now->copy()->setTime($hh, $mm, 0);
        $todayWib = $now->toDateString();

        $endDate   = $end   ? Carbon::parse($end, $tz)->startOfDay()   : $now->copy()->subDay()->startOfDay();
        $startDate = $start ? Carbon::parse($start, $tz)->startOfDay() : $now->copy()->subYear()->startOfDay();
        
        // Safety: kalau endDate > (hari ini) => clamp ke hari ini
        if ($endDate->gt($now)) {
            $endDate = $now->copy()->startOfDay();
        }

        $tickers = $this->repo->getActiveTickers($tickerCode);

        $stats = [
            'ticker_param' => $tickerCode,
            'start' => $startDate->toDateString(),
            'end'   => $endDate->toDateString(),
            'processed' => 0,
            'bars_attempted' => 0,
            'no_data' => 0,
            'failed' => 0,
            'failed_items' => [],
            'skipped_today_partial' => 0,
        ];

        $buffer = [];

        foreach ($tickers as $t) {
            $stats['processed']++;

            $code = strtoupper(trim($t->ticker_code));
            $symbol = (substr($code, -3) === '.JK') ? $code : ($code . '.JK');

            try {
                $rows = $this->yahoo->historical($symbol, $startDate, $endDate, '1d');

                if (empty($rows)) {
                    $stats['no_data']++;
                    continue;
                }

                foreach ($rows as $r) {
                    // Skip bar today kalau belum lewat cutoff (hindari partial EOD)
                    if (($r['date'] ?? null) === $todayWib && $now->lt($cutoff)) {
                        $stats['skipped_today_partial']++;
                        continue;
                    }

                    $buffer[] = [
                        'ticker_id'  => (int)$t->ticker_id,
                        'trade_date' => $r['date'],
                        'open'       => $r['open'],
                        'high'       => $r['high'],
                        'low'        => $r['low'],
                        'close'      => $r['close'],
                        'adj_close'  => $r['adj_close'],
                        'volume'     => $r['volume'],
                        'source'     => 'yahoo',
                        'is_deleted' => 0,
                        'updated_at' => $now->toDateTimeString(),
                    ];
                }

                if (!empty($buffer)) {
                    // flush per ticker (aman, nggak bengkak memory)
                    $this->repo->upsertDailyBars($buffer);
                    $stats['bars_attempted'] += count($buffer);
                    $buffer = [];
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['failed_items'][] = [
                    'ticker_code' => $code,
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ];

                // pastikan buffer bersih kalau error di tengah ticker
                $buffer = [];
            }
        }

        return $stats;
    }
}
