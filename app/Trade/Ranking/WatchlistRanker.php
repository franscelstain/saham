<?php

namespace App\Trade\Ranking;

use App\Trade\Explain\ReasonCatalog;

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
     *  - reasons: array<int, array{code:string,message:string,severity:string,points:float,context?:array}>
     *  - codes: string[] (compat)
     */
    public function rank(array $candidateRow): array
    {
        if (!$this->enabled) {
            return [
                'score' => 0.0,
                'reasons' => [
                    [
                        'code' => 'RANKING_DISABLED',
                        'message' => ReasonCatalog::rankReasonMessage('RANKING_DISABLED', []),
                        'severity' => 'warn',
                        'points' => 0.0,
                    ]
                ],
                'codes' => ['RANKING_DISABLED'],
            ];
        }

        $score = 0.0;
        $reasons = [];
        $codes = [];

        // --- Setup status
        $setup = $candidateRow['setupStatus'] ?? null;
        if ($setup === 'SETUP_OK') {
            $pts = $this->points('setup_ok');
            $score += $pts;
            $this->addReason($reasons, $codes, 'SETUP_OK', 'pos', $pts);
        } elseif ($setup === 'SETUP_CONFIRM') {
            $pts = $this->points('setup_confirm');
            $score += $pts;
            $this->addReason($reasons, $codes, 'SETUP_CONFIRM', 'pos', $pts);
        }

        // --- Decision code
        $decisionCode = (int) ($candidateRow['decisionCode'] ?? 0);
        if ($decisionCode === 5) {
            $pts = $this->points('decision_5');
            $score += $pts;
            $this->addReason($reasons, $codes, 'DECISION_5', 'pos', $pts);
        } elseif ($decisionCode === 4) {
            $pts = $this->points('decision_4');
            $score += $pts;
            $this->addReason($reasons, $codes, 'DECISION_4', 'pos', $pts);
        }

        // --- Signal code (pattern/setup teknikal)
        $signalCode = (int) ($candidateRow['signalCode'] ?? 0);
        $signalWeights = (array) ($this->w['signal_weights'] ?? []);
        if ($signalCode !== 0) {
            // note: boleh negatif jika kamu set begitu di config
            $pts = (float) ($signalWeights[$signalCode] ?? 0.0);
            if ($pts !== 0.0) {
                $score += $pts;
                $sev = ($pts >= 0) ? 'pos' : 'neg';

                // code-nya biar spesifik, bukan "SIGNAL"
                $code = 'SIGNAL_' . $signalCode;

                $this->addReason($reasons, $codes, $code, $sev, $pts, [
                    'signalCode' => $signalCode,
                ]);
            } else {
                // optional: kalau mau tetap tampil alasan signal tanpa points
                // $this->addReason($reasons, $codes, 'SIGNAL_' . $signalCode, 'warn', 0.0, ['signalCode'=>$signalCode]);
            }
        }

        // --- Volume label code
        $volCode = (int) ($candidateRow['volumeLabelCode'] ?? 0);
        if ($volCode === 7) {
            $pts = $this->points('volume_strong_burst');
            $score += $pts;
            $this->addReason($reasons, $codes, 'VOL_STRONG_BURST', 'pos', $pts);
        } elseif ($volCode === 6) {
            $pts = $this->points('volume_burst');
            $score += $pts;
            $this->addReason($reasons, $codes, 'VOL_BURST', 'pos', $pts);
        } elseif ($volCode === 5) {
            $pts = $this->points('volume_early');
            $score += $pts;
            $this->addReason($reasons, $codes, 'VOL_EARLY', 'pos', $pts);
        }

        // --- Expiry / freshness
        $expiryStatus = (string) ($candidateRow['expiryStatus'] ?? 'N/A');
        $age = $candidateRow['signalAgeDays'] ?? null;

        if ($expiryStatus === 'EXPIRED') {
            $pts = $this->points('expired');
            $score += $pts;
            $this->addReason($reasons, $codes, 'EXPIRED', 'neg', $pts);
        } elseif ($expiryStatus === 'AGING') {
            $pts = $this->points('aging');
            $score += $pts;
            $this->addReason($reasons, $codes, 'AGING', 'warn', $pts);
        } elseif ($expiryStatus === 'FRESH' && $age !== null) {
            $age = (int) $age;
            if ($age === 0) {
                $pts = $this->points('fresh_age_0');
                $score += $pts;
                $this->addReason($reasons, $codes, 'AGE_0', 'pos', $pts, ['age' => $age]);
            } elseif ($age === 1) {
                $pts = $this->points('fresh_age_1');
                $score += $pts;
                $this->addReason($reasons, $codes, 'AGE_1', 'pos', $pts, ['age' => $age]);
            } elseif ($age === 2) {
                $pts = $this->points('fresh_age_2');
                $score += $pts;
                $this->addReason($reasons, $codes, 'AGE_2', 'pos', $pts, ['age' => $age]);
            }
        }

        // --- Liquidity (valueEst)
        $valueEst = (float) ($candidateRow['valueEst'] ?? 0);
        if ($valueEst >= 5000000000) {
            $pts = $this->points('liq_5b');
            $score += $pts;
            $this->addReason($reasons, $codes, 'LIQ_GE_5B', 'pos', $pts, ['valueEst' => $valueEst]);
        } elseif ($valueEst >= 2000000000) {
            $pts = $this->points('liq_2b');
            $score += $pts;
            $this->addReason($reasons, $codes, 'LIQ_GE_2B', 'pos', $pts, ['valueEst' => $valueEst]);
        } elseif ($valueEst >= 1000000000) {
            $pts = $this->points('liq_1b');
            $score += $pts;
            $this->addReason($reasons, $codes, 'LIQ_GE_1B', 'pos', $pts, ['valueEst' => $valueEst]);
        }

        // --- RR TP2 (fee-aware)
        $rr = (float) ($candidateRow['plan']['rrTp2'] ?? 0);
        if ($rr >= 2.0) {
            $pts = $this->points('rr_ge_2');
            $score += $pts;
            $this->addReason($reasons, $codes, 'RR_GE_20', 'pos', $pts, ['rrTp2' => $rr]);
        } elseif ($rr >= 1.5) {
            $pts = $this->points('rr_ge_15');
            $score += $pts;
            $this->addReason($reasons, $codes, 'RR_GE_15', 'pos', $pts, ['rrTp2' => $rr]);
        } elseif ($rr >= 1.2) {
            $pts = $this->points('rr_ge_12');
            $score += $pts;
            $this->addReason($reasons, $codes, 'RR_GE_12', 'pos', $pts, ['rrTp2' => $rr]);
        } elseif ($rr > 0 && $rr < $this->rrMin) {
            // penalty_rr_below_min sudah kamu bikin negatif via factory (recommended)
            $pen = $this->points('penalty_rr_below_min', $this->points('rr_lt_min_penalty', -20));
            $score += $pen;
            $this->addReason($reasons, $codes, 'RR_BELOW_MIN', 'neg', $pen, [
                'rrTp2' => $rr,
                'min' => $this->rrMin,
            ]);
        } elseif ($rr <= 0) {
            $this->addReason($reasons, $codes, 'RR_UNKNOWN', 'warn', 0.0);
        }

        // --- Plan validity penalty (jika ada errors dari PlanValidator)
        $errors = (array) ($candidateRow['plan']['errors'] ?? []);
        if (!empty($errors)) {
            // penalty_plan_invalid sudah kamu bikin negatif via factory (recommended)
            $pen = $this->points('penalty_plan_invalid', -30);
            $score += $pen;
            $this->addReason($reasons, $codes, 'PLAN_INVALID', 'neg', $pen, [
                'errors' => $errors,
            ]);
        }

        $debug = (bool)($this->w['debug_reasons'] ?? false);

        return [
            'score' => $score,
            'codes' => $codes,
            'reasons' => $debug ? $reasons : [], // atau unset saja
        ];
    }

    private function points(string $key, float $default = 0.0): float
    {
        return (float) ($this->w[$key] ?? $default);
    }

    /**
     * @param array<int,array> $reasons
     * @param array<int,string> $codes
     */
    private function addReason(array &$reasons, array &$codes, string $code, string $severity, float $points, array $context = []): void
    {
        $codes[] = $code;

        $debug = (bool)($this->w['debug_reasons'] ?? false);
        if (!$debug) return;

        $item = [
            'code' => $code,
            'message' => ReasonCatalog::rankReasonMessage($code, $context),
            'severity' => $severity,
            'points' => (float) $points,
        ];
        if (!empty($context)) $item['context'] = $context;

        $reasons[] = $item;
    }
}
