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
        // optional: capital (buying power) untuk hitung lots di meta.recommendations
        $capital = null;
        try {
            $q = request()->query('capital');
            if ($q !== null && $q !== '') {
                $capital = (float) preg_replace('/[^0-9.]/', '', (string)$q);
                if ($capital <= 0) $capital = null;
            }
        } catch (\Throwable $e) {
            $capital = null;
        }

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
            // keep internal key 'plan' for ranker/bucketer, output akan dinormalisasi di akhir
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

        // groups-only output (single source of truth)
        $groupsInternal = $grouped['groups'] ?? ['top_picks' => [], 'watch' => [], 'avoid' => []];

        // normalize rows -> snake_case only, no redundant keys
        $groups = [
            'top_picks' => $this->normalizeRows((array)($groupsInternal['top_picks'] ?? [])),
            'watch'     => $this->normalizeRows((array)($groupsInternal['watch'] ?? [])),
            'avoid'     => $this->normalizeRows((array)($groupsInternal['avoid'] ?? [])),
        ];

        // meta.recommendations = action plan (allocation + timing + slices + optional lots)
        $recommendations = $this->alloc->buildActionPlan((array)($groups['top_picks'] ?? []), $dow ?: '', $capital);

        $meta = array_merge($grouped['meta'] ?? [], [
            'build_id' => (string) config('trade.build_id', 'v2.5.2'),
            'as_of_date' => $eodDate,
            'generated_at' => Carbon::now(config('trade.clock.timezone', 'Asia/Jakarta'))->toIso8601String(),
            'dow' => $dow,
            'market_regime' => 'neutral',
            'market_notes' => 'market_regime belum tersedia di build ini (default neutral).',
            'recommendations' => $recommendations,
        ]);

        return [
            // keys utama untuk UI
            'eod_date' => $eodDate,
            'groups'   => $groups,
            'meta'     => $meta,
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
     * Normalize rows for JSON output: snake_case only, no redundant fields.
     * @param array<int,array> $rows
     * @return array<int,array>
     */
    private function normalizeRows(array $rows): array
    {
        $out = [];
        $i = 1;
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $out[] = $this->normalizeRow($r, $i);
            $i++;
        }
        return $out;
    }

    private function normalizeRow(array $r, int $rank): array
    {
        $rawScore = (float)($r['rankScore'] ?? 0);
        $scoreUi = $rawScore;
        if ($scoreUi < 0) $scoreUi = 0;
        if ($scoreUi > 100) $scoreUi = 100;

        $bucket = $r['bucket'] ?? null;
        if (is_array($bucket)) $bucket = $bucket['name'] ?? $bucket['bucket'] ?? null;

        $tradePlan = $r['plan'] ?? ($r['trade_plan'] ?? null);
        if (!is_array($tradePlan)) $tradePlan = null;

        $row = [
            'rank' => $rank,
            'ticker_id' => (int)($r['ticker_id'] ?? $r['tickerId'] ?? 0),
            'ticker' => (string)($r['ticker'] ?? $r['code'] ?? ''),
            'company_name' => (string)($r['company_name'] ?? $r['name'] ?? ''),

            'trade_date' => (string)($r['trade_date'] ?? $r['tradeDate'] ?? ''),

            'open' => $r['open'] ?? null,
            'high' => $r['high'] ?? null,
            'low'  => $r['low'] ?? null,
            'close'=> $r['close'] ?? null,
            'volume' => $r['volume'] ?? null,
            'value_est' => $r['value_est'] ?? ($r['valueEst'] ?? null),

            'ma20' => $r['ma20'] ?? null,
            'ma50' => $r['ma50'] ?? null,
            'ma200' => $r['ma200'] ?? null,
            'rsi14' => $r['rsi'] ?? ($r['rsi14'] ?? null),

            'vol_sma20' => $r['vol_sma20'] ?? null,
            'vol_ratio' => $r['vol_ratio'] ?? null,
            'prev_close' => $r['prev_close'] ?? null,
            'gap_pct' => $r['gap_pct'] ?? null,
            'atr_pct' => $r['atr_pct'] ?? null,
            'range_pct' => $r['range_pct'] ?? null,

            'decision_code' => $r['decision_code'] ?? ($r['decisionCode'] ?? null),
            'signal_code' => $r['signal_code'] ?? ($r['signalCode'] ?? null),
            'volume_label_code' => $r['volume_label_code'] ?? ($r['volumeLabelCode'] ?? null),

            'decision_label' => $r['decision_label'] ?? ($r['decisionLabel'] ?? null),
            'signal_label' => $r['signal_label'] ?? ($r['signalLabel'] ?? null),
            'volume_label' => $r['volume_label'] ?? ($r['volumeLabel'] ?? null),

            'setup_status' => (string)($r['setup_status'] ?? ($r['setupStatus'] ?? '')),
            'passes_hard_filter' => (bool)($r['passes_hard_filter'] ?? true),

            'expiry_status' => (string)($r['expiry_status'] ?? ($r['expiryStatus'] ?? 'N/A')),
            'is_expired' => (bool)($r['is_expired'] ?? ($r['isExpired'] ?? false)),
            'signal_age_days' => $r['signal_age_days'] ?? ($r['signalAgeDays'] ?? null),
            'signal_first_seen_date' => $r['signal_first_seen_date'] ?? ($r['signalFirstSeenDate'] ?? null),

            'rank_score' => $rawScore,
            'watchlist_score' => $scoreUi,
            'rank_reason_codes' => $r['rankReasonCodes'] ?? [],

            'bucket' => (string)($bucket ?? 'AVOID'),

            // advice fields
            'setup_type' => $r['setup_type'] ?? null,
            'confidence' => $r['confidence'] ?? null,
            'entry_windows' => $r['entry_windows'] ?? [],
            'avoid_windows' => $r['avoid_windows'] ?? [],
            'entry_style' => $r['entry_style'] ?? null,
            'size_multiplier' => $r['size_multiplier'] ?? null,
            'max_positions_today' => $r['max_positions_today'] ?? null,
            'reason_codes' => $r['reason_codes'] ?? [],
            'timing_summary' => $r['timing_summary'] ?? '',
            'pre_buy_checklist' => $r['pre_buy_checklist'] ?? [],

            'trade_plan' => $tradePlan,
        ];

        // optional verbose reasons
        if (isset($r['rankReasons'])) {
            $row['rank_reasons'] = $r['rankReasons'];
        }

        // optional validator badge
        if (isset($r['validator'])) {
            $row['validator'] = $r['validator'];
        }

        return $row;
    }
}
