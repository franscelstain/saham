<?php

namespace App\DTO\Watchlist\Scorecard;

class EntryBandDto
{
    public function __construct(
        public ?int $low,
        public ?int $high,
    ) {
    }

    /**
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $low = isset($a['low']) && is_numeric($a['low']) ? (int)$a['low'] : null;
        $high = isset($a['high']) && is_numeric($a['high']) ? (int)$a['high'] : null;
        return new self($low, $high);
    }

    /**
     * @return array{low:?int,high:?int}
     */
    public function toArray(): array
    {
        return ['low' => $this->low, 'high' => $this->high];
    }
}
