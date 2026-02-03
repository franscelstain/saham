<?php

namespace App\Trade\Watchlist\Config;

/**
 * Immutable-ish config object for Watchlist Scorecard/Confirm.
 *
 * SRP_Performa.md: Provider is the only place that may call config().
 * Domain/compute layers must receive thresholds via injection.
 *
 * NOTE: Keep this PHP 7.3-compatible (no typed properties / property promotion).
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

    // --- strict CONFIRM knobs (docs/watchlist/scorecard.md) ---
    /** @var float */
    public $staleTolPct;
    /** @var int */
    public $maxSnapshotAgeSec;
    /** @var int */
    public $retryCooldownSec;
    /** @var float */
    public $breakoutBandPctDefault;
    /** @var int */
    public $maxRetryWindowsDefault;

    /** @var array<string,array<string,mixed>> */
    public $overridesByPolicy;

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

        // defaults for strict
        $this->staleTolPct = 0.002;
        $this->maxSnapshotAgeSec = 60;
        $this->retryCooldownSec = 15;
        $this->breakoutBandPctDefault = 0.008;
        $this->maxRetryWindowsDefault = 3;

        $this->overridesByPolicy = [];
    }

    /**
     * @param array<string,mixed> $cfg
     * @return self
     */
    public static function fromArray(array $cfg)
    {
        $o = new self(
            (bool)($cfg['include_watch_only'] ?? false),
            (float)($cfg['max_chase_pct_default'] ?? 0.01),
            (float)($cfg['gap_up_block_pct_default'] ?? 0.015),
            (float)($cfg['spread_max_pct_default'] ?? 0.004),
            (string)($cfg['session_open_time_default'] ?? '09:00'),
            (string)($cfg['session_close_time_default'] ?? '15:50')
        );

        // strict knobs (optional)
        if (isset($cfg['stale_tol_pct']) && is_numeric($cfg['stale_tol_pct'])) $o->staleTolPct = (float)$cfg['stale_tol_pct'];
        if (isset($cfg['max_snapshot_age_sec']) && is_numeric($cfg['max_snapshot_age_sec'])) $o->maxSnapshotAgeSec = (int)$cfg['max_snapshot_age_sec'];
        if (isset($cfg['retry_cooldown_sec']) && is_numeric($cfg['retry_cooldown_sec'])) $o->retryCooldownSec = (int)$cfg['retry_cooldown_sec'];
        if (isset($cfg['breakout_band_pct_default']) && is_numeric($cfg['breakout_band_pct_default'])) $o->breakoutBandPctDefault = (float)$cfg['breakout_band_pct_default'];
        if (isset($cfg['max_retry_windows_default']) && is_numeric($cfg['max_retry_windows_default'])) $o->maxRetryWindowsDefault = (int)$cfg['max_retry_windows_default'];

        // policy overrides (optional)
        $over = isset($cfg['policy_overrides']) && is_array($cfg['policy_overrides']) ? $cfg['policy_overrides'] : [];
        if (is_array($over)) {
            foreach ($over as $policy => $vals) {
                if (!is_string($policy) || $policy === '' || !is_array($vals)) continue;
                $o->overridesByPolicy[strtoupper($policy)] = $vals;
            }
        }

        return $o;
    }

    /**
     * Resolve scalar config with policy override.
     *
     * @param string $policy
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function policyValue(string $policy, string $key, $default)
    {
        $p = strtoupper(trim($policy));
        if ($p !== '' && isset($this->overridesByPolicy[$p]) && is_array($this->overridesByPolicy[$p])) {
            $vals = $this->overridesByPolicy[$p];
            if (array_key_exists($key, $vals)) return $vals[$key];
        }
        return $default;
    }

    /**
     * @return array{max_chase_pct:float,gap_up_block_pct:float,spread_max_pct:float,breakout_band_pct:float,max_retry_windows:int}
     */
    public function guardsForPolicy(string $policy): array
    {
        $maxChase = $this->policyValue($policy, 'max_chase_pct', $this->maxChasePctDefault);
        $gapBlock = $this->policyValue($policy, 'gap_up_block_pct', $this->gapUpBlockPctDefault);
        $spreadMax = $this->policyValue($policy, 'spread_max_pct', $this->spreadMaxPctDefault);
        $band = $this->policyValue($policy, 'breakout_band_pct', $this->breakoutBandPctDefault);
        $maxRetry = $this->policyValue($policy, 'max_retry_windows', $this->maxRetryWindowsDefault);

        return [
            'max_chase_pct' => (float)$maxChase,
            'gap_up_block_pct' => (float)$gapBlock,
            'spread_max_pct' => (float)$spreadMax,
            'breakout_band_pct' => (float)$band,
            'max_retry_windows' => (int)$maxRetry,
        ];
    }

    /**
     * @return array{entry_windows:string[],avoid_windows:string[]}
     */
    public function windowsForPolicy(string $policy): array
    {
        $entry = $this->policyValue($policy, 'entry_windows', ['open-close']);
        $avoid = $this->policyValue($policy, 'avoid_windows', []);
        if (!is_array($entry)) $entry = ['open-close'];
        if (!is_array($avoid)) $avoid = [];
        $entry = array_values(array_map('strval', $entry));
        $avoid = array_values(array_map('strval', $avoid));
        return ['entry_windows' => $entry, 'avoid_windows' => $avoid];
    }
}
