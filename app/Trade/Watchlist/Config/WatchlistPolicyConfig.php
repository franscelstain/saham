<?php

namespace App\Trade\Watchlist\Config;

/**
 * WatchlistPolicyConfig
 *
 * Holder konfigurasi watchlist agar engine/builder tidak baca config() langsung.
 */
final class WatchlistPolicyConfig
{
    private string $policyDefault;
    private ?string $eodCutoffTimeOverride;

    private bool $marketRegimeEnabled;
    private array $marketRegimeThresholds;

    private int $maxStaleTradingDays;

    private float $minCanonicalCoveragePct;
    private float $minIndicatorCoveragePct;

    private bool $autoPositionTradeEnabled;

    // thresholds used by derived metrics
    private float $dv20AMin;
    private float $dv20BMin;
    private float $caSuspectMin;
    private float $caSuspectMax;
    private float $candleLongWickPct;

    private bool $confirmEnabled;
    private array $confirmGuards;

    // policy knobs used by Engine (docs/watchlist/*)
    private int $weeklySwingSignalRecentDays;
    private array $weeklySwingSignalCodes;
    private array $weeklySwingAvoidReasonCodes;

    // global knobs used by Engine (docs/watchlist/watchlist.md)
    private array $groupSemantics;
    private array $universe;

    public function __construct(
        string $policyDefault,
        ?string $eodCutoffTimeOverride,
        bool $marketRegimeEnabled,
        array $marketRegimeThresholds,
        int $maxStaleTradingDays,
        float $minCanonicalCoveragePct,
        float $minIndicatorCoveragePct,
        bool $autoPositionTradeEnabled,
        float $dv20AMin,
        float $dv20BMin,
        float $caSuspectMin,
        float $caSuspectMax,
        float $candleLongWickPct,
        bool $confirmEnabled,
        array $confirmGuards,
        int $weeklySwingSignalRecentDays,
        array $weeklySwingSignalCodes,
        array $weeklySwingAvoidReasonCodes,
        array $groupSemantics,
        array $universe
    ) {
        $this->policyDefault = $policyDefault;
        $this->eodCutoffTimeOverride = $eodCutoffTimeOverride;
        $this->marketRegimeEnabled = $marketRegimeEnabled;
        $this->marketRegimeThresholds = $marketRegimeThresholds;
        $this->maxStaleTradingDays = max(0, $maxStaleTradingDays);
        $this->minCanonicalCoveragePct = max(0.0, min(100.0, $minCanonicalCoveragePct));
        $this->minIndicatorCoveragePct = max(0.0, min(100.0, $minIndicatorCoveragePct));
        $this->autoPositionTradeEnabled = $autoPositionTradeEnabled;
        $this->dv20AMin = max(0.0, $dv20AMin);
        $this->dv20BMin = max(0.0, $dv20BMin);
        $this->caSuspectMin = max(0.0, $caSuspectMin);
        $this->caSuspectMax = max(0.0, $caSuspectMax);
        $this->candleLongWickPct = max(0.0, min(1.0, $candleLongWickPct));

        $this->confirmEnabled = $confirmEnabled;
        $this->confirmGuards = $confirmGuards;

        $this->weeklySwingSignalRecentDays = max(1, (int) $weeklySwingSignalRecentDays);
        $this->weeklySwingSignalCodes = $weeklySwingSignalCodes;
        $this->weeklySwingAvoidReasonCodes = $weeklySwingAvoidReasonCodes;

        $this->groupSemantics = $groupSemantics;
        $this->universe = $universe;
    }

    public function policyDefault(): string { return $this->policyDefault; }
    public function eodCutoffTimeOverride(): ?string { return $this->eodCutoffTimeOverride; }

    public function marketRegimeEnabled(): bool { return $this->marketRegimeEnabled; }
    public function marketRegimeThresholds(): array { return $this->marketRegimeThresholds; }

    public function maxStaleTradingDays(): int { return $this->maxStaleTradingDays; }

    public function minCanonicalCoveragePct(): float { return $this->minCanonicalCoveragePct; }
    public function minIndicatorCoveragePct(): float { return $this->minIndicatorCoveragePct; }

    public function autoPositionTradeEnabled(): bool { return $this->autoPositionTradeEnabled; }

    public function dv20AMin(): float { return $this->dv20AMin; }
    public function dv20BMin(): float { return $this->dv20BMin; }
    public function caSuspectMin(): float { return $this->caSuspectMin; }
    public function caSuspectMax(): float { return $this->caSuspectMax; }
    public function candleLongWickPct(): float { return $this->candleLongWickPct; }

    public function confirmEnabled(): bool { return $this->confirmEnabled; }
    public function confirmGuards(): array { return $this->confirmGuards; }

    public function weeklySwingSignalRecentDays(): int { return $this->weeklySwingSignalRecentDays; }
    public function weeklySwingSignalCodes(): array { return $this->weeklySwingSignalCodes; }
    public function weeklySwingAvoidReasonCodes(): array { return $this->weeklySwingAvoidReasonCodes; }

    public function groupSemantics(): array { return $this->groupSemantics; }
    public function universe(): array { return $this->universe; }

}
