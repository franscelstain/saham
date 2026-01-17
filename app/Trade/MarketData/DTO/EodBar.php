<?php

namespace App\Trade\MarketData\DTO;

final class EodBar
{
    /** @var string */
    public $tradeDate; // YYYY-MM-DD (Asia/Jakarta)

    /** @var float|null */
    public $open;
    /** @var float|null */
    public $high;
    /** @var float|null */
    public $low;
    /** @var float|null */
    public $close;

    /** @var float|null */
    public $adjClose;

    /** @var int|null */
    public $volume;

    public function __construct(
        string $tradeDate,
        ?float $open,
        ?float $high,
        ?float $low,
        ?float $close,
        ?float $adjClose,
        ?int $volume
    ) {
        $this->tradeDate = $tradeDate;
        $this->open = $open;
        $this->high = $high;
        $this->low = $low;
        $this->close = $close;
        $this->adjClose = $adjClose;
        $this->volume = $volume;
    }
}
