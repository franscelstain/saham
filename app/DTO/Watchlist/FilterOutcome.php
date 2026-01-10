<?php

namespace App\DTO\Watchlist;

use App\DTO\Trade\RuleResult;

class FilterOutcome
{
    public bool $eligible;
    /** @var RuleResult[] */
    public array $results;

    public function __construct(bool $eligible, array $results)
    {
        $this->eligible = $eligible;
        $this->results = $results;
    }

    public function failed(): array
    {
        return array_values(array_filter($this->results, fn($r) => !$r->pass));
    }

    public function passed(): array
    {
        return array_values(array_filter($this->results, fn($r) => $r->pass));
    }
}
