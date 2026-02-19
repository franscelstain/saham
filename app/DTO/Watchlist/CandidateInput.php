<?php

namespace App\DTO\Watchlist;

use App\DTO\BaseDto;

class CandidateInput extends BaseDto
{
    private int $tickerId;
    private string $tickerCode;

    // EOD OHLC
    private ?float $open;
    private ?float $high;
    private ?float $low;
    private ?float $close;
    private ?float $volume;

    // Previous close (for gap)
    private ?float $prevClose;
    private ?float $prevOpen;
    private ?float $prevHigh;
    private ?float $prevLow;

    // Indicators - classification + score (docs/watchlist)
    private ?float $scoreTotal;
    private ?int $decisionCode;
    private ?int $signalCode;
    private ?int $volumeLabelCode;
    private ?int $signalAgeDays;
    private ?float $volSma20;

    // Indicators
    private ?float $ma20;
    private ?float $ma50;
    private ?float $ma200;
    private ?float $rsi14;
    private ?float $atr14;
    private ?float $volRatio;
    private ?float $support20d;
    private ?float $resistance20d;

    // Liquidity (IDR)
    private ?float $dv20;
    private ?float $turnover20;

    // Weekly Swing helpers (computed from OHLC before trade_date)
    private ?float $hh20;
    private ?float $ll5;
    private ?float $roc20;

    // Intraday Light helpers (computed from OHLC before trade_date)
    private ?float $hh10;
    private ?float $ll3;
    private ?float $roc5;

    // Classification outputs
    private ?string $liqBucket;
    private ?array $candle;

