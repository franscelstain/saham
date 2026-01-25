<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Entry band (low/high) for limit orders.
 * PHP 7.3 compatible.
 */
class EntryBandDto
{
    /** @var int|null */
    public $low;
    /** @var int|null */
    public $high;

    public function __construct($low, $high)
    {
        $this->low = ($low === null) ? null : (int)$low;
        $this->high = ($high === null) ? null : (int)$high;
    }

    /**
     * @param array<string,mixed> $a
     * @return self
     */
    public static function fromArray(array $a)
    {
        $low = (isset($a['low']) && is_numeric($a['low'])) ? (int)$a['low'] : null;
        $high = (isset($a['high']) && is_numeric($a['high'])) ? (int)$a['high'] : null;
        return new self($low, $high);
    }

    /**
     * @return array{low:?int,high:?int}
     */
    public function toArray()
    {
        return ['low' => $this->low, 'high' => $this->high];
    }
}
