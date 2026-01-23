<?php

namespace App\Services\Compute;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\MarketData\RunRepository;
use App\Trade\Support\TradeClock;
use Carbon\Carbon;

class EodDateResolver
{
    private MarketCalendarRepository $cal;
    private RunRepository $runs;

    public function __construct(MarketCalendarRepository $cal, RunRepository $runs)
    {
        $this->cal = $cal;
        $this->runs = $runs;
    }

    /**
     * Resolve trade date yang boleh diproses compute-eod.
     *
     * Rules:
     * - Jika requestedDate *diisi*: return apa adanya.
     *   (Validasi trading day / holiday dilakukan oleh service, agar explicit date tidak diam-diam bergeser.)
     * - Jika requestedDate null: pakai default date yang aman (<= hari ini,
     *   dan kalau sebelum cutoff pakai prev trading day), lalu mundur sampai ketemu trading day.
     */
    public function resolve(?string $requestedDate = null, ?Carbon $now = null): ?string
    {
        $tz = TradeClock::tz();

        $requestedDate = $requestedDate ? Carbon::parse($requestedDate, $tz)->toDateString() : null;

        // 1) Explicit date: do NOT shift for holiday/weekend.
        if ($requestedDate) {
            return $requestedDate; // keep as-is, service will decide skip reason
        }

        $today = TradeClock::today($now);

        // 2) Default date: prefer last known-good market-data trade date.
        // Contract (compute_eod.md): if latest market-data run is HELD/FAILED,
        // compute-eod must fall back to last_good_trade_date (the latest SUCCESS end date).
        $date = null;

        $latest = $this->runs->findLatestImportRun();
        if ($latest) {
            $status = strtoupper((string) ($latest->status ?? ''));
            $effEnd = isset($latest->effective_end_date) ? (string) $latest->effective_end_date : null;
            $lastGood = isset($latest->last_good_trade_date) ? (string) $latest->last_good_trade_date : null;

            if ($status === 'SUCCESS' && $effEnd) {
                $date = $effEnd;
            } else {
                if (!$lastGood) {
                    $lastGood = $this->runs->getLatestSuccessEffectiveEndDate();
                }
                $date = $lastGood ?: $effEnd;
            }
        }

        // 3) Fallback if no market-data runs exist
        if (!$date) {
            $date = $today;
            if (TradeClock::isBeforeEodCutoff($now)) {
                $date = $this->cal->previousTradingDate($today);
            }
        }

        if (!$date) return null;

        // safety clamp: never return a future date
        if ($date > $today) {
            $date = TradeClock::isBeforeEodCutoff($now)
                ? ($this->cal->previousTradingDate($today) ?: $today)
                : $today;
        }

        // Default mode boleh mundur sampai ketemu trading day
        while ($date && !$this->cal->isTradingDay($date)) {
            $date = $this->cal->previousTradingDate($date);
        }

        return $date;
    }
}
