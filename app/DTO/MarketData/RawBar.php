<?php

namespace App\DTO\MarketData;

final class RawBar
{
    /** @var string */
    public $source;

    /** @var string */
    public $sourceSymbol;

    /** @var int|null unix seconds */
    public $epoch;

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

    /** @var string|null */
    public $errorCode;

    /** @var string|null */
    public $errorMsg;

    public function __construct(
        string $source,
        string $sourceSymbol,
        ?int $epoch,
        ?float $open,
        ?float $high,
        ?float $low,
        ?float $close,
        ?float $adjClose,
        ?int $volume,
        ?string $errorCode = null,
        ?string $errorMsg = null
    ) {
        $this->source = $source;
        $this->sourceSymbol = $sourceSymbol;
        $this->epoch = $epoch;
        $this->open = $open;
        $this->high = $high;
        $this->low = $low;
        $this->close = $close;
        $this->adjClose = $adjClose;
        $this->volume = $volume;
        $this->errorCode = $errorCode;
        $this->errorMsg = $errorMsg;
    }
}
