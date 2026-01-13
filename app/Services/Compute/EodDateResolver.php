<?php

namespace App\Services\Compute;

use Carbon\Carbon;
use App\Repositories\MarketCalendarRepository;

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
    public function resolve(?string $requestedDate = null): ?string
    {
        $requestedDate = $requestedDate ? Carbon::parse($requestedDate)->toDateString() : null;

        // 1) Explicit date: do NOT shift for holiday/weekend.
        // Only apply cutoff rule for "today".
        if ($requestedDate) {
            return $requestedDate; // keep as-is, service will decide skip reason
        }

        // 2) Default mode: safe date
        $today = Carbon::now()->toDateString();
        $beforeCutoff = $this->isBeforeCutoff();

        $date = $today;
        if ($beforeCutoff) {
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
        $now = $now ?: Carbon::now();

        $cutoffHour = (int) config('trade.compute.eod_cutoff_hour', 16);
        $cutoffMin  = (int) config('trade.compute.eod_cutoff_min', 5);

        return $now->hour < $cutoffHour || ($now->hour === $cutoffHour && $now->minute < $cutoffMin);
    }
}
