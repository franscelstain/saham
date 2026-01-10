<?php

namespace App\DTO\Watchlist;

use App\Trade\Explain\ReasonCatalog;

class CandidateInput
{
    public int $tickerId;
    public string $code;
    public string $name;

    public float $close;
    public float $ma20;
    public float $ma50;
    public float $ma200;
    public float $rsi;

    public int $scoreTotal;

    public int $volume;
    public float $valueEst;

    public string $decisionLabel;
    public string $signalLabel;
    public string $volumeLabel;
    public string $tradeDate;

    public function __construct(array $row)
    {
        $this->tickerId     = (int) $row['ticker_id'];
        $this->code         = $row['ticker_code'];
        $this->name         = $row['company_name'];
        $this->close        = (float) $row['close'];
        $this->ma20         = (float) $row['ma20'];
        $this->ma50         = (float) $row['ma50'];
        $this->ma200        = (float) $row['ma200'];
        $this->rsi          = (float) $row['rsi14'];
        $this->scoreTotal   = (int) $row['score_total'];
        $this->valueEst     = (float) $row['value_est'];
        $this->volume       = (int) $row['volume'];
        $this->volumeLabel  = ReasonCatalog::volumeLabel((int) $row['volume_label_code']);
        $this->signalLabel  = ReasonCatalog::signalLabel((int) $row['signal_code']);
        $this->decisionLabel= ReasonCatalog::decisionLabel((int) $row['decision_code']);
        $this->tradeDate    = (string) $row['trade_date'];
    }
}
