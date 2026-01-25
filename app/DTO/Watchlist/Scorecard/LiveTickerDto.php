<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Live snapshot per ticker from broker app (Ajaib input).
 * PHP 7.3 compatible.
 */
class LiveTickerDto
{
    /** @var string */
    public $ticker;
    /** @var float|null */
    public $bid;
    /** @var float|null */
    public $ask;
    /** @var float|null */
    public $last;
    /** @var float|null */
    public $open;
    /** @var float|null */
    public $prevClose;

    public function __construct($ticker, $bid, $ask, $last, $open, $prevClose)
    {
        $this->ticker = (string)$ticker;
        $this->bid = ($bid === null) ? null : (float)$bid;
        $this->ask = ($ask === null) ? null : (float)$ask;
        $this->last = ($last === null) ? null : (float)$last;
        $this->open = ($open === null) ? null : (float)$open;
        $this->prevClose = ($prevClose === null) ? null : (float)$prevClose;
    }

    /**
     * @param array<string,mixed> $a
     * @return self
     */
    public static function fromArray(array $a)
    {
        $ticker = strtoupper(trim((string)($a['ticker'] ?? ($a['ticker_code'] ?? ''))));
        return new self(
            $ticker,
            self::toFloatOrNull($a['bid'] ?? null),
            self::toFloatOrNull($a['ask'] ?? null),
            self::toFloatOrNull($a['last'] ?? ($a['open_or_last'] ?? null)),
            self::toFloatOrNull($a['open'] ?? null),
            self::toFloatOrNull($a['prev_close'] ?? null)
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
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

    /**
     * @param mixed $v
     * @return float|null
     */
    private static function toFloatOrNull($v)
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
