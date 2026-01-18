<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistRepository;
use App\Repositories\MarketData\CandidateValidationRepository;
use App\Services\Trade\TradePlanService;
use App\Trade\Explain\ReasonCatalog;

class WatchlistService
{
    private WatchlistRepository $watchRepo;
    private TradePlanService $planService;
    private WatchlistPipelineFactory $factory;
    private CandidateValidationRepository $valRepo;

    public function __construct(
        WatchlistRepository $watchRepo, 
        TradePlanService $planService,
        WatchlistPipelineFactory $factory,
        CandidateValidationRepository $valRepo
    ) {
        $this->watchRepo = $watchRepo;
        $this->planService = $planService;
        $this->factory = $factory;
        $this->valRepo = $valRepo;
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

        // Phase 7: attach cached validator result (EODHD) for recommended picks (top_picks).
        // No external API call here. Data is populated via market-data:validate-eod.
        if ($eodDate && !empty($grouped['groups']['top_picks'])) {
            $codes = [];
            foreach ((array) $grouped['groups']['top_picks'] as $r) {
                if (is_array($r) && !empty($r['code'])) $codes[] = (string) $r['code'];
            }

            try {
                $map = $this->valRepo->mapByDateAndCodes((string) $eodDate, $codes, 'EODHD');
            } catch (\Throwable $e) {
                $map = [];
            }

            if ($map) {
                $newTop = [];
                foreach ((array) $grouped['groups']['top_picks'] as $r) {
                    if (!is_array($r)) {
                        $newTop[] = $r;
                        continue;
                    }
                    $code = strtoupper((string) ($r['code'] ?? ''));
                    if ($code !== '' && isset($map[$code])) {
                        $r['validator'] = $map[$code];
                    }
                    $newTop[] = $r;
                }
                $grouped['groups']['top_picks'] = $newTop;
            }
        }

        $grouped['meta'] = array_merge($grouped['meta'] ?? [], [
            'top_picks_max'   => (int) config('trade.watchlist.top_picks_max', 5),
            'rank_reason_catalog' => ReasonCatalog::rankReasonCatalog(),
            'rank_reason_schema' => [
                'code',
                'message',
                'severity',
                'points',
                'context?',
            ],
        ]);

        return [
            'eod_date' => $eodDate,
            'groups'   => $grouped['groups'] ?? ['top_picks' => [], 'watch' => [], 'avoid' => []],
            'meta'     => $grouped['meta'] ?? [],
        ];
    }
}
