<?php

namespace App\Services;

use App\Repositories\Contracts\TickerOhlcRepositoryInterface;
use App\Services\YahooFinanceService;
use Carbon\Carbon;

class YahooOhlcImportService
{
    private $repo;
    private $yahoo;

    public function __construct(TickerOhlcRepositoryInterface $repo, YahooFinanceService $yahoo)
    {
        $this->repo  = $repo;
        $this->yahoo = $yahoo;
    }

    public function import(Carbon $start, Carbon $end, string $interval = '1d', ?string $tickerCode = null): array
    {
        $tickers = $this->repo->getActiveTickers($tickerCode);
        $now = now();

        $stats = [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'interval' => $interval,
            'processed' => 0,
            'success' => 0,
            'no_data' => 0,
            'failed' => 0,
            'failed_items' => [],
        ];

        foreach ($tickers as $t) {
            $stats['processed']++;

            $code = strtoupper(trim($t->ticker_code));
            $symbol = (substr($code, -3) === '.JK') ? $code : ($code . '.JK');

            try {
                $rows = $this->yahoo->historical($symbol, $start, $end, $interval);

                if (empty($rows)) {
                    $stats['no_data']++;
                    continue;
                }

                $payload = [];
                foreach ($rows as $r) {
                    if (empty($r['trade_date']) || $r['close'] === null) continue;

                    $payload[] = [
                        'ticker_id'   => (int) $t->ticker_id,
                        'trade_date'  => $r['trade_date'],
                        'open'        => $r['open'],
                        'high'        => $r['high'],
                        'low'         => $r['low'],
                        'close'       => $r['close'],
                        'adj_close'   => $r['adj_close'],
                        'volume'      => $r['volume'],
                        'source'      => 'yahoo',
                        'is_deleted'  => 0,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }

                if (empty($payload)) {
                    $stats['no_data']++;
                    continue;
                }

                $this->repo->upsertOhlcDaily($payload);
                $stats['success']++;
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

        return $stats;
    }
}
