<?php

namespace App\Trade\Ranking;

class WatchlistBucketer
{
    private int $topMin;
    private int $watchMin;
    private float $rrMin;

    public function __construct(int $topMin, int $watchMin, float $rrMin)
    {
        $this->topMin = $topMin;
        $this->watchMin = $watchMin;
        $this->rrMin = $rrMin;
    }

    public function bucket(array $row): string
    {
        $score = (float)($row['rankScore'] ?? 0);
        $isExpired = (bool)($row['isExpired'] ?? false);

        $liqBucket = (string)($row['liq_bucket'] ?? '');

        // Rule keras: sinyal yang sudah EXPIRED tidak boleh masuk WATCH maupun TOP_PICKS.
        // Ini mencegah output watchlist jadi noisy dan kontradiktif.
        if ($isExpired) {
            return 'AVOID';
        }

        $errors = $row['plan']['errors'] ?? [];
        $hasErrors = !empty($errors);

        $rrTp2 = (float)($row['plan']['rrTp2'] ?? 0);

        // Liquidity gating: bucket C / unknown tidak boleh masuk TOP_PICKS (boleh tetap WATCH/AVOID).
        $liqOkForTop = ($liqBucket === 'A' || $liqBucket === 'B');

        if ($liqOkForTop && !$hasErrors && $rrTp2 >= $this->rrMin && $score >= $this->topMin) {
            return 'TOP_PICKS';
        }

        if ($score >= $this->watchMin) {
            return 'WATCH';
        }

        return 'AVOID';
    }
}
