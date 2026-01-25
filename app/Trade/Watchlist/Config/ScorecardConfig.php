<?php

namespace App\Trade\Watchlist\Config;

/**
 * Immutable-ish config object for Watchlist Scorecard.
 *
 * SRP_Performa.md: Provider is the only place that may call config().
 * Domain/compute layers must receive thresholds via injection.
 *
 * NOTE: Keep this PHP 7.3-compatible (no constructor property promotion / typed properties).
 */
class ScorecardConfig
{
    /** @var bool */
    public $includeWatchOnly;
    /** @var float */
    public $maxChasePctDefault;
    /** @var float */
    public $gapUpBlockPctDefault;
    /** @var float */
    public $spreadMaxPctDefault;
    /** @var string */
    public $sessionOpenTimeDefault;
    /** @var string */
    public $sessionCloseTimeDefault;

    public function __construct(
        $includeWatchOnly,
        $maxChasePctDefault,
        $gapUpBlockPctDefault,
        $spreadMaxPctDefault,
        $sessionOpenTimeDefault,
        $sessionCloseTimeDefault
    ) {
        $this->includeWatchOnly = (bool)$includeWatchOnly;
        $this->maxChasePctDefault = (float)$maxChasePctDefault;
        $this->gapUpBlockPctDefault = (float)$gapUpBlockPctDefault;
        $this->spreadMaxPctDefault = (float)$spreadMaxPctDefault;
        $this->sessionOpenTimeDefault = (string)$sessionOpenTimeDefault;
        $this->sessionCloseTimeDefault = (string)$sessionCloseTimeDefault;
    }

    /**
     * @param array<string,mixed> $cfg
     * @return self
     */
    public static function fromArray(array $cfg)
    {
        return new self(
            (bool)($cfg['include_watch_only'] ?? false),
            (float)($cfg['max_chase_pct_default'] ?? 0.01),
            (float)($cfg['gap_up_block_pct_default'] ?? 0.015),
            (float)($cfg['spread_max_pct_default'] ?? 0.004),
            (string)($cfg['session_open_time_default'] ?? '09:00'),
            (string)($cfg['session_close_time_default'] ?? '15:50')
        );
    }
}
