<?php

namespace App\DTO\Watchlist;

use App\Trade\Explain\LabelCatalog;

class CandidateInput
{
    public int $tickerId;
    public string $code;
    public string $name;

    // OHLC
    public ?float $open = null;
    public ?float $high = null;
    public ?float $low = null;
    public float $close;
    public ?int $volume = null;

    // prev candle (for gap + candle flags)
    public ?float $prevOpen = null;
    public ?float $prevHigh = null;
    public ?float $prevLow = null;
    public ?float $prevClose = null;

    // indicators
    public float $ma20;
    public float $ma50;
    public float $ma200;
    public float $rsi;
    public ?float $volSma20 = null;
    public ?float $volRatio = null;
    public ?float $atr14 = null;
    public ?float $support20d = null;
    public ?float $resistance20d = null;

    // derived proxies
    public ?float $valueEst = null; // close*volume (today)
    public ?float $dv20 = null;     // SMA20(close*volume) over 20 prior trading days (exclude today)
    public ?string $liqBucket = null; // A/B/C/U

    // decision / signal
    public int $decisionCode;
    public int $signalCode;
    public int $volumeLabelCode;

    public string $decisionLabel;
    public string $signalLabel;
    public string $volumeLabel;

    // expiry
    public ?string $signalFirstSeenDate = null;
    public ?int $signalAgeDays = null;

    // date
    public string $tradeDate;

    // candle structure ratios (0..1)
    public ?float $candleBodyPct = null;
    public ?float $candleUpperWickPct = null;
    public ?float $candleLowerWickPct = null;
    public ?bool $isInsideDay = null;
    public ?string $engulfingType = null; // bull|bear|null
    public ?bool $isLongUpperWick = null;
    public ?bool $isLongLowerWick = null;

    public function __construct(array $row)
    {
        $this->tickerId = (int)($row['ticker_id'] ?? 0);
        $this->code = (string)($row['ticker_code'] ?? '');
        $this->name = (string)($row['company_name'] ?? '');

        $this->open = isset($row['open']) && is_numeric($row['open']) ? (float)$row['open'] : null;
        $this->high = isset($row['high']) && is_numeric($row['high']) ? (float)$row['high'] : null;
        $this->low  = isset($row['low']) && is_numeric($row['low']) ? (float)$row['low'] : null;
        $this->close = isset($row['close']) && is_numeric($row['close']) ? (float)$row['close'] : 0.0;
        $this->volume = isset($row['volume']) && is_numeric($row['volume']) ? (int)$row['volume'] : null;

        $this->prevOpen = isset($row['prev_open']) && is_numeric($row['prev_open']) ? (float)$row['prev_open'] : null;
        $this->prevHigh = isset($row['prev_high']) && is_numeric($row['prev_high']) ? (float)$row['prev_high'] : null;
        $this->prevLow  = isset($row['prev_low']) && is_numeric($row['prev_low']) ? (float)$row['prev_low'] : null;
        $this->prevClose = isset($row['prev_close']) && is_numeric($row['prev_close']) ? (float)$row['prev_close'] : null;

        $this->ma20 = (float)($row['ma20'] ?? 0);
        $this->ma50 = (float)($row['ma50'] ?? 0);
        $this->ma200 = (float)($row['ma200'] ?? 0);
        $this->rsi = (float)($row['rsi14'] ?? 0);
        $this->volSma20 = isset($row['vol_sma20']) && is_numeric($row['vol_sma20']) ? (float)$row['vol_sma20'] : null;
        $this->volRatio = isset($row['vol_ratio']) && is_numeric($row['vol_ratio']) ? (float)$row['vol_ratio'] : null;
        $this->atr14 = isset($row['atr14']) && is_numeric($row['atr14']) ? (float)$row['atr14'] : null;
        $this->support20d = isset($row['support_20d']) && is_numeric($row['support_20d']) ? (float)$row['support_20d'] : null;
        $this->resistance20d = isset($row['resistance_20d']) && is_numeric($row['resistance_20d']) ? (float)$row['resistance_20d'] : null;

        $this->valueEst = isset($row['value_est']) && is_numeric($row['value_est']) ? (float)$row['value_est'] : null;
        $this->dv20 = isset($row['dv20']) && is_numeric($row['dv20']) ? (float)$row['dv20'] : null;
        $this->liqBucket = isset($row['liq_bucket']) ? (string)$row['liq_bucket'] : null;

        $this->decisionCode = (int)($row['decision_code'] ?? 0);
        $this->signalCode = (int)($row['signal_code'] ?? 0);
        $this->volumeLabelCode = (int)($row['volume_label_code'] ?? 0);

        $this->decisionLabel = LabelCatalog::decision($this->decisionCode);
        $this->signalLabel = LabelCatalog::signal($this->signalCode);
        $this->volumeLabel = LabelCatalog::volumeLabel($this->volumeLabelCode);

        $this->signalFirstSeenDate = !empty($row['signal_first_seen_date']) ? (string)$row['signal_first_seen_date'] : null;
        $this->signalAgeDays = isset($row['signal_age_days']) ? (int)$row['signal_age_days'] : null;

        $this->tradeDate = (string)($row['trade_date'] ?? '');

        $this->computeCandleFlags();
    }

    private function computeCandleFlags(): void
    {
        if ($this->open === null || $this->high === null || $this->low === null) return;
        $range = $this->high - $this->low;
        if ($range <= 0) return;

        $o = $this->open;
        $c = $this->close;
        $h = $this->high;
        $l = $this->low;

        $body = abs($c - $o) / $range;
        $upper = ($h - max($o, $c)) / $range;
        $lower = (min($o, $c) - $l) / $range;

        $this->candleBodyPct = round($body, 4);
        $this->candleUpperWickPct = round($upper, 4);
        $this->candleLowerWickPct = round($lower, 4);

        $thr = (float) config('trade.watchlist.candle.long_wick_pct', 0.55);
        $this->isLongUpperWick = ($this->candleUpperWickPct !== null) ? ($this->candleUpperWickPct >= $thr) : null;
        $this->isLongLowerWick = ($this->candleLowerWickPct !== null) ? ($this->candleLowerWickPct >= $thr) : null;

        if ($this->prevHigh !== null && $this->prevLow !== null) {
            $this->isInsideDay = ($h <= $this->prevHigh) && ($l >= $this->prevLow);
        }

        // engulfing
        if ($this->prevOpen !== null && $this->prevClose !== null) {
            $po = $this->prevOpen;
            $pc = $this->prevClose;

            $bull = ($c > $o) && ($pc < $po) && ($c >= $po) && ($o <= $pc);
            $bear = ($c < $o) && ($pc > $po) && ($c <= $po) && ($o >= $pc);

            if ($bull) $this->engulfingType = 'bull';
            elseif ($bear) $this->engulfingType = 'bear';
        }
    }
}
