<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Eligibility check result for one run at one checkpoint.
 * PHP 7.3 compatible.
 */
class EligibilityCheckDto
{
    /** @var string */
    public $policy;
    /** @var string */
    public $tradeDate;
    /** @var string */
    public $execDate;
    /** @var string */
    public $checkedAt;
    /** @var string */
    public $checkpoint;
    /** @var EligibilityResultDto[] */
    public $results;
    /** @var string|null */
    public $defaultRecommendationTicker;
    /** @var string|null */
    public $defaultRecommendationWhy;

    /**
     * @param string $policy
     * @param string $tradeDate
     * @param string $execDate
     * @param string $checkedAt
     * @param string $checkpoint
     * @param EligibilityResultDto[] $results
     * @param string|null $defaultTicker
     * @param string|null $defaultWhy
     */
    public function __construct($policy, $tradeDate, $execDate, $checkedAt, $checkpoint, array $results, $defaultTicker, $defaultWhy)
    {
        $this->policy = (string)$policy;
        $this->tradeDate = (string)$tradeDate;
        $this->execDate = (string)$execDate;
        $this->checkedAt = (string)$checkedAt;
        $this->checkpoint = (string)$checkpoint;
        $this->results = array_values($results);
        $this->defaultRecommendationTicker = ($defaultTicker === null || $defaultTicker === '') ? null : (string)$defaultTicker;
        $this->defaultRecommendationWhy = ($defaultWhy === null || $defaultWhy === '') ? null : (string)$defaultWhy;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        $results = [];
        foreach ($this->results as $r) {
            if ($r instanceof EligibilityResultDto) $results[] = $r->toArray();
        }

        $default = null;
        if ($this->defaultRecommendationTicker !== null) {
            $default = ['ticker' => $this->defaultRecommendationTicker, 'why' => (string)$this->defaultRecommendationWhy];
        }

        return [
            'policy' => $this->policy,
            'trade_date' => $this->tradeDate,
            'exec_trade_date' => $this->execDate,
            'checked_at' => $this->checkedAt,
            'checkpoint' => $this->checkpoint,
            'results' => $results,
            'default_recommendation' => $default,
        ];
    }
}
