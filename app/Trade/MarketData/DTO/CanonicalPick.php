<?php

namespace App\Trade\MarketData\DTO;

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

    /** @var MdEodBar */
    public $bar;

    /**
     * @param string[] $flags
     */
    public function __construct(string $tradeDate, string $chosenSource, string $reason, array $flags, MdEodBar $bar)
    {
        $this->tradeDate = $tradeDate;
        $this->chosenSource = $chosenSource;
        $this->reason = $reason;
        $this->flags = $flags;
        $this->bar = $bar;
    }
}
