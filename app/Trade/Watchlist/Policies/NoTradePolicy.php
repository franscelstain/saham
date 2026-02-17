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


    public function enrichPlanRow(array &$row, array $opts, array $policyMeta, WatchlistEngine $engine): void
    {
        // NO_TRADE never eligible for new entry.
        if (!isset($row['plan']) || !is_array($row['plan'])) $row['plan'] = [];
        $row['plan']['is_eligible_new_entry'] = false;
        if (!isset($row['plan']['block_codes']) || !is_array($row['plan']['block_codes'])) $row['plan']['block_codes'] = [];
        $row['plan']['block_codes'][] = 'NT_MONITOR_ONLY';
        $row['plan']['block_codes'] = array_values(array_unique($row['plan']['block_codes']));
    }

    public function defaultActionWindows(array $session): array
    {
        return ['open-close'];
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
