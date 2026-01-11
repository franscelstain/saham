<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistRepository;
use App\Services\Trade\TradePlanService;

class WatchlistService
{
    private WatchlistRepository $watchRepo;
    private TradePlanService $planService;
    private WatchlistPipelineFactory $factory;

    public function __construct(
        WatchlistRepository $watchRepo, 
        TradePlanService $planService,
        WatchlistPipelineFactory $factory
    ) {
        $this->watchRepo = $watchRepo;
        $this->planService = $planService;
        $this->factory = $factory;
    }

    public function preopenRaw(): array
    {
        $pipe = $this->factory->makePreopen();
        $raw = $this->watchRepo->getEodCandidates();
        $selected = $pipe->selector->select($raw);

        $rows = [];

        foreach ($selected as $item) {
            $c = $item['candidate'];
            $outcome = $item['outcome'];
            $setupStatus = $item['setupStatus'];

            $plan = $this->planService->buildFromCandidate($c);
            $expiry = $pipe->expiry->evaluate($c);

            $row = $pipe->presenter->baseRow($c, $outcome, $setupStatus, $plan, $expiry);
            $rank = $pipe->ranker->rank($row);
            $row = $pipe->presenter->attachRank($row, $rank);
            
            $row['bucket'] = $pipe->bucketer->bucket($row);

            $rows[] = $row;
        }

        $pipe->sorter->sort($rows);

        return $rows;
    }
}
