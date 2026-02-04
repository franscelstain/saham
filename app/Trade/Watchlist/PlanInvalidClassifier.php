<?php

namespace App\Trade\Watchlist;

/**
 * Centralized classifier for policy-level PLAN_INVALID reasons.
 *
 * PLAN_INVALID means the plan math/levels are invalid (e.g. R<=0, R<tick, TP1<=entry),
 * so the ticker must be EXCLUDED from that policy output (not merely watch_only).
 */
final class PlanInvalidClassifier
{
    /**
     * Exact reason codes that are considered PLAN_INVALID.
     * Keep this list small; prefer pattern matching when possible.
     */
    private const EXACT = [
        // Generic
        'R_INVALID_NONPOSITIVE',
        'R_INVALID_LT_TICK',

        // Weekly Swing
        'WS_R_INVALID_NONPOSITIVE',
        'WS_R_INVALID_LT_TICK',

        // Dividend Swing
        'DS_R_INVALID_NONPOSITIVE',
        'DS_R_INVALID_LT_TICK',
        'DS_TP1_NOT_ABOVE_ENTRY',

        // Intraday Light
        'IL_R_INVALID_R_LE_0',
        'IL_R_INVALID_R_LT_TICK',
    ];

    /**
     * Returns true if the reason code indicates PLAN_INVALID.
     */
    public static function isPlanInvalidReason($code): bool
    {
        if (!is_string($code) || $code === '') {
            return false;
        }

        if (in_array($code, self::EXACT, true)) {
            return true;
        }

        // Broad patterns (kept in one place to avoid drift)
        if (preg_match('/_R_INVALID_/', $code)) {
            return true;
        }
        if (preg_match('/_TP1_NOT_ABOVE_ENTRY$/', $code)) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if any code in the list is PLAN_INVALID.
     *
     * @param array $codes
     */
    public static function any(array $codes): bool
    {
        foreach ($codes as $c) {
            if (self::isPlanInvalidReason($c)) {
                return true;
            }
        }
        return false;
    }
}
