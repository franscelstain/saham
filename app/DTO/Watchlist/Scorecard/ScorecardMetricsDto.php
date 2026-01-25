<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Aggregated metrics computed for scorecard.
 * PHP 7.3 compatible.
 */
class ScorecardMetricsDto
{
    /** @var float|null */
    public $feasibleRate;
    /** @var float|null */
    public $fillRate;
    /** @var float|null */
    public $outcomeRate;
    /** @var array<string,mixed> */
    public $payload;

    /**
     * @param float|null $feasibleRate
     * @param float|null $fillRate
     * @param float|null $outcomeRate
     * @param array<string,mixed> $payload
     */
    public function __construct($feasibleRate, $fillRate, $outcomeRate, array $payload)
    {
        $this->feasibleRate = ($feasibleRate === null) ? null : (float)$feasibleRate;
        $this->fillRate = ($fillRate === null) ? null : (float)$fillRate;
        $this->outcomeRate = ($outcomeRate === null) ? null : (float)$outcomeRate;
        $this->payload = $payload;
    }
}
