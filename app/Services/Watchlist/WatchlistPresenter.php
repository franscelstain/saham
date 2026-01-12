<?php

namespace App\Services\Watchlist;

use App\DTO\Watchlist\CandidateInput;
use App\DTO\Watchlist\FilterOutcome;

/**
 * SRP: bentuk output JSON (array) untuk UI.
 * Tidak mengurus query DB, planning, expiry, ranking calculation.
 */
class WatchlistPresenter
{
    /**
     * @param CandidateInput $c
     * @param FilterOutcome $outcome
     * @param string $setupStatus
     * @param array $plan
     * @param array $expiry
     */
    public function baseRow(CandidateInput $c, FilterOutcome $outcome, string $setupStatus, array $plan, array $expiry): array
    {
        return [
            'tickerId' => $c->tickerId,
            'code' => $c->code,
            'name' => $c->name,

            'close' => $c->close,
            'ma20' => $c->ma20,
            'ma50' => $c->ma50,
            'ma200' => $c->ma200,
            'rsi' => $c->rsi,

            'volume' => $c->volume,
            'valueEst' => $c->valueEst,
            'tradeDate' => $c->tradeDate,

            // raw codes
            'decisionCode' => $c->decisionCode,
            'signalCode' => $c->signalCode,
            'volumeLabelCode' => $c->volumeLabelCode,

            // labels
            'decisionLabel' => $c->decisionLabel,
            'signalLabel' => $c->signalLabel,
            'volumeLabel' => $c->volumeLabel,

            'setupStatus' => $setupStatus,
            'reasons' => array_map(fn($r) => $r->code, $outcome->passed()),

            'plan' => $plan,

            'expiryStatus' => $expiry['expiryStatus'] ?? 'N/A',
            'isExpired' => (bool) ($expiry['isExpired'] ?? false),
            'signalAgeDays' => $c->signalAgeDays,
            'signalFirstSeenDate' => $c->signalFirstSeenDate,
        ];
    }

    public function attachRank(array $row, array $rank): array
    {
        $row['rankScore'] = $rank['score'] ?? 0;
        $row['rankReasonCodes'] = $rank['codes'] ?? [];

        // optional debug
        if (!empty($rank['reasons'])) {
            $row['rankReasons'] = $rank['reasons'];
        }

        return $row;
    }
}
