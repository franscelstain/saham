<?php

namespace App\Trade\Support;

/**
 * TradeClockConfig
 *
 * Wrapper config untuk TradeClock agar class utility tidak perlu baca config() langsung.
 */
final class TradeClockConfig
{
    /** @var string */
    private $timezone;

    /** @var int */
    private $eodCutoffHour;

    /** @var int */
    private $eodCutoffMin;

    public function __construct(string $timezone, int $eodCutoffHour, int $eodCutoffMin)
    {
        $tz = trim($timezone);
        if ($tz === '') $tz = 'Asia/Jakarta';

        $this->timezone = $tz;
        $this->eodCutoffHour = max(0, min(23, $eodCutoffHour));
        $this->eodCutoffMin = max(0, min(59, $eodCutoffMin));
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function eodCutoffHour(): int
    {
        return $this->eodCutoffHour;
    }

    public function eodCutoffMin(): int
    {
        return $this->eodCutoffMin;
    }
}
