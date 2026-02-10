<?php

namespace App\Trade\Watchlist\Policies;

use App\Trade\Watchlist\WatchlistEngine;

/**
 * NO_TRADE policy: keep candidates as watch_only and never eligible.
 */
class NoTradePolicy implements WatchlistPolicyInterface
{
    public function code(): string
    {
        return 'NO_TRADE';
    }

    public function apply(array $x, array $reasonCodes, WatchlistEngine $engine): array
    {
        $blockCodes = ['GL_POLICY_INACTIVE'];
        return [
            'drop' => false,
            'score' => 0.0,
            'entry_style' => 'WATCH_ONLY',
            'confidence' => 'Low',
            'reason_codes' => array_values(array_unique($reasonCodes)),
            'eligibility_block_codes' => $blockCodes,
            'size_multiplier_adj' => 1.0,
            'shift_entry_windows' => null,
        ];
    }
}
