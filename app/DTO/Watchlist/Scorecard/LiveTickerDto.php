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

    // Depth Top-N (optional) bid/ask levels and lots
    /** @var float[]|null */
    public $bidLevels;
    /** @var int[]|null */
    public $bidLots;
    /** @var float[]|null */
    public $askLevels;
    /** @var int[]|null */
    public $askLots;

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
        $bidLevels = null,
        $bidLots = null,
        $askLevels = null,
        $askLots = null,
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

        $this->bidLevels = self::toFloatArrayOrNull($bidLevels);
        $this->bidLots = self::toIntArrayOrNull($bidLots);
        $this->askLevels = self::toFloatArrayOrNull($askLevels);
        $this->askLots = self::toIntArrayOrNull($askLots);

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

        $bidLevels = self::readDepthLevels($a, 'bid', 5);
        $bidLots = self::readDepthLots($a, 'bid_lots', 5);
        $askLevels = self::readDepthLevels($a, 'ask', 5);
        $askLots = self::readDepthLots($a, 'ask_lots', 5);

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
            $bidLevels,
            $bidLots,
            $askLevels,
            $askLots,
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
        if ($this->bidLevels !== null) $a['bid_levels'] = $this->bidLevels;
        if ($this->bidLots !== null) $a['bid_lots'] = $this->bidLots;
        if ($this->askLevels !== null) $a['ask_levels'] = $this->askLevels;
        if ($this->askLots !== null) $a['ask_lots'] = $this->askLots;
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

    /**
     * @param mixed $v
     * @return float[]|null
     */
    private static function toFloatArrayOrNull($v)
    {
        if ($v === null) return null;
        if (!is_array($v)) return null;
        $out = [];
        foreach ($v as $x) {
            $f = self::toFloatOrNull($x);
            if ($f === null) continue;
            $out[] = $f;
        }
        return $out;
    }

    /**
     * @param mixed $v
     * @return int[]|null
     */
    private static function toIntArrayOrNull($v)
    {
        if ($v === null) return null;
        if (!is_array($v)) return null;
        $out = [];
        foreach ($v as $x) {
            if ($x === null || $x === '') continue;
            if (is_int($x)) { $out[] = $x; continue; }
            if (is_float($x)) { $out[] = (int)round($x); continue; }
            if (is_string($x)) {
                $x = trim($x);
                if ($x === '' || !is_numeric($x)) continue;
                $out[] = (int)round((float)$x);
                continue;
            }
        }
        return $out;
    }

    /**
     * Read depth price levels from various broker payload shapes.
     * Accepts:
     * - `${side}_levels`: array of prices
     * - `${side}1..${side}N`: scalar prices
     * - `${side}`: array of prices
     *
     * @param array<string,mixed> $a
     * @return float[]|null
     */
    private static function readDepthLevels(array $a, string $side, int $n)
    {
        $k1 = $side . '_levels';
        if (isset($a[$k1]) && is_array($a[$k1])) return self::toFloatArrayOrNull($a[$k1]);
        if (isset($a[$side]) && is_array($a[$side])) return self::toFloatArrayOrNull($a[$side]);

        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $k = $side . $i;
            if (!array_key_exists($k, $a)) continue;
            $f = self::toFloatOrNull($a[$k]);
            if ($f === null) continue;
            $out[] = $f;
        }
        return empty($out) ? null : $out;
    }

    /**
     * Read depth lots from various broker payload shapes.
     * Accepts:
     * - `${sideLotsKey}`: array
     * - `${sideLotsKey}1..${sideLotsKey}N`: scalars
     *
     * @param array<string,mixed> $a
     * @return int[]|null
     */
    private static function readDepthLots(array $a, string $sideLotsKey, int $n)
    {
        if (isset($a[$sideLotsKey]) && is_array($a[$sideLotsKey])) return self::toIntArrayOrNull($a[$sideLotsKey]);

        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $k = $sideLotsKey . $i;
            if (!array_key_exists($k, $a)) continue;
            $x = $a[$k];
            if ($x === null || $x === '') continue;
            if (is_int($x)) { $out[] = $x; continue; }
            if (is_float($x)) { $out[] = (int)round($x); continue; }
            if (is_string($x)) {
                $x = trim($x);
                if ($x === '' || !is_numeric($x)) continue;
                $out[] = (int)round((float)$x);
                continue;
            }
        }
        return empty($out) ? null : $out;
    }
}
