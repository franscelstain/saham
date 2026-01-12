<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistRepository;
use App\Services\Trade\TradePlanService;
use App\Trade\Explain\ReasonCatalog;

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
        
        $grouped = $pipe->grouper->group($rows);
        $eodDate = $rows[0]['tradeDate'] ?? null;

        $grouped['meta'] = array_merge($grouped['meta'] ?? [], [
            'top_picks_max'   => (int) config('trade.watchlist.top_picks_max', 5),
            'rank_reason_catalog' => ReasonCatalog::rankReasonCatalog(),
        ]);

        return [
            'eod_date' => $eodDate,
            'groups'   => $grouped['groups'] ?? ['top_picks' => [], 'watch' => [], 'avoid' => []],
            'meta'     => $grouped['meta'] ?? [],
        ];
    }
}
