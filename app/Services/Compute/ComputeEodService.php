<?php

namespace App\Services\Compute;

use Carbon\Carbon;
use DateTimeInterface;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\TickerOhlcDailyRepository;
use App\Repositories\TickerIndicatorsDailyRepository;
use App\Trade\Compute\Classifiers\DecisionClassifier;
use App\Trade\Compute\Classifiers\VolumeLabelClassifier;
use App\Trade\Compute\Classifiers\PatternClassifier;
use App\Trade\Compute\SignalAgeTracker;
use App\Trade\Compute\Rolling\RollingSma;
use App\Trade\Compute\Rolling\RollingRsiWilder;
use App\Trade\Compute\Rolling\RollingAtrWilder;

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
        $requested = $tradeDate ? Carbon::parse($tradeDate)->toDateString() : null;

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

        $now = Carbon::now();
        $today = $now->toDateString();
        if ($date === $today && $this->dateResolver->isBeforeCutoff($now)) {
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

        $startDate = Carbon::parse($date)
            ->subDays((int) config('trade.compute.lookback_days', 260) + 60)
            ->toDateString();

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

        $totalSkippedNoRow = 0;
        $processed = 0;

        // timestamp sekali (hemat now() call di loop)
        $ts = now();

        $chunks = array_chunk($tickerIds, max(1, $chunkSize));
        foreach ($chunks as $ids) {

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

                // support/resistance (exclude today): nilai diambil dari buffer sebelum push bar hari ini
                $support20 = null;
                $resist20  = null;
                if (count($lows20) >= 20)  $support20 = min($lows20);
                if (count($highs20) >= 20) $resist20  = max($highs20);

                // volSma20Prev: avg 20 sebelum push volume hari ini
                $volSma20Prev = null;
                if (count($lastVols20) >= 20) $volSma20Prev = ($sumVol20 / 20.0);

                $close = (float) $r->close;
                $high  = (float) $r->high;
                $low   = (float) $r->low;
                $vol   = (float) $r->volume;

                $sma20->push($close);
                $sma50->push($close);
                $sma200->push($close);
                $rsi14->push($close);
                $atr14->push($high, $low, $close);

                $lows20[] = $low;
                if (count($lows20) > 20) array_shift($lows20);

                $highs20[] = $high;
                if (count($highs20) > 20) array_shift($highs20);

                $lastVols20[] = $vol;
                $sumVol20 += $vol;
                if (count($lastVols20) > 20) {
                    $out = array_shift($lastVols20);
                    $sumVol20 -= $out;
                }

                // proses hanya untuk row trade_date == $date
                $rowDate = $r->trade_date instanceof DateTimeInterface
                    ? $r->trade_date->format('Y-m-d')
                    : (string) $r->trade_date;

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

                $decisionCode = $this->decision->classify($metrics);
                $volumeLabelCode = $this->volume->classify($volRatio);
                $patternCode = $this->pattern->classify($metrics); // ini memang = signal_code

                $prevSnap = $prev ? ($prevSnaps[$tid] ?? null) : null;
                $age = $this->age->computeFromPrev($tid, $date, $patternCode, $prevSnap);

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
                    'signal_code' => $patternCode,
                    'volume_label_code' => $volumeLabelCode,

                    'signal_first_seen_date' => $age['signal_first_seen_date'],
                    'signal_age_days' => $age['signal_age_days'],

                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];

                $seenOnDate[$tid] = true;

                $this->ind->upsert($row);
                $processed++;
            }

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
}
