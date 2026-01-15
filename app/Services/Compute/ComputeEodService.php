<?php

namespace App\Services\Compute;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\TickerOhlcDailyRepository;
use App\Repositories\TickerIndicatorsDailyRepository;
use App\Trade\Compute\Classifiers\DecisionClassifier;
use App\Trade\Compute\Classifiers\PatternClassifier;
use App\Trade\Compute\Classifiers\VolumeLabelClassifier;
use App\Trade\Compute\Rolling\RollingAtrWilder;
use App\Trade\Compute\Rolling\RollingRsiWilder;
use App\Trade\Compute\Rolling\RollingSma;
use App\Trade\Compute\SignalAgeTracker;
use App\Trade\Support\TradeClock;
use App\Trade\Support\TradePerf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use DateTimeInterface;

class ComputeEodService
{
    private MarketCalendarRepository $cal;
    private TickerOhlcDailyRepository $ohlc;
    private TickerIndicatorsDailyRepository $ind;

    private DecisionClassifier $decision;
    private VolumeLabelClassifier $volume;
    private PatternClassifier $pattern;
    private SignalAgeTracker $age;

    private EodDateResolver $dateResolver;

    public function __construct(
        MarketCalendarRepository $cal,
        TickerOhlcDailyRepository $ohlc,
        TickerIndicatorsDailyRepository $ind,
        DecisionClassifier $decision,
        VolumeLabelClassifier $volume,
        PatternClassifier $pattern,
        SignalAgeTracker $age,
        EodDateResolver $dateResolver
    ) {
        $this->cal = $cal;
        $this->ohlc = $ohlc;
        $this->ind = $ind;

        $this->decision = $decision;
        $this->volume = $volume;
        $this->pattern = $pattern;
        $this->age = $age;

        $this->dateResolver = $dateResolver;
    }

