<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistRepository;
use App\Repositories\MarketData\CandidateValidationRepository;
use App\Services\Trade\TradePlanService;
use App\Trade\Explain\ReasonCatalog;
use App\Trade\Watchlist\WatchlistAdviceService;
use App\Trade\Watchlist\WatchlistAllocationEngine;
use Carbon\Carbon;

class WatchlistService
{
    private WatchlistRepository $watchRepo;
    private TradePlanService $planService;
    private WatchlistPipelineFactory $factory;
    private CandidateValidationRepository $valRepo;
    private WatchlistAdviceService $advice;
    private WatchlistAllocationEngine $alloc;

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

        // instansiasi ringan, tidak ada IO
        $this->advice = new WatchlistAdviceService();
        $this->alloc = new WatchlistAllocationEngine();
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

            // alias score untuk spec
            $row['watchlist_score'] = (float) ($row['rankScore'] ?? 0);
            
            $row['bucket'] = $pipe->bucketer->bucket($row);

            // enrich pre-open advice (timing/checklist/confidence)
            // NOTE: market_regime belum ada datanya di build ini -> default neutral
            $dow = $this->dowFromDate($c->tradeDate);
            $advice = $this->advice->advise($c, $outcome, $setupStatus, $row, $dow, 'neutral');
            $row = array_merge($row, $advice);

            // attach spec aliases for plan
            $row['trade_plan'] = $row['plan'] ?? null;

            $rows[] = $row;
        }

        $pipe->sorter->sort($rows);
        
        $grouped = $pipe->grouper->group($rows);
        $eodDate = $rows[0]['trade_date'] ?? $rows[0]['tradeDate'] ?? null;
        $dow = $eodDate ? $this->dowFromDate((string)$eodDate) : '';

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

        // Build spec payload (WATCHLIST.md): recommended + candidates + meta
        $groups = $grouped['groups'] ?? ['top_picks' => [], 'watch' => [], 'avoid' => []];

        $recommended = $this->withRanks((array)($groups['top_picks'] ?? []));
        $candidatesAll = $this->withRanks($rows);

        // annotate group name for each candidate (based on bucket)
        $candidatesAll = array_map(function ($r) {
            $bucket = $r['bucket'] ?? null;
            $name = is_array($bucket) ? ($bucket['name'] ?? null) : $bucket;
            $group = 'avoid';
            if ($name === 'TOP_PICKS') $group = 'top_picks';
            elseif ($name === 'WATCH') $group = 'watch';
            $r['group'] = $group;
            return $r;
        }, $candidatesAll);

        $allocation = $this->alloc->decide($recommended, $dow ?: '');

        $meta = array_merge($grouped['meta'] ?? [], [
            'build_id' => (string) config('trade.build_id', 'v2.5.0'),
            'as_of_date' => $eodDate,
            'generated_at' => Carbon::now(config('trade.clock.timezone', 'Asia/Jakarta'))->toIso8601String(),
            'dow' => $dow,
            'market_regime' => 'neutral',
            'market_notes' => 'market_regime belum tersedia di build ini (default neutral).',
            'recommendation' => $allocation,
        ]);

        return [
            // legacy keys (tetap ada sampai UI siap)
            'eod_date' => $eodDate,
            'groups'   => $groups,
            'meta'     => $meta,

            // spec keys
            'recommended' => $recommended,
            'candidates' => $candidatesAll,
        ];
    }

    private function dowFromDate(string $tradeDate): string
    {
        try {
            $tz = (string) config('trade.clock.timezone', 'Asia/Jakarta');
            return Carbon::parse($tradeDate, $tz)->format('D');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param array<int,array> $rows
     * @return array<int,array>
     */
    private function withRanks(array $rows): array
    {
        $out = [];
        $i = 1;
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $r['rank'] = $i;
            $out[] = $r;
            $i++;
        }
        return $out;
    }
}
