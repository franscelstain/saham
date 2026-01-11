<?php

namespace App\Services\Watchlist;

use App\DTO\Watchlist\CandidateInput;
use App\DTO\Watchlist\FilterOutcome;
use App\Trade\Filters\WatchlistHardFilter;
use App\Trade\Signals\SetupClassifier;

/**
 * SRP: mengambil raw kandidat -> kandidat yang lolos hard filter + setup status.
 * Tidak mengurus planning, expiry, ranking, atau presentation.
 */
class WatchlistSelector
{
    private WatchlistHardFilter $filter;
    private SetupClassifier $classifier;

    public function __construct(WatchlistHardFilter $filter, SetupClassifier $classifier)
    {
        $this->filter = $filter;
        $this->classifier = $classifier;
    }

    /**
     * @param CandidateInput[] $candidates
     * @return array<int, array{candidate: CandidateInput, outcome: FilterOutcome, setupStatus: string}>
     */
    public function select(array $candidates): array
    {
        $selected = [];

        foreach ($candidates as $c) {
            $outcome = $this->filter->evaluate($c);
            if (!$outcome->eligible) {
                continue;
            }

            $selected[] = [
                'candidate' => $c,
                'outcome' => $outcome,
                'setupStatus' => $this->classifier->classify($c),
            ];
        }

        return $selected;
    }
}