    public function runDate(?string $tradeDate = null, ?string $tickerCode = null, int $chunkSize = 200): array
    {
        $tz = TradeClock::tz();

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

        $startDate = Carbon::parse($date, $tz)
            ->subDays((int) config('trade.indicators.lookback_days', 260) + 60)
            ->toDateString();

        // hanya ticker yang punya OHLC pada $date
        $tickerIds = $this->ohlc->getTickerIdsHavingRowOnDate($date, $tickerCode);

        if (empty($tickerIds)) {
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

        DB::disableQueryLog();

        $upsertBatchSize = (int) config('trade.compute_eod.upsert_batch_size', 500);
        if ($upsertBatchSize <= 0) $upsertBatchSize = 500;

        $chunks = array_chunk($tickerIds, max(1, $chunkSize));

        foreach ($chunks as $ids) {
            // preload prev snapshot (1 query per chunk)
            $prevSnaps = [];
            if ($prev) {
                $prevSnaps = $this->ind->getPrevSnapshotMany($prev, $ids);
            }

            $cursor = $this->ohlc->cursorHistoryRange($startDate, $date, $ids);

            $curTicker = null;

            $sma20 = $sma50 = $sma200 = null;
            $rsi14 = null;
            $atr14 = null;

            $lows20 = [];
            $highs20 = [];

            $lastVols20 = [];
            $sumVol20 = 0.0;

            $seenOnDate = [];
            $rowsBuffer = [];

            foreach ($cursor as $r) {
                $tid = (int) $r->ticker_id;

                if ($curTicker !== $tid) {
                    $curTicker = $tid;

                    $sma20 = new RollingSma(20);
                    $sma50 = new RollingSma(50);
                    $sma200 = new RollingSma(200);
                    $rsi14 = new RollingRsiWilder(14);
                    $atr14 = new RollingAtrWilder(14);

                    $lows20 = [];
                    $highs20 = [];

                    $lastVols20 = [];
                    $sumVol20 = 0.0;
                }

                // support/resistance exclude today: hitung dari buffer sebelum push bar hari ini
                $support20 = null;
                $resist20 = null;
                if (count($lows20) >= 20)  $support20 = min($lows20);
                if (count($highs20) >= 20) $resist20  = max($highs20);

                // volSma20Prev (exclude today)
                $volSma20Prev = null;
                if (count($lastVols20) >= 20) $volSma20Prev = ($sumVol20 / 20.0);

                // bar today
                $close = (float) $r->close;
                $high  = (float) $r->high;
                $low   = (float) $r->low;
                $vol   = (float) $r->volume;

                // update rolling engines
                $sma20->push($close);
                $sma50->push($close);
                $sma200->push($close);
                $rsi14->push($close);
                $atr14->push($high, $low, $close);

                // update buffers
                $lows20[] = $low;   if (count($lows20) > 20) array_shift($lows20);
                $highs20[] = $high; if (count($highs20) > 20) array_shift($highs20);

                $lastVols20[] = $vol;
                $sumVol20 += $vol;
                if (count($lastVols20) > 20) {
                    $out = array_shift($lastVols20);
                    $sumVol20 -= $out;
                }

                // hanya upsert row yang trade_date == $date
                $rowDate = $this->toDateString($r->trade_date);
                if ($rowDate !== $date) {
                    continue;
                }

                $volRatio = null;
                if ($volSma20Prev !== null && $volSma20Prev > 0) {
                    $volRatio = round($vol / $volSma20Prev, 4);
                }

                $metrics = [
                    'open' => (float) $r->open,
                    'high' => $high,
                    'low'  => $low,
                    'close'=> $close,
                    'volume' => $vol,

                    'ma20' => $sma20->value(),
                    'ma50' => $sma50->value(),
                    'ma200'=> $sma200->value(),
                    'rsi14'=> $rsi14->value(),
                    'atr14'=> $atr14->value(),

                    'support_20d' => $support20,
                    'resistance_20d' => $resist20,

                    'vol_sma20' => $volSma20Prev,
                    'vol_ratio' => $volRatio,
                ];

                $signalCode = $this->pattern->classify($metrics);
                $volumeLabelCode = $this->volume->classify($volRatio);

                // inject context untuk decision
                $metrics['signal_code'] = $signalCode;
                $metrics['volume_label'] = $volumeLabelCode;

                $decisionCode = $this->decision->classify($metrics);

                $prevSnap = $prev ? ($prevSnaps[$tid] ?? null) : null;
                $age = $this->age->computeFromPrev($tid, $date, $signalCode, $prevSnap);

                $row = [
                    'ticker_id' => $tid,
                    'trade_date' => $date,

                    'open' => (float) $r->open,
                    'high' => $high,
                    'low' => $low,
                    'close' => $close,
                    'volume' => (int) $r->volume,

                    'ma20' => $metrics['ma20'] !== null ? round($metrics['ma20'], 4) : null,
                    'ma50' => $metrics['ma50'] !== null ? round($metrics['ma50'], 4) : null,
                    'ma200'=> $metrics['ma200'] !== null ? round($metrics['ma200'], 4) : null,

                    'rsi14' => $metrics['rsi14'] !== null ? round($metrics['rsi14'], 4) : null,
                    'atr14' => $metrics['atr14'] !== null ? round($metrics['atr14'], 4) : null,

                    'support_20d' => $support20 !== null ? round($support20, 4) : null,
                    'resistance_20d' => $resist20 !== null ? round($resist20, 4) : null,

                    'vol_sma20' => $volSma20Prev !== null ? round($volSma20Prev, 4) : null,
                    'vol_ratio' => $volRatio,

                    'decision_code' => $decisionCode,
                    'signal_code' => $signalCode,
                    'volume_label_code' => $volumeLabelCode,

                    'signal_first_seen_date' => $age['signal_first_seen_date'],
                    'signal_age_days' => $age['signal_age_days'],

                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $seenOnDate[$tid] = true;

                $rowsBuffer[] = $row;
                $processed++;

                if (count($rowsBuffer) >= $upsertBatchSize) {
                    $this->ind->upsertMany($rowsBuffer, $upsertBatchSize);
                    $rowsBuffer = [];
                }
            }

            if (!empty($rowsBuffer)) {
                $this->ind->upsertMany($rowsBuffer, $upsertBatchSize);
                $rowsBuffer = [];
            }

            // diagnostic: harusnya 0 karena ids dipilih dari "having row on date"
            $skippedNoRow = 0;
            foreach ($ids as $tid) {
                if (empty($seenOnDate[$tid])) $skippedNoRow++;
            }
            $totalSkippedNoRow += $skippedNoRow;
        }

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
        ];
    }

    private function toDateString($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // kalau string datetime, ambil 10 char pertama
        $s = (string) $value;
        if (strlen($s) >= 10) {
            return substr($s, 0, 10);
        }

        // fallback
        return $s;
    }
}
