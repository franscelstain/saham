<?php

namespace App\Trade\Ranking;

class WatchlistRanker
{
    private bool $enabled;
    private float $rrMin;
    private array $w;

    public function __construct(bool $enabled, float $rrMin, array $weights)
    {
        $this->enabled = $enabled;
        $this->rrMin = $rrMin;
        $this->w = $weights;
    }

    /**
     * Return:
     *  - score: float
     *  - reasons: string[]
     */
    public function rank(array $candidateRow): array
    {
        if (!$this->enabled) {
            return ['score' => 0.0, 'reasons' => ['RANKING_DISABLED']];
        }

        $score = 0.0;
        $reasons = [];

        // --- Setup status
        $setup = $candidateRow['setupStatus'] ?? null;
        if ($setup === 'SETUP_OK') {
            $score += $this->points('setup_ok'); $reasons[] = 'SETUP_OK';
        } elseif ($setup === 'SETUP_CONFIRM') {
            $score += $this->points('setup_confirm'); $reasons[] = 'SETUP_CONFIRM';
        }

        // --- Decision code (gunakan code dari CandidateInput yang kita expose)
        $decisionCode = (int) ($candidateRow['decisionCode'] ?? 0);
        if ($decisionCode === 5) {
            $score += $this->points('decision_5'); $reasons[] = 'DECISION_5';
        } elseif ($decisionCode === 4) {
            $score += $this->points('decision_4'); $reasons[] = 'DECISION_4';
        }

        // --- Signal code (pattern/setup teknikal)
        $signalCode = (int) ($candidateRow['signalCode'] ?? 0);
        $signalWeights = (array) ($this->w['signal_weights'] ?? []);
        if ($signalCode > 0 && isset($signalWeights[$signalCode])) {
            $score += (float) $signalWeights[$signalCode];
            $reasons[] = 'SIGNAL_' . $signalCode;
        }

        // --- Volume label code (6/7 bagus)
        $volCode = (int) ($candidateRow['volumeLabelCode'] ?? 0);
        if ($volCode === 7) { // Strong Burst / Breakout
            $score += $this->points('volume_strong_burst'); $reasons[] = 'VOL_STRONG_BURST';
        } elseif ($volCode === 6) { // Volume Burst / Accumulation
            $score += $this->points('volume_burst'); $reasons[] = 'VOL_BURST';
        } elseif ($volCode === 5) { // Early Interest
            $score += $this->points('volume_early'); $reasons[] = 'VOL_EARLY';
        }

        // --- Expiry / freshness
        $expiryStatus = (string) ($candidateRow['expiryStatus'] ?? 'N/A');
        $age = $candidateRow['signalAgeDays'] ?? null;
        if ($expiryStatus === 'EXPIRED') {
            $score += $this->points('expired'); $reasons[] = 'EXPIRED';
        } elseif ($expiryStatus === 'AGING') {
            $score += $this->points('aging'); $reasons[] = 'AGING';
        } elseif ($expiryStatus === 'FRESH' && $age !== null) {
            $age = (int) $age;
            if ($age === 0) { $score += $this->points('fresh_age_0'); $reasons[] = 'AGE_0'; }
            elseif ($age === 1) { $score += $this->points('fresh_age_1'); $reasons[] = 'AGE_1'; }
            elseif ($age === 2) { $score += $this->points('fresh_age_2'); $reasons[] = 'AGE_2'; }
        }

        // --- Liquidity (valueEst)
        $valueEst = (float) ($candidateRow['valueEst'] ?? 0);
        if ($valueEst >= 5000000000) { $score += $this->points('liq_5b'); $reasons[] = 'LIQ_>=5B'; }
        elseif ($valueEst >= 2000000000) { $score += $this->points('liq_2b'); $reasons[] = 'LIQ_>=2B'; }
        elseif ($valueEst >= 1000000000) { $score += $this->points('liq_1b'); $reasons[] = 'LIQ_>=1B'; }

        // --- RR TP2 (fee-aware)
        $rr = (float) ($candidateRow['plan']['rrTp2'] ?? 0);
        if ($rr >= 2.0) { $score += $this->points('rr_ge_2'); $reasons[] = 'RR>=2.0'; }
        elseif ($rr >= 1.5) { $score += $this->points('rr_ge_15'); $reasons[] = 'RR>=1.5'; }
        elseif ($rr >= 1.2) { $score += $this->points('rr_ge_12'); $reasons[] = 'RR>=1.2'; }
        elseif ($rr > 0 && $rr < $this->rrMin) {
            $score += $this->points('penalty_rr_below_min', $this->points('rr_lt_min_penalty', -20));
            $reasons[] = 'RR_BELOW_MIN';
        } elseif ($rr <= 0) {
            $reasons[] = 'RR_UNKNOWN';
        }

        // --- Plan validity penalty (jika ada errors dari PlanValidator)
        $errors = (array) ($candidateRow['plan']['errors'] ?? []);
        if (!empty($errors)) {
            $score += $this->points('penalty_plan_invalid', -30);
            $reasons[] = 'PLAN_INVALID';
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    private function points(string $key, float $default = 0.0): float
    {
        return (float) ($this->w[$key] ?? $default);
    }
}
