<?php

namespace App\DTO\MarketData;

/**
 * CanonicalEodBar
 *
 * DTO untuk row md_canonical_eod.
 */
final class CanonicalEodBar
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
    public $close;
    /** @var ?float */
    public $adjClose;
    /** @var ?int */
    public $volume;
    /** @var ?string */
    public $priceBasis;
    /** @var ?float */
    public $priceUsed;
    /** @var ?string */
    public $caEvent;

    public static function fromObject(object $r): self
    {
        $o = new self();
        $o->runId = (int) ($r->run_id ?? 0);
        $o->tickerId = (int) ($r->ticker_id ?? 0);
        $o->tradeDate = (string) ($r->trade_date ?? '');
        // column name varies: canonical table uses chosen_source
        $o->source = (string) ($r->source ?? ($r->chosen_source ?? ''));
        $o->close = $r->close !== null ? (float) $r->close : null;
        $o->adjClose = $r->adj_close !== null ? (float) $r->adj_close : null;
        $o->volume = $r->volume !== null ? (int) $r->volume : null;
        $o->priceBasis = $r->price_basis !== null ? (string) $r->price_basis : null;
        $o->priceUsed = $r->price_used !== null ? (float) $r->price_used : null;
        $o->caEvent = $r->ca_event !== null ? (string) $r->ca_event : null;
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
            'close' => $this->close,
            'adj_close' => $this->adjClose,
            'volume' => $this->volume,
            'price_basis' => $this->priceBasis,
            'price_used' => $this->priceUsed,
            'ca_event' => $this->caEvent,
        ];
    }
}
