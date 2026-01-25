<?php

namespace App\Trade\Watchlist\Config;

/**
 * Immutable config object for Watchlist Scorecard.
 *
 * SRP_Performa.md: Provider is the only place that may call config().
 * Domain/compute layers must receive thresholds via injection.
 */
class ScorecardConfig
{
    public function __construct(
        public bool $includeWatchOnly,
        public float $maxChasePctDefault,
        public float $gapUpBlockPctDefault,
        public float $spreadMaxPctDefault,
        public string $sessionOpenTimeDefault,
        public string $sessionCloseTimeDefault,
    ) {
    }

    /**
     * @param array<string,mixed> $cfg
     */
    public static function fromArray(array $cfg): self
    {
        return new self(
            (bool)($cfg['include_watch_only'] ?? false),
            (float)($cfg['max_chase_pct_default'] ?? 0.01),
            (float)($cfg['gap_up_block_pct_default'] ?? 0.015),
            (float)($cfg['spread_max_pct_default'] ?? 0.004),
            (string)($cfg['session_open_time_default'] ?? '09:00'),
            (string)($cfg['session_close_time_default'] ?? '15:50'),
        );
    }
}
