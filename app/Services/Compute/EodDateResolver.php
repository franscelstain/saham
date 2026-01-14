<?php

namespace App\Services\Compute;

use App\Repositories\MarketCalendarRepository;
use App\Trade\Support\TradeClock;
use Carbon\Carbon;

class EodDateResolver
{
    private MarketCalendarRepository $cal;

    public function __construct(MarketCalendarRepository $cal)
    {
        $this->cal = $cal;
    }

    /**
     * Resolve trade date yang boleh diproses compute-eod.
     *
     * Rules:
     * - Jika requestedDate null: pakai default date yang aman (<= hari ini, dan kalau sebelum cutoff pakai prev trading day).
     * - Jika requestedDate == today dan sebelum cutoff: turun ke prev trading day (biar gak compute data hari ini sebelum EOD).
     * - Jika requestedDate hari libur: turun ke previous trading day.
     */
    public function resolve(?string $requestedDate = null, ?Carbon $now = null): ?string
    {
        $requestedDate = $requestedDate ? Carbon::parse($requestedDate)->toDateString() : null;

        // 1) Explicit date: do NOT shift for holiday/weekend.
        // Only apply cutoff rule for "today".
        if ($requestedDate) {
            return $requestedDate; // keep as-is, service will decide skip reason
        }

        $today = TradeClock::today($now);
        $date  = $today;

        if (TradeClock::isBeforeEodCutoff($now)) {
            $date = $this->cal->previousTradingDate($today);
        }

        if (!$date) return null;

        // Default mode boleh mundur sampai ketemu trading day
        while ($date && !$this->cal->isTradingDay($date)) {
            $date = $this->cal->previousTradingDate($date);
        }

        return $date;
    }
    
    public function isBeforeCutoff(?Carbon $now = null): bool
    {
        return TradeClock::isBeforeEodCutoff($now);
    }
}
