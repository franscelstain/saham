<?php

namespace App\Trade\Compute;

class SignalAgeTracker
{
    /**
     * Upgrade Expiry Basis (Opsi A):
     * - Age dihitung berdasarkan "BUY-ish bucket" (decision_code 4/5), bukan decision exact.
     * - Tujuan: expiry lebih stabil; tidak reset hanya karena 4 <-> 5.
     *
     * BUY-ish = decision_code in (4, 5)
     *
     * Rules:
     * - Jika current bukan BUY-ish => reset (age=0, first_seen=tradeDate)
     * - Jika prev tidak ada => (age=0, first_seen=tradeDate)
     * - Jika prev BUY-ish dan current BUY-ish => age=prev_age+1, first_seen=prev_first_seen (fallback prevTradeDate)
     * - Jika prev bukan BUY-ish dan current BUY-ish => age=0, first_seen=tradeDate
     */
    public function computeDecisionAge(
        int $currentDecisionCode,
        $prevSnapshot,
        string $tradeDate,
        ?string $prevTradeDate
    ): array {
        // default reset
        $firstSeen = $tradeDate;
        $age = 0;

        $currentBuyish = $this->isBuyish($currentDecisionCode);

        // kalau hari ini bukan BUY-ish, selalu reset (expiry tidak relevan)
        if (!$currentBuyish) {
            return [
                'signal_first_seen_date' => $firstSeen,
                'signal_age_days' => $age,
            ];
        }

        // hari ini BUY-ish, cek prev
        if ($prevSnapshot && $prevTradeDate) {
            $prevDecision = (int) $prevSnapshot->decision_code;
            $prevBuyish = $this->isBuyish($prevDecision);

            if ($prevBuyish) {
                $age = ((int)($prevSnapshot->signal_age_days ?? 0)) + 1;
                $firstSeen = $prevSnapshot->signal_first_seen_date ?: $prevTradeDate;
            }
        }

        return [
            'signal_first_seen_date' => $firstSeen,
            'signal_age_days' => $age,
        ];
    }

    private function isBuyish(int $decisionCode): bool
    {
        return in_array($decisionCode, [4, 5], true);
    }
}
