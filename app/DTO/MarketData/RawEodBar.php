<?php

namespace App\DTO\MarketData;

/**
 * RawEodBar
 *
 * DTO untuk row md_raw_eod (read model). Digunakan agar repository tidak
 * me-return array bebas.
 */
final class RawEodBar
{
    /** @var int */
    public $runId;
    /** @var int */
    public $tickerId;
    /** @var string */
    public $tradeDate;
    /** @var string */
    public $source;
    /** @var ?float */
    public $open;
    /** @var ?float */
    public $high;
    /** @var ?float */
    public $low;
    /** @var ?float */
    public $close;
    /** @var ?float */
    public $adjClose;
    /** @var ?int */
    public $volume;

    /** @var ?string */
    public $errorCode;
    /** @var ?string */
    public $errorMsg;

    public static function fromObject(object $r): self
    {
        $o = new self();
        $o->runId = (int) ($r->run_id ?? 0);
        $o->tickerId = (int) ($r->ticker_id ?? 0);
        $o->tradeDate = (string) ($r->trade_date ?? '');
        $o->source = (string) ($r->source ?? '');
        $o->open = $r->open !== null ? (float) $r->open : null;
        $o->high = $r->high !== null ? (float) $r->high : null;
        $o->low = $r->low !== null ? (float) $r->low : null;
        $o->close = $r->close !== null ? (float) $r->close : null;
        $o->adjClose = $r->adj_close !== null ? (float) $r->adj_close : null;
        $o->volume = $r->volume !== null ? (int) $r->volume : null;
        $o->errorCode = $r->error_code !== null ? (string) $r->error_code : null;
        $o->errorMsg = $r->error_msg !== null ? (string) $r->error_msg : null;
        return $o;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'ticker_id' => $this->tickerId,
            'trade_date' => $this->tradeDate,
            'source' => $this->source,
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'adj_close' => $this->adjClose,
            'volume' => $this->volume,
            'error_code' => $this->errorCode,
            'error_msg' => $this->errorMsg,
        ];
    }
}
