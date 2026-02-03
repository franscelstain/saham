<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Eligibility check output.
 * Output schema is LOCKED by docs/watchlist/scorecard.md.
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

    /** @var EligibilityResultDto[] */
    public $results;

    /** @var string|null */
    public $defaultRecommendationTicker;
    /** @var string|null */
    public $defaultRecommendationWhy;

    /** @var string|null */
    public $planRefStrategyRunId;

    public function __construct(
        $policy,
        $tradeDate,
        $execDate,
        $checkedAt,
        array $results,
        $defaultRecommendationTicker = null,
        $defaultRecommendationWhy = null,
        $planRefStrategyRunId = null
    ) {
        $this->policy = (string)$policy;
        $this->tradeDate = (string)$tradeDate;
        $this->execDate = (string)$execDate;
        $this->checkedAt = (string)$checkedAt;
        $this->results = array_values($results);
        $this->defaultRecommendationTicker = $defaultRecommendationTicker === null ? null : (string)$defaultRecommendationTicker;
        $this->defaultRecommendationWhy = $defaultRecommendationWhy === null ? null : (string)$defaultRecommendationWhy;
        $this->planRefStrategyRunId = $planRefStrategyRunId === null ? null : (string)$planRefStrategyRunId;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        $rows = [];
        foreach ($this->results as $r) {
            if ($r instanceof EligibilityResultDto) $rows[] = $r->toArray();
        }

        $a = [
            'checked_at' => $this->checkedAt,
            'policy' => $this->policy,
            'trade_date' => $this->tradeDate,
            'plan_ref' => [
                'strategy_run_id' => $this->planRefStrategyRunId,
            ],
            'results' => array_values($rows),
        ];

        if ($this->defaultRecommendationTicker !== null && $this->defaultRecommendationTicker !== '') {
            $a['default_recommendation'] = [
                'ticker_code' => strtoupper(trim($this->defaultRecommendationTicker)),
                'why' => $this->defaultRecommendationWhy,
            ];
        }

        return $a;
    }
}
