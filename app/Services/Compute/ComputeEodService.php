<?php

namespace App\Services\Compute;

use Carbon\Carbon;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\TickerOhlcDailyRepository;
use App\Repositories\TickerIndicatorsDailyRepository;
use App\Trade\Compute\Indicators\IndicatorCalculator;
use App\Trade\Compute\Classifiers\DecisionClassifier;
use App\Trade\Compute\Classifiers\VolumeLabelClassifier;
use App\Trade\Compute\Classifiers\PatternClassifier;
use App\Trade\Compute\SignalAgeTracker;

class ComputeEodService
{
    protected $cal;
    protected $ohlc;
    protected $ind;
    protected $calc;
    protected $decision;
    protected $volume;
    protected $pattern;
    protected $age;

    public function __construct(
        MarketCalendarRepository $cal,
        TickerOhlcDailyRepository $ohlc,
        TickerIndicatorsDailyRepository $ind,
        IndicatorCalculator $calc,
        DecisionClassifier $decision,
        VolumeLabelClassifier $volume,
        PatternClassifier $pattern,
        SignalAgeTracker $age
    ) {
        $this->cal = $cal;
        $this->ohlc = $ohlc;
        $this->ind = $ind;
        $this->calc = $calc;
        $this->decision = $decision;
        $this->volume = $volume;
        $this->pattern = $pattern;
        $this->age = $age;
    }

    public function run(?string $tradeDate = null, ?string $tickerCode = null): array
    {
        $date = $tradeDate ?: $this->cal->latestTradingDate();

        if (!$date) {
            return ['status' => 'error', 'message' => 'market_calendar kosong / tidak ada trading day'];
        }

        if (!$this->cal->isTradingDay($date)) {
            return [
                'status' => 'skipped',
                'trade_date' => $date,
                'reason' => 'holiday (is_trading_day=0)'
            ];
        }

        $prev = $this->cal->previousTradingDate($date);

        $lookback = (int) config('trade.compute.lookback_days', 260);
        $startDate = Carbon::parse($date)->subDays($lookback + 60)->toDateString();

        $hist = $this->ohlc->getHistoryRange($startDate, $date);

        // group by ticker
        $byTicker = [];
        foreach ($hist as $r) {
            $tid = (int) $r->ticker_id;
            if (!isset($byTicker[$tid])) $byTicker[$tid] = [];
            $byTicker[$tid][] = $r;
        }

        $processed = 0;
        $skipped = 0;

        foreach ($byTicker as $tickerId => $rows) {
            // cari row hari ini
            $today = null;
            foreach ($rows as $r) {
                if ($r->trade_date === $date) { $today = $r; break; }
            }
            if (!$today) { $skipped++; continue; }

            // arrays
            $highs = []; $lows = []; $closes = []; $vols = [];
            foreach ($rows as $r) {
                $highs[] = (float)$r->high;
                $lows[] = (float)$r->low;
                $closes[] = (float)$r->close;
                $vols[] = (float)$r->volume;
            }

            $ma20  = $this->calc->sma($closes, 20);
            $ma50  = $this->calc->sma($closes, 50);
            $ma200 = $this->calc->sma($closes, 200);

            $rsi14 = $this->calc->rsiWilder($closes, 14);
            $atr14 = $this->calc->atrWilder($highs, $lows, $closes, 14);

            $support20 = $this->calc->rollingMinExcludeToday($lows, 20);
            $resist20  = $this->calc->rollingMaxExcludeToday($highs, 20);

            $volSma20Prev = $this->calc->smaExcludeToday($vols, 20);

            $volRatio = null;
            if ($volSma20Prev !== null && $volSma20Prev > 0) {
                $volRatio = round(((float)$today->volume) / $volSma20Prev, 4);
            }

            $metrics = [
                'open' => (float)$today->open,
                'high' => (float)$today->high,
                'low'  => (float)$today->low,
                'close'=> (float)$today->close,
                'volume' => (float)$today->volume,

                'ma20' => $ma20,
                'ma50' => $ma50,
                'ma200'=> $ma200,
                'rsi14'=> $rsi14,
                'atr14'=> $atr14,

                'support_20d' => $support20,
                'resistance_20d' => $resist20,

                'vol_sma20' => $volSma20Prev,
                'vol_ratio' => $volRatio,
            ];

            $decisionCode = $this->decision->classify($metrics);
            $volumeLabelCode = $this->volume->classify($volRatio);
            $patternCode = $this->pattern->classify($metrics); // null

            $prevSnap = ($prev) ? $this->ind->getPrevSnapshot($tickerId, $prev) : null;
            $age = $this->age->computeDecisionAge($decisionCode, $prevSnap, $date, $prev);

            // upsert
            $row = [
                'ticker_id' => $tickerId,
                'trade_date' => $date,

                'open' => (float)$today->open,
                'high' => (float)$today->high,
                'low' => (float)$today->low,
                'close' => (float)$today->close,
                'volume' => (int)$today->volume,

                'ma20' => $ma20 !== null ? round($ma20, 4) : null,
                'ma50' => $ma50 !== null ? round($ma50, 4) : null,
                'ma200'=> $ma200 !== null ? round($ma200, 4) : null,

                'rsi14' => $rsi14 !== null ? round($rsi14, 4) : null,
                'atr14' => $atr14 !== null ? round($atr14, 4) : null,

                'support_20d' => $support20 !== null ? round($support20, 4) : null,
                'resistance_20d' => $resist20 !== null ? round($resist20, 4) : null,

                'vol_sma20' => $volSma20Prev !== null ? round($volSma20Prev, 4) : null,
                'vol_ratio' => $volRatio,

                'decision_code' => $decisionCode,
                'signal_code' => $patternCode,
                'volume_label_code' => $volumeLabelCode,

                'signal_first_seen_date' => $age['signal_first_seen_date'],
                'signal_age_days' => $age['signal_age_days'],

                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->ind->upsert($row);

            $processed++;
        }

        return [
            'status' => 'ok',
            'trade_date' => $date,
            'prev_trade_date' => $prev,
            'start_date' => $startDate,
            'tickers' => count($byTicker),
            'processed' => $processed,
            'skipped' => $skipped,
        ];
    }
}
