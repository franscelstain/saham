<?php

namespace App\Trade\Watchlist\Scorecard;

/**
 * Synthesizes default execution_slices when absent.
 *
 * SRP_Performa.md + DTO.md:
 * - Business-facing defaulting should not live inside DTO constructors/parsers.
 * - DTO remains a strict contract; synthesis is an explicit domain step.
 */
final class ExecutionSlicesSynthesizer
{
    /**
     * @param array<int,array<string,mixed>> $executionSlices Normalized slices (may be empty).
     * @param int|null $entryTriggerPrice Entry trigger price (ticks/price int), if available.
     * @param float $maxChasePct Guard max chase percentage (0.02 = +2%).
     * @return array<int,array<string,mixed>> Slices (possibly synthesized).
     */
    public function synthesizeIfMissing(array $executionSlices, $entryTriggerPrice, $maxChasePct): array
    {
        if (!empty($executionSlices)) {
            return array_values($executionSlices);
        }

        if ($entryTriggerPrice === null) {
            return [];
        }

        $entry = (float)$entryTriggerPrice;
        $cap = $entry * (1.0 + (float)$maxChasePct);

        return [[
            'tranche' => 1,
            'lots' => null,
            'plan_limit_price' => $entry,
            'plan_price_cap' => $cap,
        ]];
    }
}
