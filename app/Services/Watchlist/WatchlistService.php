<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistRepository;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\MarketData\CandidateValidationRepository;
use App\Repositories\MarketBreadthRepository;
use App\Repositories\WatchlistPersistenceRepository;
use App\Trade\Watchlist\WatchlistMarketContextService;
use App\Services\Trade\TradePlanService;
use App\Trade\Explain\ReasonCatalog;
use App\Trade\Watchlist\WatchlistAdviceService;
use App\Trade\Watchlist\WatchlistAllocationEngine;
use Carbon\Carbon;

class WatchlistService
{
    private WatchlistRepository $watchRepo;
    private MarketCalendarRepository $calRepo;
    private TradePlanService $planService;
    private WatchlistPipelineFactory $factory;
    private CandidateValidationRepository $valRepo;
    private MarketBreadthRepository $breadthRepo;
    private WatchlistMarketContextService $marketCtx;
    private WatchlistAdviceService $advice;
    private WatchlistAllocationEngine $alloc;
    private WatchlistPersistenceRepository $persistRepo;

    public function __construct(
        WatchlistRepository $watchRepo,
        MarketCalendarRepository $calRepo,
        TradePlanService $planService,
        WatchlistPipelineFactory $factory,
        CandidateValidationRepository $valRepo,
        MarketBreadthRepository $breadthRepo,
        WatchlistPersistenceRepository $persistRepo
    ) {
        $this->watchRepo = $watchRepo;
        $this->calRepo = $calRepo;
        $this->planService = $planService;
        $this->factory = $factory;
        $this->valRepo = $valRepo;
        $this->breadthRepo = $breadthRepo;
        $this->persistRepo = $persistRepo;
        $this->marketCtx = new WatchlistMarketContextService();

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

        // Pull candidates from the latest date where BOTH indicators and canonical OHLC are available.
        // This avoids emitting BUY recommendations on partial / non-canonical coverage.
        $raw = $this->watchRepo->getEodCandidates();

        $eodBasisDate = (!empty($raw) && is_object($raw[0]) && property_exists($raw[0], "tradeDate")) ? (string)$raw[0]->tradeDate : null;

        // market breadth snapshot -> regime (risk_on / neutral / risk_off)
        $mrEnabled = (bool) config('trade.watchlist.market_regime_enabled', true);
        $mrThresholds = (array) config('trade.watchlist.market_regime_thresholds', []);
        $riskOn = (array) ($mrThresholds['risk_on'] ?? []);
        $riskOff = (array) ($mrThresholds['risk_off'] ?? []);

        $marketSnapshot = [
            'trade_date' => (string)($eodBasisDate ?: ''),
            'sample_size' => 0,
            'pct_above_ma200' => null,
            'pct_ma_alignment' => null,
            'avg_rsi14' => null,
        ];

        if ($mrEnabled && $eodBasisDate) {
            try {
                $marketSnapshot = $this->breadthRepo->snapshot($eodBasisDate);
            } catch (\Throwable $e) {
                // keep defaults
            }
        }

        $marketRegime = 'neutral';
        $marketNotes = $mrEnabled
            ? 'Breadth snapshot tidak tersedia (fallback neutral).'
            : 'Market regime disabled (forced neutral).';

        if ($mrEnabled) {
            $classified = $this->marketCtx->classify($marketSnapshot, $riskOn, $riskOff);
            $marketRegime = (string)($classified['regime'] ?? 'neutral');
            $marketNotes = (string)($classified['notes'] ?? '');
        }

        $market = [
            'market_regime' => $marketRegime,
            'market_notes' => $marketNotes,
            'market_snapshot' => $marketSnapshot,
        ];

        $selected = $pipe->selector->select($raw);

        $rows = [];

        foreach ($selected as $item) {
            $c = $item['candidate'];
            $outcome = $item['outcome'];
            $setupStatus = $item['setupStatus'];

            $plan = $this->planService->buildFromCandidate($c);
            $expiry = $pipe->expiry->evaluate($c);

            $row = $pipe->presenter->baseRow($c, $outcome, $setupStatus, $plan, $expiry);
            $rank = $pipe->ranker->rank($row, $marketRegime);
            $row = $pipe->presenter->attachRank($row, $rank);

            // alias score untuk spec
            $row['watchlist_score'] = (float) ($row['rankScore'] ?? 0);
            
            $row['bucket'] = $pipe->bucketer->bucket($row);

            // enrich pre-open advice (timing/checklist/confidence)
            $dow = $this->dowFromDate($c->tradeDate);
            $advice = $this->advice->advise($c, $outcome, $setupStatus, $row, $dow, $marketRegime);
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

        $tz = (string) config('trade.clock.timezone', 'Asia/Jakarta');
        $generatedAt = Carbon::now($tz);
        $today = $generatedAt->toDateString();

        $stale = $this->computeEodStaleness($eodDate ? (string) $eodDate : null, $today, $tz);
        $maxStale = (int) config('trade.watchlist.max_stale_trading_days', 1);

        // Coverage gate (canonical readiness)
        $coverage = null;
        $minCanonPct = (float) config('trade.watchlist.min_canonical_coverage_pct', 85);
        $minIndPct = (float) config('trade.watchlist.min_indicator_coverage_pct', 85);
        $isCoverageOk = true;
        if ($eodDate) {
            try {
                $coverage = $this->watchRepo->coverageSnapshot((string) $eodDate);
                $canonPct = (float) ($coverage['canonical_coverage_pct'] ?? 0);
                $indPct = (float) ($coverage['indicators_coverage_pct'] ?? 0);

                // must have enough canonical & indicator rows to be considered ready
                if ($canonPct < $minCanonPct || $indPct < $minIndPct) {
                    $isCoverageOk = false;
                }
            } catch (\Throwable $e) {
                // if we cannot measure coverage, be conservative: block recommendations
                $isCoverageOk = false;
            }
        }

        // recommendations = action plan (allocation + timing + slices + optional lots)
        // Guardrails:
        // - If EOD data is stale beyond threshold, do not emit BUY recommendations.
        // - If market_regime is risk_off, emit NO_TRADE (avoid forcing entries on bad breadth).
        $warnings = [];
        if (!$isCoverageOk) {
            $warnings[] = 'EOD_NOT_READY';
        }
        if ($stale['trading_days_stale'] !== null && (int)$stale['trading_days_stale'] > $maxStale) {
            $warnings[] = 'EOD_STALE';
        }
        $blockOnRiskOff = (bool) config('trade.watchlist.market_regime_block_buy_on_risk_off', true);
        if ($mrEnabled && $blockOnRiskOff && $marketRegime === 'risk_off') {
            $warnings[] = 'MARKET_RISK_OFF';
        }

        if (!$isCoverageOk) {
            $recommendations = $this->buildNoTradeRecommendationsEodNotReady($coverage, $minCanonPct, $minIndPct);
        } elseif ($stale['trading_days_stale'] !== null && (int)$stale['trading_days_stale'] > $maxStale) {
            $recommendations = $this->buildNoTradeRecommendations($stale, $maxStale);
        } elseif ($mrEnabled && $blockOnRiskOff && $marketRegime === 'risk_off') {
            $recommendations = $this->buildNoTradeRecommendationsMarketRiskOff($market);
        } else {
            $recommendations = $this->alloc->buildActionPlan((array)($groups['top_picks'] ?? []), $dow ?: '', $capital);
        }

        $meta = array_merge($grouped['meta'] ?? [], [
            'generated_at' => $generatedAt->toIso8601String(),
            'dow' => $dow,
            'market_regime' => $marketRegime,
            'market_notes' => (string)($market['market_notes'] ?? ''),
            'market_snapshot' => $market['market_snapshot'] ?? null,
            'data_status' => [
                'eod_date' => $eodDate,
                'today' => $today,
                'trading_days_stale' => $stale['trading_days_stale'],
                'missing_trading_dates' => $stale['missing_trading_dates'],
                'max_allowed_trading_days_stale' => $maxStale,
                'coverage' => $coverage,
                'min_canonical_coverage_pct' => $minCanonPct,
                'min_indicator_coverage_pct' => $minIndPct,
            ],
            'warnings' => $warnings,
        ]);

        $payload = [
            // keys utama untuk UI
            'eod_date' => $eodDate,
            'groups'   => $groups,
            'meta'     => $meta,
            'recommendations' => $recommendations,
        ];

        // Persist snapshot + flattened candidates (optional; safe if migrations not yet run)
        $persistEnabled = (bool) config('trade.watchlist.persistence.enabled', true);
        if ($persistEnabled && $eodDate) {
            try {
                $dailyId = $this->persistRepo->saveDailySnapshot((string) $eodDate, $payload, 'pre_open');
                $this->persistRepo->saveCandidates($dailyId, (string) $eodDate, (array) $groups);
                $payload['meta']['persistence'] = [
                    'enabled' => true,
                    'watchlist_daily_id' => $dailyId,
                ];
            } catch (\Throwable $e) {
                $payload['meta']['persistence'] = [
                    'enabled' => true,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $payload;
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
     * Compute how stale the current EOD basis date is, measured in TRADING DAYS (not calendar days).
     *
     * trading_days_stale = number of trading days AFTER eod_date up to today (inclusive) according to market_calendar.
     * missing_trading_dates = list of those trading dates (for UI/debug).
     */
    private function computeEodStaleness(?string $eodDate, string $today, string $tz): array
    {
        if (!$eodDate) {
            return [
                'trading_days_stale' => null,
                'missing_trading_dates' => [],
            ];
        }

        try {
            $eod = Carbon::parse($eodDate, $tz)->toDateString();
        } catch (\Throwable $e) {
            return [
                'trading_days_stale' => null,
                'missing_trading_dates' => [],
            ];
        }

        if ($today <= $eod) {
            return [
                'trading_days_stale' => 0,
                'missing_trading_dates' => [],
            ];
        }

        $from = Carbon::parse($eod, $tz)->addDay()->toDateString();
        try {
            $dates = $this->calRepo->tradingDatesBetween($from, $today);
        } catch (\Throwable $e) {
            $dates = [];
        }

        return [
            'trading_days_stale' => count($dates),
            'missing_trading_dates' => array_values($dates),
        ];
    }

    /**
     * Build NO_TRADE recommendations payload when EOD basis is too stale.
     */
    private function buildNoTradeRecommendations(array $stale, int $maxStale): array
    {
        $days = $stale['trading_days_stale'] ?? null;
        $missing = $stale['missing_trading_dates'] ?? [];

        return [
            'mode' => 'NO_TRADE',
            'positions' => 0,
            'split_ratio' => '',
            'selected_strategy_id' => null,
            'strategies' => [],
            'buy_plan' => [],

            // defaults for UI (still present, but empty because trading is blocked)
            'entry_windows_default' => [],
            'avoid_windows_default' => [],
            'timing_summary_default' => 'NO_TRADE karena data EOD terlalu basi untuk dipakai hari ini.',
            'pre_buy_checklist_default' => [
                'Pastikan market data EOD sudah ter-update sampai trading day terakhir.',
                'Jangan entry berdasarkan data lama (hindari blind buy).',
            ],

            'reason' => 'EOD_STALE',
            'stale_trading_days' => $days,
            'max_allowed_trading_days_stale' => $maxStale,
            'missing_trading_dates' => $missing,
        ];
    }

    /**
     * Build NO_TRADE recommendations payload when canonical EOD coverage is not ready.
     * This prevents "blind buy" on partial market data.
     */
    private function buildNoTradeRecommendationsEodNotReady(?array $coverage, float $minCanonPct, float $minIndPct): array
    {
        $canonPct = $coverage['canonical_coverage_pct'] ?? null;
        $indPct = $coverage['indicators_coverage_pct'] ?? null;
        $tradeDate = $coverage['trade_date'] ?? null;

        $msg = 'NO_TRADE karena data EOD canonical belum siap / coverage masih kurang.';
        if ($tradeDate) {
            $msg .= ' (basis ' . $tradeDate . ')';
        }

        return [
            'mode' => 'NO_TRADE',
            'positions' => 0,
            'split_ratio' => '',
            'selected_strategy_id' => null,
            'strategies' => [],
            'buy_plan' => [],

            'entry_windows_default' => [],
            'avoid_windows_default' => [],
            'timing_summary_default' => $msg,
            'pre_buy_checklist_default' => [
                'Pastikan import EOD canonical (ticker_ohlc_daily) sudah lengkap untuk trading day terakhir.',
                'Jika ada missing data: jalankan pipeline market-data/import dan compute-eod terlebih dulu.',
                'Hindari entry berdasarkan sebagian data (blind buy).',
            ],

            'reason' => 'EOD_NOT_READY',
            'coverage' => $coverage,
            'min_canonical_coverage_pct' => $minCanonPct,
            'min_indicator_coverage_pct' => $minIndPct,
            'canonical_coverage_pct' => $canonPct,
            'indicators_coverage_pct' => $indPct,
        ];
    }

    /**
     * Build NO_TRADE recommendations payload when market breadth is risk-off.
     */
    private function buildNoTradeRecommendationsMarketRiskOff(array $market): array
    {
        $snap = $market['market_snapshot'] ?? null;

        return [
            'mode' => 'NO_TRADE',
            'positions' => 0,
            'split_ratio' => '',
            'selected_strategy_id' => null,
            'strategies' => [],
            'buy_plan' => [],

            'entry_windows_default' => [],
            'avoid_windows_default' => [],
            'timing_summary_default' => 'NO_TRADE karena market breadth sedang risk-off (peluang follow-through rendah).',
            'pre_buy_checklist_default' => [
                'Cek kondisi index dan breadth (pct_above_ma200, ma alignment, avg_rsi14).',
                'Tunggu market_regime kembali risk-on / setidaknya neutral sebelum entry.',
                'Kalau tetap entry: kecilkan size dan wajib konfirmasi (break/hold). Hindari blind buy.',
            ],

            'reason' => 'MARKET_RISK_OFF',
            'market_regime' => 'risk_off',
            'market_snapshot' => $snap,
        ];
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

            'score_breakdown' => $r['score_breakdown'] ?? ($r['scoreBreakdown'] ?? null),

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
