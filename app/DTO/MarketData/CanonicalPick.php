<?php

namespace App\DTO\MarketData;

final class CanonicalPick
{
    /** @var string */
    public $tradeDate;

    /** @var string */
    public $chosenSource;

    /** @var string */
    public $reason;

    /** @var string[] */
    public $flags;

    /** @var EodBar */
    public $bar;

    /**
     * @param string[] $flags
     */
    public function __construct(string $tradeDate, string $chosenSource, string $reason, array $flags, EodBar $bar)
    {
        $this->tradeDate = $tradeDate;
        $this->chosenSource = $chosenSource;
        $this->reason = $reason;
        $this->flags = $flags;
        $this->bar = $bar;
    }
}
