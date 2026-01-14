<?php

namespace App\Services\MarketData\Importers;

use Carbon\Carbon;
use App\Repositories\TickerRepository;
use App\Repositories\TickerOhlcDailyRepository;
use App\Services\MarketData\Contracts\OhlcEodProvider;

class OhlcEodImportService
{
    private TickerRepository $tickers;
    private TickerOhlcDailyRepository $ohlc;
    private OhlcEodProvider $provider;

    public function __construct(
        TickerRepository $tickers,
        TickerOhlcDailyRepository $ohlc,
        OhlcEodProvider $provider
    ) {
        $this->tickers = $tickers;
        $this->ohlc = $ohlc;
        $this->provider = $provider;
    }

    public function run(string $startDate, string $endDate, ?string $tickerCode = null): array
    {
        $tz = (string) config('trade.market_data.ohlc_eod.timezone', config('trade.compute.eod_timezone', 'Asia/Jakarta'));
        $suffix = (string) config('trade.market_data.providers.yahoo.suffix', '.JK');

        $startDate = Carbon::parse($startDate, $tz)->toDateString();
        $endDate   = Carbon::parse($endDate, $tz)->toDateString();

        if ($startDate > $endDate) {
            return [
                'status' => 'error',
                'reason' => 'invalid_range',
                'start' => $startDate,
                'end' => $endDate,
            ];
        }

        $tickerChunk = (int) config('trade.market_data.ohlc_eod.chunk_tickers', 50);
        $rowsChunk   = (int) config('trade.market_data.ohlc_eod.chunk_rows', 500);

        $list = $this->tickers->listActive($tickerCode);

        $totalTickers = count($list);
        $imported = 0;
        $failed = 0;

        $chunks = array_chunk($list, max(1, $tickerChunk));

        foreach ($chunks as $chunk) {
            $buffer = [];

            foreach ($chunk as $t) {
                $tid  = (int) $t['ticker_id'];
                $code = (string) $t['ticker_code'];

                $symbol = $code . $suffix; // BEI default

                try {
                    $rows = $this->provider->fetchDaily($symbol, $startDate, $endDate);

                    foreach ($rows as $r) {
                        // minimal validation
                        if (empty($r['trade_date'])) continue;

                        $buffer[] = [
                            'ticker_id' => $tid,
                            'trade_date' => (string) $r['trade_date'],
                            'open' => (float) $r['open'],
                            'high' => (float) $r['high'],
                            'low' => (float) $r['low'],
                            'close' => (float) $r['close'],
                            'volume' => (int) $r['volume'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (count($buffer) >= $rowsChunk) {
                            $imported += $this->ohlc->upsertMany($buffer);
                            $buffer = [];
                        }
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    // Kalau mau logging: log error + ticker_code
                    // \Log::warning('[OHLC_IMPORT] failed', ['ticker'=>$code,'err'=>$e->getMessage()]);
                    continue;
                }
            }

            if (!empty($buffer)) {
                $imported += $this->ohlc->upsertMany($buffer);
            }
        }

        return [
            'status' => 'ok',
            'provider' => $this->provider->name(),
            'start' => $startDate,
            'end' => $endDate,
            'ticker_filter' => $tickerCode,
            'tickers_total' => $totalTickers,
            'imported_rows' => $imported,
            'failed_tickers' => $failed,
        ];
    }
}
