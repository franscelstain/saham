<?php

namespace App\Trade\MarketData\Normalize;

use App\Trade\MarketData\DTO\RawBar;
use App\Trade\MarketData\DTO\EodBar;
use Carbon\Carbon;

final class EodBarNormalizer
{
    /** @var string */
    private $timezone;

    public function __construct(string $timezone)
    {
        $this->timezone = $timezone ?: 'Asia/Jakarta';
    }

    /**
     * Convert raw bar to normalized EOD bar (WIB trade_date).
     */
    public function normalize(RawBar $raw): ?EodBar
    {
        if (!$raw->epoch) return null;

        $tradeDate = Carbon::createFromTimestamp((int) $raw->epoch, 'UTC')
            ->setTimezone($this->timezone)
            ->toDateString();

        return new EodBar(
            $tradeDate,
            $raw->open,
            $raw->high,
            $raw->low,
            $raw->close,
            $raw->adjClose,
            $raw->volume
        );
    }
}
