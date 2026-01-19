<?php

namespace App\Services\Compute;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\TickerIndicatorsDailyRepository;
use App\Repositories\TickerOhlcDailyRepository;
use App\Trade\Compute\Calculators\EodIndicatorsStreamCalculator;
use App\Trade\Compute\Config\ComputeEodPolicy;
use App\Trade\Support\TradeClock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComputeEodService
{
    private MarketCalendarRepository $cal;
    private TickerOhlcDailyRepository $ohlc;
    private TickerIndicatorsDailyRepository $ind;

    private EodIndicatorsStreamCalculator $calculator;

    private EodDateResolver $dateResolver;
    private ComputeEodPolicy $policy;

    public function __construct(
        MarketCalendarRepository $cal,
        TickerOhlcDailyRepository $ohlc,
        TickerIndicatorsDailyRepository $ind,
        EodDateResolver $dateResolver,
        ComputeEodPolicy $policy,
        EodIndicatorsStreamCalculator $calculator
    ) {
        $this->cal = $cal;
        $this->ohlc = $ohlc;
        $this->ind = $ind;
        $this->dateResolver = $dateResolver;
        $this->policy = $policy;
        $this->calculator = $calculator;
    }

    public function runDate(?string $tradeDate = null, ?string $tickerCode = null, int $chunkSize = 200): array
    {
        $tz = TradeClock::tz();

        // SRP_Performa: log hanya dari layer orchestration (service), bukan dari domain/pure logic.
        // Channel ini juga dipakai untuk menandai data canonical yang ternyata invalid (agar akurasi tidak rusak diam-diam).
        $log = logger()->channel('compute_eod');

        $requested = $tradeDate
            ? Carbon::parse($tradeDate, $tz)->toDateString()
            : null;

        $resolved = $this->dateResolver->resolve($requested);

        if (!$resolved) {
            return [
                'status' => 'error',
                'requested_date' => $requested,
                'resolved_trade_date' => null,
                'reason' => 'cannot_resolve_trade_date',
            ];
        }

        $date = $resolved;

        // IMPORTANT: pakai timezone config supaya cutoff bener
        $now = TradeClock::now();
        $today = TradeClock::today();

        // Kalau user minta explicit "today" sebelum cutoff -> skip (biar gak compute data yang belum EOD)
        if ($date === $today && TradeClock::isBeforeEodCutoff()) {
            $log->info('compute_eod.run.skipped', [
                'requested_date' => $requested,
                'resolved_trade_date' => $date,
                'reason' => 'before_cutoff',
                'ticker_filter' => $tickerCode,
                'chunk_size' => $chunkSize,
            ]);
            return [
                'status' => 'skipped',
                'requested_date' => $requested,
                'resolved_trade_date' => $date,
                'reason' => 'before_cutoff',
                'ticker_filter' => $tickerCode,
                'chunk_size' => $chunkSize,
                'tickers_in_scope' => 0,
                'chunks' => 0,
                'processed' => 0,
                'skipped_no_row' => 0,
            ];
        }

        // Boleh dijalankan saat libur -> status skipped
        if (!$this->cal->isTradingDay($date)) {
            $log->info('compute_eod.run.skipped', [
                'requested_date' => $requested,
                'resolved_trade_date' => $date,
                'reason' => 'holiday_or_non_trading_day',
                'ticker_filter' => $tickerCode,
                'chunk_size' => $chunkSize,
            ]);
            return [
                'status' => 'skipped',
                'requested_date' => $requested,
                'resolved_trade_date' => $date,
                'reason' => 'holiday_or_non_trading_day',
                'ticker_filter' => $tickerCode,
                'chunk_size' => $chunkSize,
                'tickers_in_scope' => 0,
                'chunks' => 0,
                'processed' => 0,
                'skipped_no_row' => 0,
            ];
        }

        $prev = $this->cal->previousTradingDate($date);

        // startDate dihitung dari market_calendar (trading days), bukan calendar days.
        // Tujuan: konsisten terhadap libur/weekend dan lebih sesuai dengan aturan compute_eod.md
        $lookbackN = $this->policy->lookbackTradingDays + $this->policy->warmupExtraTradingDays;
        $startDate = $this->cal->lookbackStartDate($date, $lookbackN);

        // hanya ticker yang punya OHLC pada $date
        $tickerIds = $this->ohlc->getTickerIdsHavingRowOnDate($date, $tickerCode);

        $log->info('compute_eod.run.start', [
            'requested_date' => $requested,
            'resolved_trade_date' => $date,
            'prev_trade_date' => $prev,
            'start_date' => $startDate,
            'ticker_filter' => $tickerCode,
            'chunk_size' => $chunkSize,
            'tickers_in_scope' => count($tickerIds),
        ]);

        if (empty($tickerIds)) {
            $log->info('compute_eod.run.done', [
                'requested_date' => $requested,
                'resolved_trade_date' => $date,
                'status' => 'ok',
                'processed' => 0,
                'skipped_no_row' => 0,
                'skipped_invalid' => 0,
            ]);
            return [
                'status' => 'ok',
                'requested_date' => $requested,
                'resolved_trade_date' => $date,
                'prev_trade_date' => $prev,
                'start_date' => $startDate,

                'ticker_filter' => $tickerCode,
                'chunk_size' => $chunkSize,
                'tickers_in_scope' => 0,
                'chunks' => 0,

                'processed' => 0,
                'skipped_no_row' => 0,
            ];
        }

        $processed = 0;
        $totalSkippedNoRow = 0;
        $totalSkippedInvalid = 0;

        DB::disableQueryLog();

        $upsertBatchSize = max(1, (int) $this->policy->upsertBatchSize);

        $chunks = array_chunk($tickerIds, max(1, $chunkSize));

        foreach ($chunks as $ids) {
            // preload prev snapshot (1 query per chunk)
            $prevSnaps = [];
            if ($prev) {
                $prevSnaps = $this->ind->getPrevSnapshotMany($prev, $ids);
            }

            $cursor = $this->ohlc->cursorHistoryRange($startDate, $date, $ids);

            // SRP_Performa: heavy per-bar compute lives in calculator (domain).
            // Service tetap bertanggung jawab atas logging, batching upsert, dan summary.
            $calc = $this->calculator->onInvalidBarOnTradeDate(function (array $ctx) use ($log) {
                $log->warning('compute_eod.invalid_canonical_bar', $ctx);
            });

            $seenOnDate = [];
            $invalidOnDate = [];
            $rowsBuffer = [];

            foreach ($calc->streamRows($cursor, $date, $prevSnaps, $now) as $row) {
                $rowsBuffer[] = $row;

                if (count($rowsBuffer) >= $upsertBatchSize) {
                    $this->ind->upsertMany($rowsBuffer, $upsertBatchSize);
                    $rowsBuffer = [];
                }
            }

            $processed += $calc->processedCount();
            $totalSkippedInvalid += $calc->skippedInvalidOnTradeDateCount();
            $seenOnDate = $calc->seenOnTradeDateMap();
            $invalidOnDate = $calc->invalidOnTradeDateMap();

            if (!empty($rowsBuffer)) {
                $this->ind->upsertMany($rowsBuffer, $upsertBatchSize);
                $rowsBuffer = [];
            }

            // diagnostic: harusnya 0 karena ids dipilih dari "having row on date"
            $skippedNoRow = 0;
            foreach ($ids as $tid) {
                if (empty($seenOnDate[$tid]) && empty($invalidOnDate[$tid])) $skippedNoRow++;
            }
            $totalSkippedNoRow += $skippedNoRow;
        }

        $log->info('compute_eod.run.done', [
            'requested_date' => $requested,
            'resolved_trade_date' => $date,
            'status' => 'ok',
            'ticker_filter' => $tickerCode,
            'chunk_size' => $chunkSize,
            'tickers_in_scope' => count($tickerIds),
            'chunks' => count($chunks),
            'processed' => $processed,
            'skipped_no_row' => $totalSkippedNoRow,
            'skipped_invalid' => $totalSkippedInvalid,
        ]);

        return [
            'status' => 'ok',
            'requested_date' => $requested,
            'resolved_trade_date' => $date,
            'prev_trade_date' => $prev,
            'start_date' => $startDate,

            'ticker_filter' => $tickerCode,
            'chunk_size' => $chunkSize,
            'tickers_in_scope' => count($tickerIds),
            'chunks' => count($chunks),

            'processed' => $processed,
            'skipped_no_row' => $totalSkippedNoRow,
            'skipped_invalid' => $totalSkippedInvalid,
        ];
    }
}
