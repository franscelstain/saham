<?php

namespace App\DTO\Watchlist\Scorecard;

class EligibilityCheckDto
{
    /** @param EligibilityResultDto[] $results */
    public function __construct(
        public string $policy,
        public string $tradeDate,
        public string $execDate,
        public string $checkedAt,
        public string $checkpoint,
        public array $results,
        public ?string $defaultRecommendationTicker,
        public ?string $defaultRecommendationWhy,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'policy' => $this->policy,
            'trade_date' => $this->tradeDate,
            'exec_trade_date' => $this->execDate,
            'checked_at' => $this->checkedAt,
            'checkpoint' => $this->checkpoint,
            'results' => array_map(fn(EligibilityResultDto $r) => $r->toArray(), $this->results),
            'default_recommendation' => $this->defaultRecommendationTicker
                ? ['ticker' => $this->defaultRecommendationTicker, 'why' => (string)$this->defaultRecommendationWhy]
                : null,
        ];
    }
}
