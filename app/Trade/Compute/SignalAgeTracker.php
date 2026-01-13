<?php

namespace App\Trade\Compute;

use Carbon\Carbon;

class SignalAgeTracker
{
    /**
     * Pure logic. Tidak query DB.
     *
     * @param int $tickerId
     * @param string $tradeDate YYYY-MM-DD
     * @param int $signalCode current (ini = signal_code)
     * @param array|null $prevState ['signal_code'=>int,'signal_first_seen_date'=>?string]
     * @return array ['signal_first_seen_date'=>string, 'signal_age_days'=>int]
     */
    public function computeFromPrev(int $tickerId, string $tradeDate, int $signalCode, ?array $prevState): array
    {
        // First time / no previous snapshot
        if (!$prevState) {
            return [
                'signal_first_seen_date' => $tradeDate,
                'signal_age_days' => 0,
            ];
        }

        $prevSignal    = (int) ($prevState['signal_code'] ?? 0);
        $prevFirstSeen = $prevState['signal_first_seen_date'] ?? null;

        // same signal continues, and we have firstSeen -> age increases
        if ($prevSignal === (int) $signalCode && $prevFirstSeen) {
            $firstSeen = (string) $prevFirstSeen;

            // date-only compare (no timezone/hour noise)
            $trade = Carbon::parse($tradeDate)->startOfDay();
            $first = Carbon::parse($firstSeen)->startOfDay();

            $ageDays = $first->diffInDays($trade); // always >= 0

            return [
                'signal_first_seen_date' => $firstSeen,
                'signal_age_days' => $ageDays,
            ];
        }

        // signal changed OR prev firstSeen empty -> reset
        return [
            'signal_first_seen_date' => $tradeDate,
            'signal_age_days' => 0,
        ];
    }
}
