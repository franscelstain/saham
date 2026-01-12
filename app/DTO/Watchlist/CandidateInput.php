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

    // indicators
    public float $ma20;
    public float $ma50;
    public float $ma200;
    public float $rsi;

    public ?float $atr14 = null;
    public ?float $support20 = null;
    public ?float $resistance20 = null;

    public int $scoreTotal;

    public int $volume;
    public float $valueEst;

    // expiry
    public ?string $signalFirstSeenDate = null; // 'YYYY-MM-DD'
    public ?int $signalAgeDays = null;

    // labels
    public string $decisionLabel;
    public string $signalLabel;
    public string $volumeLabel;

    public string $tradeDate;

    // raw codes (berguna buat expiry/ranking tanpa parsing label)
    public int $decisionCode;
    public int $signalCode;
    public int $volumeLabelCode;

    public function __construct(array $row)
    {
        $this->tickerId             = (int) $row['ticker_id'];
        $this->code                 = $row['ticker_code'];
        $this->name                 = $row['company_name'];

        $this->open                 = isset($row['open']) ? (float) $row['open'] : null;
        $this->high                 = isset($row['high']) ? (float) $row['high'] : null;
        $this->low                  = isset($row['low']) ? (float) $row['low'] : null;
        $this->close                = (float) $row['close'];
        
        $this->ma20                 = (float) $row['ma20'];
        $this->ma50                 = (float) $row['ma50'];
        $this->ma200                = (float) $row['ma200'];
        $this->rsi                  = (float) $row['rsi14'];

        $this->atr14                = isset($row['atr14']) ? (float) $row['atr14'] : null;
        $this->support20            = isset($row['support_20d']) ? (float) $row['support_20d'] : null;
        $this->resistance20         = isset($row['resistance_20d']) ? (float) $row['resistance_20d'] : null;

        $this->scoreTotal           = (int) $row['score_total'];
        $this->valueEst             = (float) $row['value_est'];
        $this->volume               = (int) $row['volume'];

        $this->decisionCode         = (int) $row['decision_code'];
        $this->signalCode           = (int) $row['signal_code'];
        $this->volumeLabelCode      = (int) $row['volume_label_code'];
        
        $this->volumeLabel          = LabelCatalog::volumeLabel($this->volumeLabelCode);
        $this->signalLabel          = LabelCatalog::signalLabel($this->signalCode);
        $this->decisionLabel        = LabelCatalog::decisionLabel($this->decisionCode);

        $this->signalFirstSeenDate  = isset($row['signal_first_seen_date']) ? (string) $row['signal_first_seen_date'] : null;
        $this->signalAgeDays        = isset($row['signal_age_days']) ? (int) $row['signal_age_days'] : null;

        $this->tradeDate            = (string) $row['trade_date'];
    }
}