    public function __construct(array $data = [])
    {
        $this->tickerId = (int)($data['ticker_id'] ?? 0);
        $this->tickerCode = (string)($data['ticker_code'] ?? '');

        $this->open = isset($data['open']) && is_numeric($data['open']) ? (float)$data['open'] : null;
        $this->high = isset($data['high']) && is_numeric($data['high']) ? (float)$data['high'] : null;
        $this->low = isset($data['low']) && is_numeric($data['low']) ? (float)$data['low'] : null;
        $this->close = isset($data['close']) && is_numeric($data['close']) ? (float)$data['close'] : null;
        $this->volume = isset($data['volume']) && is_numeric($data['volume']) ? (float)$data['volume'] : null;

        $this->prevOpen = isset($data['prev_open']) && is_numeric($data['prev_open']) ? (float)$data['prev_open'] : null;
        $this->prevHigh = isset($data['prev_high']) && is_numeric($data['prev_high']) ? (float)$data['prev_high'] : null;
        $this->prevLow  = isset($data['prev_low']) && is_numeric($data['prev_low']) ? (float)$data['prev_low'] : null;
        $this->prevClose = isset($data['prev_close']) && is_numeric($data['prev_close']) ? (float)$data['prev_close'] : null;

        $this->scoreTotal = isset($data['score_total']) && is_numeric($data['score_total']) ? (float)$data['score_total'] : null;
        $this->decisionCode = isset($data['decision_code']) && is_numeric($data['decision_code']) ? (int)$data['decision_code'] : null;
        $this->signalCode = isset($data['signal_code']) && is_numeric($data['signal_code']) ? (int)$data['signal_code'] : null;
        $this->volumeLabelCode = isset($data['volume_label_code']) && is_numeric($data['volume_label_code']) ? (int)$data['volume_label_code'] : null;
        $this->signalAgeDays = isset($data['signal_age_days']) && is_numeric($data['signal_age_days']) ? (int)$data['signal_age_days'] : null;
        $this->volSma20 = isset($data['vol_sma20']) && is_numeric($data['vol_sma20']) ? (float)$data['vol_sma20'] : null;

        $this->ma20 = isset($data['ma20']) && is_numeric($data['ma20']) ? (float)$data['ma20'] : null;
        $this->ma50 = isset($data['ma50']) && is_numeric($data['ma50']) ? (float)$data['ma50'] : null;
        $this->ma200 = isset($data['ma200']) && is_numeric($data['ma200']) ? (float)$data['ma200'] : null;
        $this->rsi14 = isset($data['rsi14']) && is_numeric($data['rsi14']) ? (float)$data['rsi14'] : null;
        $this->atr14 = isset($data['atr14']) && is_numeric($data['atr14']) ? (float)$data['atr14'] : null;
        $this->volRatio = isset($data['vol_ratio']) && is_numeric($data['vol_ratio']) ? (float)$data['vol_ratio'] : null;

        $this->support20d = isset($data['support_20d']) && is_numeric($data['support_20d']) ? (float)$data['support_20d'] : null;
        $this->resistance20d = isset($data['resistance_20d']) && is_numeric($data['resistance_20d']) ? (float)$data['resistance_20d'] : null;

        // Repo may provide dv20 or dv20_idr (legacy naming).
        if (isset($data['dv20']) && is_numeric($data['dv20'])) $this->dv20 = (float)$data['dv20'];
        elseif (isset($data['dv20_idr']) && is_numeric($data['dv20_idr'])) $this->dv20 = (float)$data['dv20_idr'];
        else $this->dv20 = null;

        // Repo may provide turnover20 or turnover20_idr (same scale IDR) as fallback liquidity metric.
        if (isset($data['turnover20']) && is_numeric($data['turnover20'])) $this->turnover20 = (float)$data['turnover20'];
        elseif (isset($data['turnover20_idr']) && is_numeric($data['turnover20_idr'])) $this->turnover20 = (float)$data['turnover20_idr'];
        else $this->turnover20 = null;

        $this->hh20 = isset($data['hh20']) && is_numeric($data['hh20']) ? (float)$data['hh20'] : null;
        $this->ll5 = isset($data['ll5']) && is_numeric($data['ll5']) ? (float)$data['ll5'] : null;
        $this->roc20 = isset($data['roc20']) && is_numeric($data['roc20']) ? (float)$data['roc20'] : null;

        $this->hh10 = isset($data['hh10']) && is_numeric($data['hh10']) ? (float)$data['hh10'] : null;
        $this->ll3  = isset($data['ll3']) && is_numeric($data['ll3']) ? (float)$data['ll3'] : null;
        $this->roc5 = isset($data['roc5']) && is_numeric($data['roc5']) ? (float)$data['roc5'] : null;

        $this->liqBucket = isset($data['liq_bucket']) && is_string($data['liq_bucket']) ? (string)$data['liq_bucket'] : null;
        $this->candle = isset($data['candle']) && is_array($data['candle']) ? $data['candle'] : null;
    }

    public function toArray(): array
    {
        return [
            'ticker_id' => $this->tickerId,
            'ticker_code' => $this->tickerCode,
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'volume' => $this->volume,
            'prev_open' => $this->prevOpen,
            'prev_high' => $this->prevHigh,
            'prev_low' => $this->prevLow,
            'prev_close' => $this->prevClose,
            'score_total' => $this->scoreTotal,
            'decision_code' => $this->decisionCode,
            'signal_code' => $this->signalCode,
            'volume_label_code' => $this->volumeLabelCode,
            'signal_age_days' => $this->signalAgeDays,
            'ma20' => $this->ma20,
            'ma50' => $this->ma50,
            'ma200' => $this->ma200,
            'rsi14' => $this->rsi14,
            'atr14' => $this->atr14,
            'vol_sma20' => $this->volSma20,
            'vol_ratio' => $this->volRatio,
            'support_20d' => $this->support20d,
            'resistance_20d' => $this->resistance20d,
            'dv20' => $this->dv20,
            'turnover20' => $this->turnover20,
            'hh20' => $this->hh20,
            'll5' => $this->ll5,
            'roc20' => $this->roc20,
            'hh10' => $this->hh10,
            'll3' => $this->ll3,
            'roc5' => $this->roc5,
            'liq_bucket' => $this->liqBucket,
            'candle' => $this->candle,
        ];
    }
}
