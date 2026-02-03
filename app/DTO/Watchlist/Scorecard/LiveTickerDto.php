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

    // For strict CONFIRM: prefer prev_close_plan (EOD reference), but keep live prev close too.
    /** @var float|null */
    public $prevClosePlan;
    /** @var float|null */
    public $prevCloseLive;

    // Backward compat alias
    /** @var float|null */
    public $prevClose;

    // Retry budget state (internal; not required from broker input)
    /** @var int|null */
    public $retryCount;
    /** @var string|null */
    public $retryLastCheckedAt;

    public function __construct(
        $ticker,
        $bid,
        $ask,
        $last,
        $open,
        $prevClosePlan,
        $prevCloseLive,
        $retryCount = null,
        $retryLastCheckedAt = null
    ) {
        $this->ticker = (string)$ticker;
        $this->bid = ($bid === null) ? null : (float)$bid;
        $this->ask = ($ask === null) ? null : (float)$ask;
        $this->last = ($last === null) ? null : (float)$last;
        $this->open = ($open === null) ? null : (float)$open;
        $this->prevClosePlan = ($prevClosePlan === null) ? null : (float)$prevClosePlan;
        $this->prevCloseLive = ($prevCloseLive === null) ? null : (float)$prevCloseLive;
        $this->prevClose = $this->prevClosePlan !== null ? $this->prevClosePlan : $this->prevCloseLive;

        $this->retryCount = ($retryCount === null) ? null : (int)$retryCount;
        $this->retryLastCheckedAt = ($retryLastCheckedAt === null || $retryLastCheckedAt === '') ? null : (string)$retryLastCheckedAt;
    }

    /**
     * @param array<string,mixed> $a
     * @return self
     */
    public static function fromArray(array $a)
    {
        $ticker = strtoupper(trim((string)($a['ticker'] ?? ($a['ticker_code'] ?? ''))));
        $prevPlan = self::toFloatOrNull($a['prev_close_plan'] ?? null);
        $prevLive = self::toFloatOrNull($a['prev_close_live'] ?? ($a['prev_close'] ?? null));

        $retryCount = null;
        if (isset($a['retry_count']) && is_numeric($a['retry_count'])) $retryCount = (int)$a['retry_count'];
        $retryLast = isset($a['retry_last_checked_at']) ? (string)$a['retry_last_checked_at'] : null;

        return new self(
            $ticker,
            self::toFloatOrNull($a['bid'] ?? ($a['bid1'] ?? null)),
            self::toFloatOrNull($a['ask'] ?? ($a['ask1'] ?? null)),
            self::toFloatOrNull($a['last'] ?? ($a['open_or_last'] ?? null)),
            self::toFloatOrNull($a['open'] ?? null),
            $prevPlan,
            $prevLive,
            $retryCount,
            $retryLast
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        $a = [
            'ticker' => $this->ticker,
            'bid' => $this->bid,
            'ask' => $this->ask,
            'last' => $this->last,
            'open' => $this->open,
            'prev_close_plan' => $this->prevClosePlan,
            'prev_close_live' => $this->prevCloseLive,
        ];
        if ($this->retryCount !== null) $a['retry_count'] = (int)$this->retryCount;
        if ($this->retryLastCheckedAt !== null) $a['retry_last_checked_at'] = $this->retryLastCheckedAt;
        return $a;
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
