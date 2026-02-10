<?php

namespace App\Trade\Watchlist\Policies;

/**
 * Hardcoded defaults that are locked by docs/watchlist/*.
 *
 * These numbers are POLICY defaults (PLAN stage). They must not be confused
 * with any indicator scoring fields.
 */
final class PolicyDefaults
{
    // WEEKLY_SWING
    public const WS_MIN_DV20_IDR = 5000000000.0; // Rp 5B
    public const WS_MIN_RR       = 1.3;
    public const WS_MIN_ATR_PCT  = 0.02;
    public const WS_MAX_ATR_PCT  = 0.20;
    public const WS_MAX_TICK_PCT = 0.015;
    public const WS_TP2_R_MULT   = 2.0; // docs: TP2 = 2.0 * R

    // DIVIDEND_SWING
    public const DS_MIN_RR                   = 1.2;
    public const DS_MAX_STOP_PCT             = 0.06;
    public const DS_MAX_EXTEND_ATR           = 1.0;
    public const DS_BREAKOUT_MIN_VOL_RATIO   = 1.0;
    public const DS_BREAKOUT_MIN_CLOSE_POS   = 0.70;
    public const DS_PULLBACK_MAX_MA_DIST_ATR = 0.5;
    public const DS_PULLBACK_MIN_LOWER_WICK  = 0.30;
    public const DS_PULLBACK_MIN_CLOSE_POS   = 0.60;
}
