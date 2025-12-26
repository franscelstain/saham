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
        $tz = 'Asia/Jakarta';
        $endDate   = $end   ? Carbon::parse($end, $tz)   : Carbon::now($tz);
        $startDate = $start ? Carbon::parse($start, $tz) : Carbon::now($tz)->subYear();

        $tickers = $this->repo->getActiveTickers($tickerCode);

        $stats = [
            'ticker_param' => $tickerCode,
            'start' => $startDate->toDateString(),
            'end'   => $endDate->toDateString(),
            'processed' => 0,
            'bars_saved' => 0,
            'no_data' => 0,
            'failed' => 0,
            'failed_items' => [],
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
                        'updated_at' => Carbon::now($tz)->toDateTimeString(),
                    ];
                }

                // flush per ticker (aman, nggak bengkak memory)
                $this->repo->upsertDailyBars($buffer);
                $stats['bars_saved'] += count($buffer);
                $buffer = [];

            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['failed_items'][] = [
                    'ticker_code' => $code,
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }
}
