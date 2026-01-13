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
     * @param int $signalCode current
     * @param array|null $prevState ['signal_code'=>int,'signal_first_seen_date'=>?string]
     */
    public function computeFromPrev(int $tickerId, string $tradeDate, int $signalCode, ?array $prevState): array
    {
        if (!$prevState) {
            return [
                'signal_first_seen_date' => $tradeDate,
                'signal_age_days' => 0,
            ];
        }

        $prevSignal   = (int) ($prevState['signal_code'] ?? 0);
        $prevFirstSeen = $prevState['signal_first_seen_date'] ?? null;

        if ($prevSignal === (int) $signalCode && $prevFirstSeen) {
            $firstSeen = (string) $prevFirstSeen;

            // Date-only compare, avoids timezone/hour issues
            $trade = Carbon::parse($tradeDate)->startOfDay();
            $first = Carbon::parse($firstSeen)->startOfDay();

            // Always non-negative (same behavior as your clamp-to-0)
            $ageDays = $first->diffInDays($trade);

            return [
                'signal_first_seen_date' => $firstSeen,
                'signal_age_days' => $ageDays,
            ];
        }

        return [
            'signal_first_seen_date' => $tradeDate,
            'signal_age_days' => 0,
        ];
    }
}
