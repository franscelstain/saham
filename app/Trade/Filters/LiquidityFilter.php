<?php

namespace App\Trade\Filters;

use App\DTO\Trade\RuleResult;
use App\DTO\Watchlist\CandidateInput;

class LiquidityFilter
{
    /**
     * Candidate-level liquidity gating.
     *
     * We prefer dv20 + liq_bucket (computed from the prior 20 trading days; exclude today).
     * Bucket C is allowed to pass as a *candidate* (so it can show up in WATCH/AVOID),
     * but TOP_PICKS is gated separately by WatchlistBucketer (A/B only).
     */
    private float $minDv20;

    /** @var array<int,string> */
    private array $allowedBuckets;

    /**
     * @param float $minDv20 minimal dv20 to keep as candidate (0 = allow all buckets except U/unknown)
     * @param array<int,string> $allowedBuckets allowed buckets for candidate (default A/B/C)
     */
    public function __construct(float $minDv20 = 0.0, array $allowedBuckets = ['A', 'B', 'C'])
    {
        $this->minDv20 = $minDv20;
        $this->allowedBuckets = $allowedBuckets;
    }

    public function check(CandidateInput $c): RuleResult
    {
        $bucket = strtoupper(trim((string)($c->liqBucket ?? '')));
        $dv20 = (float)($c->dv20 ?? 0.0);

        if ($bucket === '' || $bucket === 'U') {
            return new RuleResult(false, 'LIQ_UNKNOWN', 'Likuiditas tidak diketahui / terlalu rendah');
        }

        if (!in_array($bucket, $this->allowedBuckets, true)) {
            return new RuleResult(false, 'LIQ_DISALLOWED', 'Likuiditas bucket tidak diizinkan');
        }

        if ($dv20 < $this->minDv20) {
            return new RuleResult(false, 'LIQ_DV20_TOO_LOW', 'DV20 di bawah minimum');
        }

        return new RuleResult(true, 'LIQ_OK_' . $bucket, 'Likuiditas bucket ' . $bucket . ' (dv20 OK)');
    }
}
