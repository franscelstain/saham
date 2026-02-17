<?php

namespace App\Trade\Watchlist\Policies;

use App\Trade\Watchlist\WatchlistEngine;

interface WatchlistPolicyInterface
{
    public function code(): string;

    /**
     * Policy-specific PLAN enrichment/validation that must not live in WatchlistEngine.
     *
     * This runs BEFORE grouping (top/secondary/watch/avoid) so a policy can:
     * - set plan.is_eligible_new_entry
     * - add plan.block_codes
     * - set plan.trade_viability fields (evaluated/is_viable/reason_codes)
     *
     * @param array<string,mixed> $row  Candidate row (by reference)
     * @param array<string,mixed> $opts Runtime options (capital, etc.)
     * @param array<string,mixed> $policyMeta Policy meta/config (guards/thresholds)
     */
    public function enrichPlanRow(array &$row, array $opts, array $policyMeta, WatchlistEngine $engine): void;

    /**
     * Default action windows for managing existing positions (UI helper).
     * Must be policy-owned to avoid drift.
     *
     * @param array<string,mixed> $session
     * @return array<int,string>
     */
    public function defaultActionWindows(array $session): array;

    /**
     * @param array<string,mixed> $x
     * @param string[] $reasonCodes
     * @return array<string,mixed>
     */
    public function apply(array $x, array $reasonCodes, WatchlistEngine $engine): array;
}
