<?php

namespace App\Trade\Compute;

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

        $prevSignal = (int) ($prevState['signal_code'] ?? 0);
        $prevFirstSeen = $prevState['signal_first_seen_date'] ?? null;

        if ($prevSignal === (int) $signalCode && $prevFirstSeen) {
            $firstSeen = (string) $prevFirstSeen;

            $ageDays = (int) floor((strtotime($tradeDate) - strtotime($firstSeen)) / 86400);
            if ($ageDays < 0) $ageDays = 0;

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
