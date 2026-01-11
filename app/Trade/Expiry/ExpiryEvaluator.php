<?php

namespace App\Trade\Expiry;

class ExpiryEvaluator
{
    private bool $enabled;
    private int $maxAgeDays;
    private int $agingFromDays;
    private array $applyToDecisions;

    public function __construct(
        bool $enabled,
        int $maxAgeDays,
        int $agingFromDays,
        array $applyToDecisions
    ) {
        $this->enabled = $enabled;
        $this->maxAgeDays = $maxAgeDays;
        $this->agingFromDays = $agingFromDays;
        $this->applyToDecisions = $applyToDecisions;
    }

    /**
     * Return structure yang stabil buat UI + ranking.
     */
    public function evaluate($candidate): array
    {
        if (!$this->enabled) {
            return [
                'expiryStatus' => 'N/A',
                'isExpired' => false,
            ];
        }

        // Wajib punya decisionCode untuk menentukan apply/skip
        $decisionCode = $candidate->decisionCode ?? null;
        if ($decisionCode === null || !in_array((int) $decisionCode, $this->applyToDecisions, true)) {
            return [
                'expiryStatus' => 'N/A',
                'isExpired' => false,
            ];
        }

        $age = $candidate->signalAgeDays ?? null;
        if ($age === null) {
            return [
                'expiryStatus' => 'N/A',
                'isExpired' => false,
            ];
        }

        $age = (int) $age;

        if ($age > $this->maxAgeDays) {
            return [
                'expiryStatus' => 'EXPIRED',
                'isExpired' => true,
            ];
        }

        if ($age >= $this->agingFromDays) {
            return [
                'expiryStatus' => 'AGING',
                'isExpired' => false,
            ];
        }

        return [
            'expiryStatus' => 'FRESH',
            'isExpired' => false,
        ];
    }
}
