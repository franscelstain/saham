<?php

namespace App\DTO\Watchlist\Scorecard;

class LiveTickerDto
{
    public function __construct(
        public string $ticker,
        public ?float $bid,
        public ?float $ask,
        public ?float $last,
        public ?float $open,
        public ?float $prevClose,
    ) {
    }

    /**
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $ticker = strtoupper(trim((string)($a['ticker'] ?? ($a['ticker_code'] ?? ''))));
        return new self(
            $ticker,
            self::toFloatOrNull($a['bid'] ?? null),
            self::toFloatOrNull($a['ask'] ?? null),
            self::toFloatOrNull($a['last'] ?? ($a['open_or_last'] ?? null)),
            self::toFloatOrNull($a['open'] ?? null),
            self::toFloatOrNull($a['prev_close'] ?? null),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'ticker' => $this->ticker,
            'bid' => $this->bid,
            'ask' => $this->ask,
            'last' => $this->last,
            'open' => $this->open,
            'prev_close' => $this->prevClose,
        ];
    }

    private static function toFloatOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_int($v) || is_float($v)) return (float)$v;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '' || !is_numeric($v)) return null;
            return (float)$v;
        }
        return null;
    }
}
