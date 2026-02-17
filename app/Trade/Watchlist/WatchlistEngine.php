<?php

namespace App\Trade\Watchlist;

use App\DTO\Watchlist\CandidateInput;
use App\Trade\Explain\LabelCatalog;
use App\Trade\Explain\ReasonCatalog;
use App\Repositories\DividendEventRepository;
use App\Repositories\IntradaySnapshotRepository;
use App\Repositories\MarketBreadthRepository;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\TickerStatusRepository;
use App\Repositories\WatchlistRepository;
use App\Trade\Pricing\FeeConfig;
use App\Trade\Pricing\TickRule;
use App\Trade\Support\TradeClockConfig;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Config\WatchlistPolicyConfig;
use App\Trade\Watchlist\Support\WatchlistScoreScale;
use App\Trade\Watchlist\Contracts\PolicyDocLocator;
use App\Trade\Watchlist\Contracts\PreopenContractValidator;
use App\DTO\Watchlist\Scorecard\CandidateDto;
use App\DTO\Watchlist\Scorecard\CandidateGuardsDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\DTO\Watchlist\Scorecard\LiveTickerDto;
use App\Trade\Watchlist\Scorecard\ExecutionEligibilityEvaluator;

/**
 * WatchlistEngine
 *
 * Single orchestrator that:
 * - loads EOD candidates + optional intraday/dividend/status/portfolio context
 * - applies policy rules (docs/watchlist/*)
 * - produces contract payload (docs/watchlist/watchlist.md)
 */
class WatchlistEngine
{
    private WatchlistRepository $watchRepo;
    private MarketBreadthRepository $breadthRepo;
    private MarketCalendarRepository $calRepo;
    private DividendEventRepository $divRepo;
    private IntradaySnapshotRepository $intraRepo;
    private TickerStatusRepository $statusRepo;
    private PortfolioPositionRepository $posRepo;

    private TickRule $tickRule;

    private PreopenContractValidator $preopenValidator;
    private SetupTypeClassifier $setupClassifier;

    private CandidateDerivedMetricsBuilder $derivedBuilder;
    private TradeClockConfig $clockCfg;
    private WatchlistPolicyConfig $cfg;
    private ScorecardConfig $scorecardCfg;

    private PolicyDocLocator $policyDocs;

    /** Defaults from docs/watchlist/watchlist.md Section 2.4 */
    private float $buyFeePct;
    private float $sellFeePct;
    private float $slippagePct;

    public function __construct(
        WatchlistRepository $watchRepo,
        MarketBreadthRepository $breadthRepo,
        MarketCalendarRepository $calRepo,
        DividendEventRepository $divRepo,
        IntradaySnapshotRepository $intraRepo,
        TickerStatusRepository $statusRepo,
        PortfolioPositionRepository $posRepo,
        TickRule $tickRule,
        FeeConfig $feeCfg,
        TradeClockConfig $clockCfg,
        WatchlistPolicyConfig $cfg,
        ScorecardConfig $scorecardCfg,
        PolicyDocLocator $policyDocs,
        CandidateDerivedMetricsBuilder $derivedBuilder
    ) {
        $this->watchRepo = $watchRepo;
        $this->breadthRepo = $breadthRepo;
        $this->calRepo = $calRepo;
        $this->divRepo = $divRepo;
        $this->intraRepo = $intraRepo;
        $this->statusRepo = $statusRepo;
        $this->posRepo = $posRepo;

        $this->tickRule = $tickRule;

        $this->clockCfg = $clockCfg;
        $this->cfg = $cfg;
        $this->scorecardCfg = $scorecardCfg;
        $this->policyDocs = $policyDocs;
        $this->derivedBuilder = $derivedBuilder;

        $this->preopenValidator = new PreopenContractValidator();
        $this->setupClassifier = new SetupTypeClassifier();

        $this->buyFeePct = $feeCfg->buyRate();
        $this->sellFeePct = $feeCfg->sellRate();
        $this->slippagePct = $feeCfg->slippageRate();
    }

    
    public function tickSize(float $price): int
    {
        return (int)$this->tickRule->tickSize(max(1.0, $price));
    }

    public function roundUp(float $price): float
    {
        return (float)$this->tickRule->roundUp($price);
    }

    public function roundDown(float $price): float
    {
        return (float)$this->tickRule->roundDown($price);
    }

    /**
     * Build watchlist payload.
     *
     * @param array{
     *   eod_date?:string|null,
     *   policy?:string|null,
     *   capital_idr?:int|float|string|null,
     *   risk_per_trade_pct?:int|float|string|null,
     *   now_ts?:string|null
     * } $opts
     */ 
    public function buildInternal(array $opts = []): array
    {
        $tz = $this->clockCfg->timezone();
        $nowTs = $opts['now_ts'] ?? null;
        try {
            $now = $nowTs ? new \DateTimeImmutable((string)$nowTs) : new \DateTimeImmutable('now', new \DateTimeZone($tz));
        } catch (\Throwable $e) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));
        }
        $now = $now->setTimezone(new \DateTimeZone($tz));

        $generatedAt = $now->format('c');
        $today = $now->format('Y-m-d');

        // as_of_trade_date (contract):
        // - before cutoff: previous trading day
        // - after cutoff AND EOD published/ready: today
        // - otherwise: previous trading day
        // Cutoff time for deciding as_of_trade_date.
        // Prefer explicit override, otherwise fall back to global EOD cutoff (trade.clock.eod_cutoff).
        $cutoffHms = (string)($this->cfg->eodCutoffTimeOverride() ?? '');
        if (trim($cutoffHms) === '') {
            $cutoffHms = $this->clockCfg->eodCutoffHms();
        }
        $asOfTradeDate = $this->computeAsOfTradeDate($now, $cutoffHms);

        // Requested policy can be explicit (e.g. WEEKLY_SWING).
        $requestedPolicy = strtoupper(trim((string)($opts['policy'] ?? '')));
        if ($requestedPolicy === '') {
            $requestedPolicy = strtoupper($this->cfg->policyDefault());
        }

        $allowedPolicies = \App\Trade\Watchlist\WatchlistPolicyCodes::all();
        if (!in_array($requestedPolicy, $allowedPolicies, true)) {
            $requestedPolicy = 'WEEKLY_SWING';
        }

        // trade_date (contract): date EOD used for scoring (usually <= as_of_trade_date).
        $eodDate = $opts['eod_date'] ?? null;
        if (!$eodDate) $eodDate = $this->watchRepo->getLatestCommonEodDate();
        if (!$eodDate) $eodDate = $asOfTradeDate;
        // clamp: don't let trade_date exceed as_of_trade_date (pre-cutoff, or publish not ready).
        if ((string)$eodDate > (string)$asOfTradeDate) $eodDate = $asOfTradeDate;

        // exec_trade_date (contract): usually next trading day after trade_date.
        $execTradeDate = $this->calRepo->nextTradingDay((string)$eodDate) ?? (string)$eodDate;

        $coverage = $this->watchRepo->coverageSnapshot((string)$eodDate);
        // Canonical readiness strictly for trade_date (contract meta.eod_canonical_ready)
        $eodCanonicalReady = $this->isEodReady($coverage);
        // Effective readiness for NEW ENTRY (can be further gated by stale rules)
        $eodReady = $eodCanonicalReady;

        // Market regime (breadth)
        $mrEnabled = (bool) $this->cfg->marketRegimeEnabled();
        $thresholds = (array) $this->cfg->marketRegimeThresholds();
        $riskOn = (array)($thresholds['risk_on'] ?? []);
        $riskOff = (array)($thresholds['risk_off'] ?? []);

        $marketSnapshot = [
            'trade_date' => (string)$eodDate,
            'sample_size' => 0,
            'pct_above_ma200' => null,
            'pct_ma_alignment' => null,
            'avg_rsi14' => null,
        ];

        if ($mrEnabled) {
            try {
                $marketSnapshot = $this->breadthRepo->snapshot((string)$eodDate);
            } catch (\Throwable $e) {
                // keep default
            }
        }

        $marketRegime = 'neutral';
        $marketNotes = $mrEnabled
            ? 'Breadth snapshot tidak tersedia (fallback neutral).'
            : 'Market regime disabled (forced neutral).';

        if ($mrEnabled) {
            $ctx = new \App\Trade\Watchlist\WatchlistMarketContextService();
            $classified = $ctx->classify($marketSnapshot, $riskOn, $riskOff);
            $marketRegime = (string)($classified['regime'] ?? 'neutral');
            $marketNotes = (string)($classified['notes'] ?? '');
        }

        // Session times (resolve open/close tokens)
        $session = $this->sessionForDate($execTradeDate);

        // NOTE: as_of_trade_date is computed once above.

        // Missing trading dates between trade_date and as_of_trade_date (where EOD not ready)
        $missingTradingDates = [];
        if ($asOfTradeDate && $eodDate) {
            $dates = $this->calRepo->tradingDatesBetween((string)$eodDate, (string)$asOfTradeDate);
            foreach ($dates as $d) {
                if ($d === (string)$eodDate) continue;
                try {
                    $cov = $this->watchRepo->coverageSnapshot($d);
                    if (!$this->isEodReady($cov)) $missingTradingDates[] = $d;
                } catch (\Throwable $e) {
                    $missingTradingDates[] = $d;
                }
            }
        }

        // EOD stale gate (docs/watchlist/watchlist.md: legacy mapping EOD_STALE → GL_EOD_STALE)
        // If trade_date is too far behind as_of_trade_date (in trading days), block NEW ENTRY.
        $eodStale = false;
        $maxStaleTd = $this->cfg->maxStaleTradingDays();
        if ($asOfTradeDate && $eodDate) {
            $tdDiff = $this->tradingDaysDiff((string)$eodDate, (string)$asOfTradeDate);
            if ($tdDiff > $maxStaleTd) {
                $eodStale = true;
                $eodReady = false; // block new entry even if trade_date itself is canonical-ready
            }
        }

        // Global locks (do NOT change selected policy; only disable NEW ENTRY / recommendations).
        $globalLockCodes = [];
        $notes = [];
        if ($marketNotes !== '') $notes[] = $marketNotes;

        if (!$eodCanonicalReady) {
            $globalLockCodes[] = 'GL_EOD_NOT_READY';
            $notes[] = 'EOD data belum ready (coverage canonical/indikator di bawah threshold).';
        }
        if ($eodStale) {
            $globalLockCodes[] = 'GL_EOD_STALE';
            $notes[] = 'EOD basis terlalu stale dibanding as_of_trade_date (trading-days lag > max).';
        }
        // NOTE: NO_TRADE is manual-only (docs/watchlist/policy/no_trade.md). Do not add auto-triggers here.

        // Load datasets used both by router & candidate rules
        $intradayByTicker = $this->intraRepo->snapshotsByTicker($execTradeDate);
        $w = $this->dividendWindow($execTradeDate);
        $divEventsByTicker = $this->divRepo->eventsByTickerInWindow((string)($w['from'] ?? ''), (string)($w['to'] ?? ''));
        $openPositions = $this->posRepo->openPositionsByTicker();
        $hasOpenPositions = !empty($openPositions);

        // Policy selection: do not override selection due to global locks.
        $policy = $this->selectPolicy($requestedPolicy, $divEventsByTicker, $intradayByTicker, $hasOpenPositions);

        // Policy doc presence gate (docs/watchlist/watchlist.md)
        // IMPORTANT: Do NOT downgrade selected policy to NO_TRADE just because
        // a doc file is missing/misnamed. Watchlist screening (groups) should still run.
        // Missing docs are surfaced as a global lock/note for audit, but do not change policy.
        if (!$this->policyDocExists($policy)) {
            $globalLockCodes[] = 'GL_POLICY_DOC_MISSING';
            $notes[] = 'Policy doc missing for selected policy: ' . $policy;
        }

        // NOTE: Watchlist/preopen focuses on new-entry planning; open-position management is handled by Portfolio.

        // Policy meta & timing
        $policyMeta = $this->policyMeta($policy, $opts, $execTradeDate);
        $timingGlobal = $this->buildTiming($policy, $execTradeDate, $policyMeta, $globalLockCodes);

        $entryWindowsRaw = $this->resolveWindows($timingGlobal['entry_windows'] ?? [], $session['open_time'], $session['close_time']);
        // Contract: subtract market breaks from entry windows (docs/watchlist/watchlist.md Section 1.3)
        $entryWindowsRaw = $this->subtractBreaks($entryWindowsRaw, (array)($session['breaks'] ?? []));
        $avoidWindows = $this->resolveWindows($timingGlobal['avoid_windows'] ?? [], $session['open_time'], $session['close_time']);

        // Contract: avoid_windows wins over entry_windows.
        // effective_entry_windows = entry_windows - avoid_windows (docs/watchlist/watchlist.md Section 1.3)
        $entryWindows = $this->subtractBreaks($entryWindowsRaw, $avoidWindows);

        // If there is no executable entry window for today, treat as global no-trade for strict contract consistency.
        if (empty($entryWindows) && empty($globalLockCodes) && $policy !== 'NO_TRADE') {
            $globalLockCodes[] = 'GL_NO_EXEC_WINDOW';
            $avoidWindows = [$session['open_time'] . '-' . $session['close_time']];
            $timingGlobal['trade_disabled'] = true;
            $timingGlobal['size_multiplier'] = 0.0;
            $timingGlobal['max_positions_today'] = 0;
        }

        // Load datasets for candidate rules
        $candidates = $this->watchRepo->getEodCandidates((string)$eodDate);

        // Ticker status is resolved STRICTLY on execTradeDate.
        // IMPORTANT: If there is no status data at all for this trade date, we still default to REGULAR
        // and we DO NOT spam per-ticker DEFAULT_ASSUMED reasons (docs/watchlist/watchlist.md 2.6).
        $statusByTicker = $this->statusRepo->statusByTickerOnDate((string)$execTradeDate);
        $hasAnyTickerStatus = !empty($statusByTicker);

        $rows = [];
        foreach ($candidates as $ciRaw) {
            // Repository implementations historically returned either DTOs or plain rows.
            // Normalize here to keep the derived-metrics pipeline strongly typed.
            $ci = ($ciRaw instanceof \App\DTO\Watchlist\CandidateInput)
                ? $ciRaw
                : new \App\DTO\Watchlist\CandidateInput((array)$ciRaw);

            $this->derivedBuilder->enrich($ci);
            // labels are part of output contract; keep mapping out of DTO (docs/DTO.md)
            $ci->decisionLabel = LabelCatalog::decision($ci->decisionCode);
            $ci->signalLabel = LabelCatalog::signal($ci->signalCode);
            $ci->volumeLabel = LabelCatalog::volumeLabel($ci->volumeLabelCode);
            $row = $this->buildCandidate(
                $ci,
                $policy,
                $execTradeDate,
                $now,
                $session,
                $statusByTicker,
                $hasAnyTickerStatus,
                $divEventsByTicker,
                $openPositions,
                $globalLockCodes
            );
            if ($row) $rows[] = $row;
        }

        // Sort deterministically per docs/watchlist/watchlist.md
        usort($rows, function($a, $b) {
            $sa = (float)($a['score_total'] ?? 0);
            $sb = (float)($b['score_total'] ?? 0);
            if ($sa !== $sb) return ($sa < $sb) ? 1 : -1;

            $da = (float)($a['derived']['dv20_idr'] ?? ($a['dv20'] ?? 0));
            $db = (float)($b['derived']['dv20_idr'] ?? ($b['dv20'] ?? 0));
            if ($da !== $db) return ($da < $db) ? 1 : -1;

            $atra = (float)($a['derived']['atr_pct'] ?? 999.0);
            $atrb = (float)($b['derived']['atr_pct'] ?? 999.0);
            if ($atra !== $atrb) return ($atra > $atrb) ? 1 : -1; // smaller ATR% ranks higher

            $tpa = (float)($a['derived']['tick_pct'] ?? 999.0);
            $tpb = (float)($b['derived']['tick_pct'] ?? 999.0);
            if ($tpa !== $tpb) return ($tpa > $tpb) ? 1 : -1; // smaller tick% ranks higher

            return strcmp((string)($a['ticker_code'] ?? ''), (string)($b['ticker_code'] ?? ''));
        });

        // Confidence is based on score_total percentile (docs/watchlist/watchlist.md)

        $confidenceMap = $this->computeConfidenceMap($rows);

        // Attach rank + PLAN fields + global timing (without letting eligibility/viability override PLAN)
        $rank = 1;
        foreach ($rows as &$r) {
            $r['rank'] = $rank++;

            $tid = (int)($r['ticker_id'] ?? 0);
            if ($tid > 0 && isset($confidenceMap[$tid])) {
                $r['confidence'] = $confidenceMap[$tid];
            } else {
                $r['confidence'] = $this->normalizeConfidence((string)($r['confidence'] ?? 'Med'));
            }

            // PLAN eligibility blocks (for allocations / NEW ENTRY checks). Must not disable PLAN grouping.
            $candElig = (bool)($r['_eligibility']['is_eligible_new_entry'] ?? true);
            $candBlocks = (array)($r['_eligibility']['block_codes'] ?? []);
            $hardLocks = (array)($r['_hard_lock_codes'] ?? []);
            $hardLocked = !empty($hardLocks);

            $r['plan'] = [
                'is_eligible_new_entry' => $candElig,
                'block_codes' => array_values(array_unique(array_map('strval', $candBlocks))),
                'hard_lock_codes' => array_values(array_unique(array_map('strval', $hardLocks))),
                'is_tradeable' => !$hardLocked,
                'trade_viability' => [
                    'evaluated' => false,
                    'is_viable' => null,
                    'reason_codes' => [],
                ],
            ];

            // Base timing from policy + global locks (NO_TRADE days are still shown as PLAN, but not executable)
            $r['timing'] = [
                'entry_windows' => $entryWindows,
                'avoid_windows' => $avoidWindows,
                'entry_style' => $this->normalizeEntryStyle((string)($r['entry_style'] ?? 'No-trade')),
                'size_multiplier' => (float)($timingGlobal['size_multiplier'] ?? 0),
                'trade_disabled' => (bool)($timingGlobal['trade_disabled'] ?? false) || $hardLocked,
                'trade_disabled_reason' => null,
                'trade_disabled_reason_codes' => [],
            ];

            // Apply candidate-specific timing adjustments from policy rules (never disable PLAN)
            $adj = 1.0;
            $shift = null;
            if (isset($r['_policy_rules']) && is_array($r['_policy_rules'])) {
                $adj = (float) ($r['_policy_rules']['size_multiplier_adj'] ?? 1.0);
                $shift = $r['_policy_rules']['shift_entry_windows'] ?? null;
            }
            if ($adj !== 1.0) {
                $r['timing']['size_multiplier'] = round(((float)$r['timing']['size_multiplier']) * $adj, 4);
            }
            if ($shift === 'AFTERNOON_ONLY' && !empty($r['timing']['entry_windows'])) {
                $filtered = [];
                foreach ((array)$r['timing']['entry_windows'] as $w) {
                    if (preg_match('/^(\d{2}):(\d{2})-/', (string)$w, $m)) {
                        $min = ((int)$m[1]) * 60 + (int)$m[2];
                        if ($min >= 12*60) $filtered[] = (string)$w;
                    }
                }
                if (!empty($filtered)) {
                    $r['timing']['entry_windows'] = array_values(array_unique($filtered));
                } else {
                    $last = end($r['timing']['entry_windows']);
                    $r['timing']['entry_windows'] = $last ? [(string)$last] : [];
                }
            }

            // Global locks (EOD not ready / NO_TRADE policy / DOW no-entry)
            if (!empty($globalLockCodes)) {
                $r['timing']['trade_disabled'] = true;
                $r['timing']['trade_disabled_reason'] = $globalLockCodes[0];
                $r['timing']['trade_disabled_reason_codes'] = array_values($globalLockCodes);
                $r['timing']['entry_style'] = 'No-trade';
                $r['timing']['size_multiplier'] = 0.0;
            }

            // If policy has no exec windows today, mark as disabled for execution (still PLAN visible)
            if (empty($entryWindows)) {
                $r['timing']['trade_disabled'] = true;

                if ($r['timing']['trade_disabled_reason'] === null) {
                    $r['timing']['trade_disabled_reason'] = 'GL_NO_EXEC_WINDOW';
                    $codes = (array)($r['timing']['trade_disabled_reason_codes'] ?? []);
                    $codes[] = 'GL_NO_EXEC_WINDOW';
                    $r['timing']['trade_disabled_reason_codes'] = array_values(array_unique($codes));
                }

                $r['timing']['entry_style'] = 'No-trade';
                $r['timing']['size_multiplier'] = 0.0;
            }

            // Ticker hard locks: override reason codes, and force watch_only entry_type (but do not remove from PLAN)
            if ($hardLocked) {
                $r['timing']['trade_disabled'] = true;
                if ($r['timing']['trade_disabled_reason'] === null || $r['timing']['trade_disabled_reason'] === 'GL_NO_EXEC_WINDOW') {
                    $r['timing']['trade_disabled_reason'] = (string)$hardLocks[0];
                }
                $codes = (array)($r['timing']['trade_disabled_reason_codes'] ?? []);
                foreach ($hardLocks as $c) { $codes[] = (string)$c; }
                $r['timing']['trade_disabled_reason_codes'] = array_values(array_unique($codes));
                $r['levels']['entry_type'] = 'WATCH_ONLY';
            }

            // Ensure checklist exists
            if (!isset($r['checklist'])) $r['checklist'] = [];

            // Ensure sizing schema keys
            $r['sizing'] = $this->normalizeSizing($r['sizing'] ?? [], $policyMeta);

            // Ensure levels schema keys
            $r['levels'] = $this->normalizeLevels($r['levels'] ?? [], $r['setup_type'] ?? 'Base');

            // remove internal helpers to keep payload clean
            if (isset($r['_eligibility'])) unset($r['_eligibility']);
            if (isset($r['_policy_rules'])) unset($r['_policy_rules']);
            if (isset($r['_hard_lock_codes'])) unset($r['_hard_lock_codes']);
        }
        unset($r);

        // Policy-specific PLAN enrichment.
        // IMPORTANT (CONTRACT):
        // - WatchlistEngine stays orchestrator.
        // - Any policy-specific PLAN mutation (eligibility/blocks/viability) must live in Policies/*Policy.php.
        $capitalTotal = $opts['capital_idr'] ?? ($opts['capital_total'] ?? null); // legacy fallback: capital_total

        $policyFactory = new \App\Trade\Watchlist\Policies\PolicyFactory();
        $policyObj = $policyFactory->make($policy);

        // --- Group semantics cutoffs (anti salah tafsir) ---
        // score_total is 0..1 (NOT percent). All cutoffs/top_cut are computed on score_total (0..1).
        $gs = (array) (config('trade.watchlist.group_semantics') ?? []);
        // Guard anti salah tafsir: values below are fractions (0..1), not percent (0..100).
        foreach (['toppick_min_score','toppick_score_gap','secondary_min_score','watch_only_min_score'] as $k) {
            if (array_key_exists($k, $gs) && is_numeric($gs[$k]) && (float)$gs[$k] > 1.0) {
                throw new \InvalidArgumentException("trade.watchlist.group_semantics.$k must be 0..1 (fraction), not percent.");
            }
        }
        $topPickMax = (int) ($gs["toppick_max"] ?? ($gs["top_pick_max"] ?? 10));
        if ($topPickMax < 1) { $topPickMax = 1; }
        $toppickMin = (float) ($gs['toppick_min_score'] ?? 0.70);
        $toppickGap = (float) ($gs['toppick_score_gap'] ?? 0.08);
        $secondaryMin = (float) ($gs['secondary_min_score'] ?? 0.55);
        $watchMin = (float) ($gs['watch_only_min_score'] ?? 0.35);

        $scores01 = [];
        foreach ($rows as $r2) { $scores01[] = (float) ($r2['score_total'] ?? 0.0); }
        rsort($scores01);
        $top1 = $scores01[0] ?? 0.0;
        // NOTE (CONTRACT): top_pick_max is a DISPLAY/selection cap only.
        // It MUST NOT affect the cutoff formula (docs/watchlist group semantics Option A LOCKED).
        // top_cut = max(TOPPICK_MIN_SCORE, S0 - TOPPICK_SCORE_GAP)
        $scoreCutTop = max($toppickMin, ($top1 - $toppickGap));
        if ($scoreCutTop < 0.0) $scoreCutTop = 0.0;
        if ($scoreCutTop > 1.0) $scoreCutTop = 1.0;


        // --- Diagnostics / acceptance metrics (docs/watchlist/watchlist.md) ---
        $totalCandidates = is_array($candidates ?? null) ? count($candidates) : 0;
        $universePassed = count($rows);
        $eligibleNewEntry = 0;
        $tradeableCount = 0;
        foreach ($rows as $r3) {
            if (!empty($r3['plan']['is_tradeable'])) $tradeableCount++;
            if (!empty($r3['plan']['is_eligible_new_entry']) && !empty($r3['plan']['is_tradeable'])) $eligibleNewEntry++;
        }
        $scoreStats = [
            'count' => count($scores01),
            'min' => !empty($scores01) ? min($scores01) : null,
            'max' => !empty($scores01) ? max($scores01) : null,
            'mean' => !empty($scores01) ? (array_sum($scores01) / max(count($scores01), 1)) : null,
        ];

        $topPickIndices = [];
        foreach ($rows as $i => $r) {
            $levels = $r['levels'] ?? [];
            $entry = $levels['entry_trigger_price'] ?? null;
            $sl = $levels['stop_loss_price'] ?? null;
            $tp1 = $levels['take_profit_1_price'] ?? null;
            $tick = (float)($levels['tick_size'] ?? 1);

            $sizing = $r['sizing'] ?? [];
            $lotSize = (int)($sizing['lot_size'] ?? 100);

            // Delegate policy-specific PLAN enrich (eligibility/blocks/viability)
            $policyObj->enrichPlanRow($rows[$i], $opts, $policyMeta, $this);

            // NO_TRADE policy → groups.no_trade (monitoring only)
            if (strtoupper((string)$policy) === 'NO_TRADE') {
                $rows[$i]['group'] = 'no_trade';
                continue;
            }

            // IMPORTANT: use the mutated row (after policy + derived mapping), not the stale $r snapshot.
            $elig = (bool)($rows[$i]['plan']['is_eligible_new_entry'] ?? ($r['plan']['is_eligible_new_entry'] ?? true));
            $st = (float)($rows[$i]['score_total'] ?? ($r['score_total'] ?? 0));

            if ($elig) {
                if ($st >= $scoreCutTop) {
                    $rows[$i]['group'] = 'top_picks';
                    $topPickIndices[] = $i;
                    continue;
                }
                if ($st >= $secondaryMin) {
                    $rows[$i]['group'] = 'secondary';
                    continue;
                }
                if ($st >= $watchMin) {
                    $rows[$i]['group'] = 'watch_only';
                    continue;
                }
                // Below WATCH_ONLY_MIN_SCORE: exclude from groups output (docs/watchlist/watchlist.md).
                $rows[$i]['group'] = 'excluded';
                continue;
            }

            // Failed hard rules policy: MUST remain visible in PREOPEN groups (docs + tests).
            // Do not exclude just because score_total is below watch_only_min_score.
            // Blocked entries are surfaced as watch_only with reasons + eligibility_block_codes.
            $rows[$i]['group'] = 'watch_only';
        }

        // Recommendations (allocations) use top-picks as the universe, but do NOT cap top-picks.
        $recs = $this->buildRecommendations(
            $policy,
            $policyMeta,
            $globalLockCodes,
            $openPositions,
            $capitalTotal,
            $rows,
            $topPickIndices
        );

        // Build groups (LOCKED keys: top_picks, secondary, watch_only, avoid, no_trade)
        $top = [];
        $secondary = [];
        $watch = [];
        $avoid = [];
        $noTrade = [];
        foreach ($rows as $r) {
            $g = (string)($r['group'] ?? 'watch_only');
            if ($g === 'excluded') continue;
            if ($g === 'top_picks') { $top[] = $r; }
            elseif ($g === 'secondary') { $secondary[] = $r; }
            elseif ($g === 'avoid') { $avoid[] = $r; }
            elseif ($g === 'no_trade') { $noTrade[] = $r; }
            else { $watch[] = $r; }
        }

        $plan = [
            'recommendations' => $recs,
            // Raw rows (internal) used for mapping to strict preopen contract (needs OHLC, value_est, etc.)
            'groups_raw' => [
                'top_picks' => array_values($top),
                'secondary' => array_values($secondary),
                'watch_only' => array_values($watch),
                'avoid' => array_values($avoid),
                'no_trade' => array_values($noTrade),
            ],
        ];

        // Map intraday snapshots to ticker_code for CONFIRM evaluation.
        $tickerIdToCode = [];
        foreach ($rows as $r) {
            $tid = (int)($r['ticker_id'] ?? 0);
            $code = (string)($r['ticker_code'] ?? '');
            if ($tid > 0 && $code !== '') $tickerIdToCode[$tid] = $code;
        }
        $intradayByCode = [];
        if (isset($intradayByTicker) && is_array($intradayByTicker)) {
            foreach ($intradayByTicker as $tid => $snap) {
                $tid = (int)$tid;
                if ($tid > 0 && isset($tickerIdToCode[$tid])) {
                    $intradayByCode[$tickerIdToCode[$tid]] = $snap;
                }
            }
        }

		// Keep intraday snapshots (keyed by ticker_code) in internal payload for mapping to strict preopen CONFIRM.
		$plan['_intraday_by_code'] = $intradayByCode;

			// Expose thresholds & unit conventions in *internal* payload to avoid score confusion in downstream UI/conditions.
			// NOTE: strict preopen contract mapping intentionally ignores these extra meta keys.
			$groupSemanticsMeta = [
			    'option' => 'A',
			    // score_total is FRACTION [0..1]. For display/scoring, score_0_100 is score_total * 100.
			    'score_total_unit' => 'fraction_0_1',
			    'score_0_100_unit' => 'percent_0_100',
			    'thresholds_fraction' => [
			        'toppick_min_score' => $toppickMin,
			        'toppick_score_gap' => $toppickGap,
			        'secondary_min_score' => $secondaryMin,
			        'watch_only_min_score' => $watchMin,
			    ],
			    'caps' => [
			        'toppick_max' => $topPickMax,
			    ],
			    'computed' => [
			        // top_cut = max(TOPPICK_MIN_SCORE, S0 - TOPPICK_SCORE_GAP)
			        'top_cut' => $scoreCutTop,
				        // S0 = best score_total across candidates (fraction 0..1)
				        'best_score_s0' => $top1,
			    ],
			    'semantics' => [
			        'top_picks' => 'eligible_new_entry && score_total >= top_cut',
			        'secondary' => 'eligible_new_entry && score_total >= secondary_min_score && score_total < top_cut',
			        'watch_only' => 'score_total >= watch_only_min_score && not in top_picks/secondary',
			        'excluded' => 'score_total < watch_only_min_score',
			    ],
			];

        $payload = [
            'trade_date' => (string)$eodDate,
            'exec_trade_date' => (string)$execTradeDate,
            'generated_at' => $generatedAt,
            'policy' => [
                'selected' => $policy,
                'policy_version' => (string)($policyMeta['policy_version'] ?? '1.0'),
            ],
            'meta' => [
                'dow' => $this->dayOfWeek($execTradeDate),
                'market_regime' => $marketRegime,
                'eod_canonical_ready' => (bool)$eodCanonicalReady,
                'as_of_trade_date' => $asOfTradeDate,
	                'missing_trading_dates' => $missingTradingDates,
	                'group_semantics' => $groupSemanticsMeta,
                'counts' => [
                    // candidates fetched from repository (pre-universe filter)
                    'total_candidates' => $totalCandidates,
                    // rows after global universe/hard gates (post-buildCandidate)
                    'universe_passed' => $universePassed,
                    // rows that are tradeable today (not hard-locked)
                    'tradeable' => $tradeableCount,
                    // rows that are eligible for NEW ENTRY today (tradeable + not blocked)
                    'eligible_new_entry' => $eligibleNewEntry,

                    // grouped outputs
                    'top_picks' => count($top),
                    'secondary' => count($secondary),
                    'watch_only' => count($watch),
                    'avoid' => count($avoid),
                    'no_trade' => count($noTrade),
                ],
                'score_stats' => $scoreStats,
	                'notes' => $notes,
	                'session' => $session,
            ],
            'plan' => $plan,
        ];

        return $payload;
    }

    /**
     * Build both payloads:
     * - internal payload: richer structure used for persistence/audit
     * - preopen contract: STRICT schema per docs/watchlist/preopen.md (LOCKED)
     *
     * @return array{internal:array, contract:array}
     */
    public function buildBoth(array $opts = []): array
    {
        $internal = $this->buildInternal($opts);
        $contract = $this->toPreopenContract($internal);
        $this->preopenValidator->validate($contract);

        return ['internal' => $internal, 'contract' => $contract];
    }

    /**
     * Convenience: build only the strict preopen contract.
     */
    public function buildPreopen(array $opts = []): array
    {
        $both = $this->buildBoth($opts);
        return $both['contract'];
    }

    /**
     * Map internal watchlist payload into the strict preopen contract (docs/watchlist/preopen.md).
     */
    private function toPreopenContract(array $p): array
    {
        $policy = (string)($p['policy']['selected'] ?? '');
        $tradeDate = (string)($p['exec_trade_date'] ?? '');
        $asofEodDate = (string)($p['trade_date'] ?? '');
        $canonicalReady = (bool)($p['meta']['eod_canonical_ready'] ?? false);

        $flags = [];
        $reasons = [];
        if (!$canonicalReady) {
            $flags[] = 'EOD_NOT_READY';
            $reasons[] = ['code' => 'GL_EOD_NOT_READY', 'message' => 'EOD canonical belum siap.', 'severity' => 'ERROR'];
        }


	        // For strict preopen contract we must include EOD bar and ticker_plan.
	        // Those fields are only present in internal rows, so prefer plan.groups_raw.
	        $groupsRaw = (array)($p['plan']['groups_raw'] ?? []);
	        $mapGroup = function(array $rows) use ($asofEodDate, $policy) {
	            $out = [];
	            foreach ($rows as $r) {
	                if (!is_array($r)) continue;
	                $out[] = $this->rowToTickerItem($r, $policy, $asofEodDate);
	            }
	            return array_values($out);
	        };

	        $groupOut = [
	            'top_picks'  => $mapGroup((array)($groupsRaw['top_picks'] ?? [])),
	            'secondary'  => $mapGroup((array)($groupsRaw['secondary'] ?? [])),
	            'watch_only' => $mapGroup((array)($groupsRaw['watch_only'] ?? [])),
	            'avoid'      => $mapGroup((array)($groupsRaw['avoid'] ?? [])),
	            'no_trade'   => $mapGroup((array)($groupsRaw['no_trade'] ?? [])),
        ];

        $recommendations = $this->mapRecommendations($p, $groupOut, $canonicalReady);

        $confirm = $this->mapConfirm($p, $groupOut);

        return [
            'meta' => [
                'policy' => $policy,
                'trade_date' => $tradeDate,
                'asof_eod_date' => $asofEodDate,
                'canonical_ready' => $canonicalReady,
                'flags' => $flags,
                'reasons' => $reasons,
            ],
            'groups' => $groupOut,
            'recommendations' => $recommendations,
            'confirm' => $confirm,
        ];
    }

	/**
	 * Build a strict preopen TickerItem from an internal candidate row.
	 * Internal rows contain OHLC + plan fields required by docs/watchlist/preopen.md.
	 */
	private function rowToTickerItem(array $row, string $policy, string $asofEodDate): array
	{
	    $ticker = (string)($row['ticker_code'] ?? '');
	    $rank = (int)($row['rank'] ?? 0);
	    $scoreTotal = isset($row['score_total']) && is_numeric($row['score_total']) ? (float)$row['score_total'] : 0.0;

	    // IMPORTANT: PREOPEN reasons must explain BOTH "why it looks good" and "why it's blocked".
	    // Otherwise users see watch_only with empty reasons (ambiguous / misleading).
	    $reasonCodes = array_values(array_unique(array_filter((array)($row['reason_codes'] ?? []), 'is_string')));
	    $blockCodes = array_values(array_unique(array_filter((array)($row['eligibility_block_codes'] ?? []), 'is_string')));

	    $reasons = [];
	    if (!empty($reasonCodes)) {
	        $reasons = array_merge($reasons, $this->buildCandidateReasonObjects($reasonCodes, 'INFO'));
	    }
	    if (!empty($blockCodes)) {
	        // eligibility_block_codes are blockers for new entry today
	        $reasons = array_merge($reasons, $this->buildCandidateReasonObjects($blockCodes, 'SOFT_BLOCK'));
	    }
	    // Deduplicate by code
	    if (!empty($reasons)) {
	        $tmp = [];
	        foreach ($reasons as $rr) {
	            if (is_array($rr) && isset($rr['code'])) $tmp[(string)$rr['code']] = $rr;
	        }
	        $reasons = array_values($tmp);
	    }

	    // Allow internal rows to provide richer reason objects (e.g., missing_fields details).
	    if (isset($row['reasons']) && is_array($row['reasons'])) {
	        $map = [];
	        foreach ($reasons as $rr) {
	            if (is_array($rr) && isset($rr['code'])) {
	                $map[(string)$rr['code']] = $rr;
	            }
	        }
	        foreach ((array)$row['reasons'] as $cr) {
	            if (!is_array($cr)) continue;
	            $c = (string)($cr['code'] ?? '');
	            if ($c === '') continue;
	            // Accept either candidate schema or legacy schema.
	            $sevLvl = (string)($cr['severity_level'] ?? '');
	            if ($sevLvl === '' && isset($cr['severity'])) {
	                $sev = strtoupper(trim((string)$cr['severity']));
	                if ($sev === 'WARN') $sevLvl = 'WARN';
	                elseif ($sev === 'INFO') $sevLvl = 'INFO';
	                else $sevLvl = 'SOFT_BLOCK';
	            }
	            if ($sevLvl === '') $sevLvl = 'INFO';
	            $map[$c] = [
	                'code' => $c,
	                'message' => (string)($cr['message'] ?? $c),
	                'severity_level' => strtoupper($sevLvl),
	            ];
	        }
	        $reasons = array_values($map);
	    }

	    $eodBar = $this->buildEodBar($row, $asofEodDate);
	    $tickerPlan = $this->buildTickerPlan($row, $policy);

	    return [
	        'ticker' => $ticker,
	        'rank' => $rank,
	        'score_total' => $scoreTotal,
	        'reasons' => $reasons,
	        'eod_bar' => $eodBar,
	        'ticker_plan' => $tickerPlan,
	    ];
	}

	/** @return array<int, array{code:string,message:string,severity?:string}> */
	private function buildGlobalReasonObjects(array $codes, string $defaultSeverity = 'INFO'): array
	{
	    $out = [];
	    foreach ($codes as $code) {
	        if (!is_string($code) || $code === '') continue;
	        $msg = ReasonCatalog::getMessage($code);
	        if ($msg === '') $msg = $code;
	        $out[] = ['code' => $code, 'message' => $msg, 'severity' => $defaultSeverity];
	    }
	    return array_values($out);
	}

	/** @return array<int, array{code:string,message:string,severity_level:string}> */
	private function buildCandidateReasonObjects(array $codes, string $defaultLevel = 'INFO'): array
	{
	    $out = [];
	    $lvl = strtoupper(trim($defaultLevel));
	    if (!in_array($lvl, ['INFO','WARN','SOFT_BLOCK','HARD_EXCLUDE'], true)) $lvl = 'INFO';
	    foreach ($codes as $code) {
	        if (!is_string($code) || $code === '') continue;
	        $msg = ReasonCatalog::getMessage($code);
	        if ($msg === '') $msg = $code;

	        $useLvl = $lvl;
	        // Per docs: ticker status defaulted is WARN.
	        if ($code === 'GL_TICKER_STATUS_DEFAULTED_REGULAR') $useLvl = 'WARN';

	        $out[] = ['code' => $code, 'message' => $msg, 'severity_level' => $useLvl];
	    }
	    return array_values($out);
	}

	/**
	 * Normalize an array of candidate reason arrays into strict CandidateReason schema.
	 * Accepts either {code,message,severity_level} or legacy {code,message,severity}.
	 *
	 * @param array<int,mixed> $reasons
	 * @return array<int,array{code:string,message:string,severity_level:string}>
	 */
	private function normalizeCandidateReasons(array $reasons): array
	{
	    $out = [];
	    foreach ($reasons as $r) {
	        if (!is_array($r)) continue;
	        $c = (string)($r['code'] ?? '');
	        if ($c === '') continue;
	        $msg = (string)($r['message'] ?? ReasonCatalog::getMessage($c));
	        $lvl = (string)($r['severity_level'] ?? '');
	        if ($lvl === '' && isset($r['severity'])) {
	            $sev = strtoupper(trim((string)$r['severity']));
	            if ($sev === 'WARN') $lvl = 'WARN';
	            elseif ($sev === 'INFO') $lvl = 'INFO';
	            else $lvl = 'SOFT_BLOCK';
	        }
	        $lvl = strtoupper(trim($lvl));
	        if (!in_array($lvl, ['INFO','WARN','SOFT_BLOCK','HARD_EXCLUDE'], true)) $lvl = 'INFO';
	        $out[] = ['code' => $c, 'message' => $msg, 'severity_level' => $lvl];
	    }
	    // de-dupe by code
	    $map = [];
	    foreach ($out as $rr) $map[$rr['code']] = $rr;
	    return array_values($map);
	}

	/**
	 * Normalize execution_slices[].reason into CandidateReason schema.
	 *
	 * @param array<int,mixed> $slices
	 * @return array<int,array<string,mixed>>
	 */
	private function normalizeExecutionSlices(array $slices): array
	{
	    $out = [];
	    foreach ($slices as $s) {
	        if (!is_array($s)) continue;
	        if (isset($s['reason']) && is_array($s['reason'])) {
	            $rr = $this->normalizeCandidateReasons([$s['reason']]);
	            $s['reason'] = isset($rr[0]) ? $rr[0] : $s['reason'];
	        }
	        $out[] = $s;
	    }
	    return array_values($out);
	}

	/** @return array<string,mixed> */
	private function buildEodBar(array $row, string $asofEodDate): array
	{
	    // Support both legacy top-level OHLC keys and internal rows that keep OHLC under `basis`.
	    $src = (isset($row['basis']) && is_array($row['basis'])) ? (array)$row['basis'] : $row;

	    // NOTE: Real DB feeds sometimes return numeric strings with separators or blanks.
	    // Cast ONLY after sanitizing to avoid "A non well formed numeric value encountered" warnings.
	    // Internal rows SHOULD provide these keys: open, high, low, close, volume.
	    // Some legacy paths may provide: o,h,l,c,v or volume_shares.
	    $openRaw  = $src['open']  ?? ($src['o'] ?? null);
	    $highRaw  = $src['high']  ?? ($src['h'] ?? null);
	    $lowRaw   = $src['low']   ?? ($src['l'] ?? null);
	    $closeRaw = $src['close'] ?? ($src['c'] ?? null);
	    $volRaw   = $src['volume'] ?? ($src['volume_shares'] ?? ($src['v'] ?? null));

	    $open  = (int) round($this->toFloat($openRaw, 0.0));
	    $high  = (int) round($this->toFloat($highRaw, 0.0));
	    $low   = (int) round($this->toFloat($lowRaw, 0.0));
	    $close = (int) round($this->toFloat($closeRaw, 0.0));
	    $volumeShares = (int) round($this->toFloat($volRaw, 0.0));

	    $prevClose = (int) round($this->toFloat($src['prev_close'] ?? null, 0.0));

	    // Best-effort value estimate (IDR). Prefer value_est if provided.
	    $valueEst = null;
	    if (array_key_exists('value_est', $row)) {
	        $valueEst = $this->toFloat($row['value_est'], 0.0);
	    }
	    if ($valueEst === null || $valueEst <= 0) {
	        $valueEst = (float) $close * (float) $volumeShares;
	    }
	    $valueIdr = (int) round($valueEst);

	    $gapPct = null;
	    if ($prevClose > 0) {
	        $gapPct = ($open - $prevClose) / $prevClose; // ratio (0..1)
	    }

	    return [
	        'asof_eod_date' => $asofEodDate,
	        'open' => $open,
	        'high' => $high,
	        'low' => $low,
	        'close' => $close,
	        'prev_close' => $prevClose,
	        'gap_pct' => $gapPct,
	        'volume_shares' => $volumeShares,
	        'value_idr' => $valueIdr,
	    ];
	}

	/**
	 * Safe numeric parsing for values coming from DB/fixtures.
	 * Avoids "A non well formed numeric value encountered" warnings
	 * when values contain commas, spaces, or other formatting.
	 */
	private function toFloat($v, float $default = 0.0): float
	{
	    if (is_int($v) || is_float($v)) {
	        return (float) $v;
	    }
	    if ($v === null) {
	        return $default;
	    }
	    if (is_string($v)) {
	        $s = trim($v);
	        if ($s === '') return $default;
	        // common formatting: 6,000 or 6 000
	        $s = str_replace([',', ' '], ['', ''], $s);
	        // some sources might use underscores as separators
	        $s = str_replace('_', '', $s);
	        if (is_numeric($s)) return (float) $s;
	        return $default;
	    }
	    // last resort: only cast if it is numeric
	    if (is_numeric($v)) return (float) $v;
	    return $default;
	}

	/** @return array<string,mixed> */
    private function buildTickerPlan(array $row, string $policy): array
	{
	    $setup = (string)($row['setup_type'] ?? '');
	    $setupKind = $this->inferSetupKind($setup);
	
	    $levelsNorm = $this->normalizeLevels(is_array($row['levels'] ?? null) ? (array)$row['levels'] : [], $setup);
	    $entry = (int)($levelsNorm['entry_trigger_price'] ?? 0);
	    $stop = (int)($levelsNorm['stop_loss_price'] ?? 0);
	    $tp1 = (int)($levelsNorm['tp1_price'] ?? 0);
	
	    $rrEst = $this->computeRrEst($entry, $stop, $tp1);
	    $slices = $this->buildExecutionSlices($policy, $setupKind, $entry, null);

	    return [
	        'setup_type' => $setupKind,
	        'plan_entry' => $entry,
	        'plan_stop' => $stop,
	        'plan_tp1' => $tp1,
	        'rr_est' => $rrEst,
	        'execution_slices' => $slices,
	    ];
	}

	private function inferSetupKind(string $setup): string
	{
	    $s = strtoupper(trim($setup));
	    if ($s === 'PULLBACK' || $s === 'PB' || $s === 'PULLBACK_SETUP') return 'PULLBACK';
	    if ($s === 'BREAKOUT' || $s === 'BO' || $s === 'BREAKOUT_SETUP') return 'BREAKOUT';
	    // backward-compat (older labels like "Pullback")
	    if (stripos($setup, 'pull') !== false) return 'PULLBACK';
	    return 'BREAKOUT';
	}

	public function computeRrEst(int $entry, int $stop, int $tp1): ?float
	{
	    if ($entry <= 0 || $tp1 <= 0 || $stop <= 0) return null;
	    $risk = $entry - $stop;
	    $reward = $tp1 - $entry;
	    if ($risk <= 0 || $reward <= 0) return null;
	    return $reward / $risk;
	}

	/**
	 * Build execution slices (mini tranche template).
	 *
	 * Contract (docs/watchlist/watchlist.md):
	 * - Always return deterministic PLAN prices (plan_limit_price + plan_price_cap).
	 * - If capital missing -> lots=null for all slices (template only).
	 * - If plannedLots provided -> split lots by policy+profile mapping (2 tranche rule V1).
	 *
	 * @return array<int, array<string,mixed>>
	 */
	private function buildExecutionSlices(string $policy, string $setupKind, int $entryPrice, ?int $plannedLots, ?string $profile = null): array
	{
	    // Timing hint (LOCKED): tranche1 09:20, tranche2 10:30 (watchlist.md).
	    $times = ['09:20', '10:30'];

	    $profile = $profile ?: 'DEFAULT';
	    [$p1, $p2] = $this->miniTranchePcts($policy, $profile);

	    $lots1 = null;
	    $lots2 = null;
	    if ($plannedLots !== null && $plannedLots > 0) {
	        $lots1 = (int) ceil($plannedLots * $p1);
	        if ($lots1 < 1) $lots1 = 1;
	        $lots2 = (int) max(0, $plannedLots - $lots1);
	    }

	    $priceCap = $this->computePlanPriceCap($policy, $setupKind, $entryPrice);

	    $trigger = ($setupKind === 'PULLBACK')
	        ? 'PULLBACK: last_live <= plan_entry'
	        : 'BREAKOUT: last_live >= plan_entry';

	    $reasonCodePrefix = $this->policyPrefix($policy);

	    return [
	        [
	            'n' => 1,
	            'time' => $times[0],
	            'lots' => $lots1,
	            'plan_limit_price' => $entryPrice,
	            'plan_price_cap' => $priceCap,
	            'plan_price_floor' => null,
	            'trigger' => $trigger,
	            'reason' => [
	                'code' => $reasonCodePrefix . '_TRANCHE1',
	                'message' => 'Tranche 1 (profile ' . $profile . ').',
	                'severity_level' => 'INFO',
	            ],
	        ],
	        [
	            'n' => 2,
	            'time' => $times[1],
	            'lots' => $lots2,
	            'plan_limit_price' => $entryPrice,
	            'plan_price_cap' => $priceCap,
	            'plan_price_floor' => null,
	            'trigger' => $trigger,
	            'reason' => [
	                'code' => $reasonCodePrefix . '_TRANCHE2',
	                'message' => 'Tranche 2 (profile ' . $profile . ').',
	                'severity_level' => 'INFO',
	            ],
	        ],
	    ];
	}

	/**
	 * Compute plan_price_cap (PLAN, immutable) based on setup_kind and policy confirm guards.
	 */
	private function computePlanPriceCap(string $policy, string $setupKind, int $entryPrice): int
	{
	    if ($entryPrice <= 0) return 0;
	    // Source-of-truth: SCORECARD strict guards (docs/watchlist/scorecard.md).
	    // PLAN must be deterministic and aligned with CONFIRM strict.
	    $g = $this->scorecardCfg->guardsForPolicy($policy);
	    $chasePct = isset($g['max_chase_pct']) ? (float)$g['max_chase_pct'] : 0.02;
	    if ($setupKind === 'PULLBACK') {
	        return $entryPrice;
	    }
	    return (int)$this->tickRule->roundUp($entryPrice * (1.0 + $chasePct));
	}

	/**
	 * Mini tranche percentages (2 tranche rule V1).
	 *
	 * WS/DS/PT:
	 * - CONSERVATIVE 50/50
	 * - DEFAULT      60/40
	 * - AGGRESSIVE   70/30
	 *
	 * IL:
	 * - CONSERVATIVE 60/40
	 * - DEFAULT      70/30
	 * - AGGRESSIVE   80/20
	 *
	 * @return array{0:float,1:float}
	 */
	private function miniTranchePcts(string $policy, string $profile): array
	{
	    $p = strtoupper(trim($profile));
	    $isIL = (strtoupper($policy) === 'INTRADAY_LIGHT');
	
	    if ($isIL) {
	        if ($p === 'AGGRESSIVE') return [0.80, 0.20];
	        if ($p === 'CONSERVATIVE') return [0.60, 0.40];
	        return [0.70, 0.30];
	    }

	    // WS/DS/PT default
	    if ($p === 'AGGRESSIVE') return [0.70, 0.30];
	    if ($p === 'CONSERVATIVE') return [0.50, 0.50];
	    return [0.60, 0.40];
	}

	/**
	 * Determine mini tranche profile based on risk/reward buckets (LOCKED in watchlist.md).
	 */
	private function selectMiniTrancheProfile(string $policy, ?float $rrEst, ?float $atrPct, ?float $tickPct, bool $hasCaEvent): string
	{
	    // Risk bucket
	    $atr = $atrPct !== null ? (float)$atrPct : 999.0;
	    $tick = $tickPct !== null ? (float)$tickPct : 999.0;
	
	    $riskHigh = ($atr >= 0.12) || ($tick >= 0.012) || $hasCaEvent;
	    $riskLow  = ($atr <= 0.07) && ($tick <= 0.008) && (!$hasCaEvent);
	
	    // Reward bucket
	    $rr = $rrEst !== null ? (float)$rrEst : 0.0;
	    $rewardHigh = ($rr >= 1.8);
	
	    // Policy-specific rule
	    if ($riskHigh) return 'CONSERVATIVE';
	    if (strtoupper($policy) === 'INTRADAY_LIGHT') {
	        if ($rewardHigh) return 'AGGRESSIVE';
	        return 'DEFAULT';
	    }
	    if ($rewardHigh && $riskLow) return 'AGGRESSIVE';
	    return 'DEFAULT';
	}

	/** @return array<string,mixed> */
	private function mapRecommendations(array $p, array $groupOut, bool $canonicalReady): array
	{
	    $recs = (array)($p['plan']['recommendations'] ?? []);
	    $capital = null;
	    $capRaw = $recs['capital_idr'] ?? ($recs['capital_total'] ?? null); // legacy fallback
	    if ($capRaw !== null && is_numeric($capRaw)) {
	        $capital = (int)round((float)$capRaw);
	    }
	
	    $mode = ($capital === null || $capital <= 0) ? 'A_NO_CAPITAL' : 'B_WITH_CAPITAL';
	    $policy = (string)($p['policy']['selected'] ?? '');

	    $topReasons = [];
	    $skipped = [];
	    if ($canonicalReady) {
	        // Translate internal skipped rows to minimal RECO_* reason codes for UI audit.
	        foreach ((array)($recs['skipped'] ?? []) as $s) {
	            if (!is_array($s)) continue;
	            $ticker = (string)($s['ticker_code'] ?? ($s['ticker'] ?? ''));
	            if ($ticker === '') continue;
	
	            $rc = (string)($s['reason_code'] ?? '');
	            $mapped = $this->mapRecoReasonCode($rc, $policy);
	            $skipped[] = [
	                'ticker' => $ticker,
	                'reason' => [
	                    'code' => $mapped,
	                    'message' => $this->defaultRecoReasonMessage($mapped, $rc),
	                    'severity' => 'WARN',
	                ],
	            ];
	        }

	        // Exposure cap reached (no new slots today).
	        if (!empty($recs) && isset($recs['max_positions_today']) && (int)$recs['max_positions_today'] <= 0) {
	            $topReasons[] = [
	                'code' => 'RECO_TARGET_SLOTS_FULL',
	                'message' => 'Exposure cap tercapai: tidak ada slot posisi baru untuk hari ini.',
	                'severity' => 'WARN',
	            ];
	        }
	    }
	
	    $items = [];
	    if ($canonicalReady) {
	        // Build quick lookup for rank & plan from groupOut
	        $map = [];
	        foreach (['top_picks','secondary','watch_only'] as $g) {
	            foreach ((array)($groupOut[$g] ?? []) as $it) {
	                if (!is_array($it) || empty($it['ticker'])) continue;
	                $map[(string)$it['ticker']] = $it;
	            }
	        }
	
	        foreach ((array)($recs['allocations'] ?? []) as $a) {
	            if (!is_array($a)) continue;
	            $ticker = (string)($a['ticker_code'] ?? ($a['ticker'] ?? ''));
	            if ($ticker === '') continue;
	
	            $ref = $map[$ticker] ?? null;
	            $rankRef = $ref ? (int)($ref['rank'] ?? 0) : 0;
	            $weight = isset($a['alloc_pct']) && is_numeric($a['alloc_pct']) ? (float)$a['alloc_pct'] : 0.0;
	            $plannedLots = isset($a['lots_recommended']) && is_numeric($a['lots_recommended']) ? (int)$a['lots_recommended'] : null;
	            $estCost = isset($a['estimated_cost']) && is_numeric($a['estimated_cost']) ? (int)round((float)$a['estimated_cost']) : null;
	
	            $plan = $ref && isset($ref['ticker_plan']) && is_array($ref['ticker_plan']) ? $ref['ticker_plan'] : null;
	            $setupType = $plan ? (string)($plan['setup_type'] ?? 'BREAKOUT') : 'BREAKOUT';
	            $entry = $plan ? (int)($plan['plan_entry'] ?? 0) : 0;
	            $stop = $plan ? (int)($plan['plan_stop'] ?? 0) : 0;
	            $tp1 = $plan ? (int)($plan['plan_tp1'] ?? 0) : 0;

	            $itemReasons = $ref ? $this->normalizeCandidateReasons((array)($ref['reasons'] ?? [])) : [];
	            if ($mode === 'A_NO_CAPITAL') {
	                $itemReasons[] = [
	                    'code' => 'RECO_CAPITAL_MISSING',
	                    'message' => 'Mode A: capital tidak tersedia, lots/cost tidak dihitung.',
	                    'severity_level' => 'INFO',
	                ];
	            }

	            $items[] = [
	                'ticker' => $ticker,
	                'rank_ref' => $rankRef,
	                'weight_pct' => $weight,
	                'planned_lots' => ($mode === 'A_NO_CAPITAL') ? null : ($plannedLots ?? null),
	                'estimated_cost_idr' => ($mode === 'A_NO_CAPITAL') ? null : ($estCost ?? null),
	                'fee_included' => true,
	                'setup_type' => $setupType,
	                'plan_entry' => $entry,
	                'plan_stop' => $stop,
	                'plan_tp1' => $tp1,
	                'reasons' => $this->normalizeCandidateReasons($itemReasons),
	                'execution_slices' => (isset($a['execution_slices']) && is_array($a['execution_slices']))
	                    ? $this->normalizeExecutionSlices((array)$a['execution_slices'])
                    : $this->buildExecutionSlices(
                        (string)($p['policy']['selected'] ?? ''),
                        $setupType,
                        $entry,
                        ($mode === 'A_NO_CAPITAL') ? null : ($plannedLots ?? null)
                    ),
	            ];
	        }
	    }

	    return [
	        'mode' => $mode,
	        'capital_idr' => $capital,
	        'items' => array_values($items),
	        'reasons' => $topReasons,
	        'skipped' => array_values($skipped),
	        'cash_remaining_idr' => ($canonicalReady && (isset($recs['cash_remaining_idr']) || isset($recs['cash_remaining'])) && is_numeric($recs['cash_remaining_idr'] ?? $recs['cash_remaining']))
	            ? (int)round((float)($recs['cash_remaining_idr'] ?? $recs['cash_remaining']))
	            : null,
	    ];
	}

	private function mapRecoReasonCode(string $reasonCode, string $policy): string
	{
	    $rc = strtoupper($reasonCode);
	    if ($rc === 'GL_ALREADY_HELD') return 'RECO_ALREADY_HELD_SKIP';
	
	    // Policy-specific insufficient cash codes.
	    if (strpos($rc, 'INSUFFICIENT_CASH') !== false) return 'RECO_INSUFFICIENT_CASH_MIN_LOT';

	    // Blocked/lock codes.
	    if ($rc === 'GL_HARD_LOCK' || strpos($rc, 'LOCK') !== false) return 'RECO_SKIPPED_BLOCKED';
	    if (strpos($rc, 'BLOCK') !== false) return 'RECO_SKIPPED_BLOCKED';

	    // Default: still treat as blocked.
	    return 'RECO_SKIPPED_BLOCKED';
	}

	private function defaultRecoReasonMessage(string $mapped, string $raw): string
	{
	    switch ($mapped) {
	        case 'RECO_ALREADY_HELD_SKIP':
	            return 'Dilewati: sudah ada posisi terbuka.';
	        case 'RECO_INSUFFICIENT_CASH_MIN_LOT':
	            return 'Tidak feasible: cash tidak cukup untuk beli minimal 1 lot (setelah fee/slippage).';
	        case 'RECO_SKIPPED_BLOCKED':
	            return 'Tidak feasible: blocked/hard lock/tradeability false.';
	        default:
	            return 'Dilewati: '.$raw;
	    }
	}

	/** @return array<string,mixed> */
	private function mapConfirm(array $p, array $groupOut): array
	{
	    if (!$this->cfg->confirmEnabled()) {
	        return [
	            'status' => 'none',
	            'checked_count' => 0,
	            'by_ticker' => [],
	        ];
	    }

	    $policy = (string)($p['policy']['selected'] ?? '');
	    if (strtoupper($policy) === 'NO_TRADE') {
	        // NO_TRADE: monitoring only. Keep output simple (no CONFIRM verdicts).
	        return [
	            'status' => 'none',
	            'checked_count' => 0,
	            'by_ticker' => [],
	        ];
	    }

	    $snaps = (array)($p['plan']['_intraday_by_code'] ?? []);

	    // Universe per docs/watchlist/scorecard.md: recommendations first, else top+secondary.
	    $recItems = (array)($this->mapRecommendations($p, $groupOut, true)['items'] ?? []);
	    $recMap = [];
	    foreach ($recItems as $it) {
	        if (is_array($it) && !empty($it['ticker'])) $recMap[(string)$it['ticker']] = $it;
	    }

	    $planMap = [];
	    foreach (['top_picks','secondary','watch_only'] as $g) {
	        foreach ((array)($groupOut[$g] ?? []) as $it) {
	            if (is_array($it) && !empty($it['ticker'])) $planMap[(string)$it['ticker']] = $it;
	        }
	    }

	    $universe = [];
	    if (!empty($recMap)) {
	        $universe = array_keys($recMap);
	    } else {
	        foreach (['top_picks','secondary'] as $g) {
	            foreach ((array)($groupOut[$g] ?? []) as $it) {
	                if (is_array($it) && !empty($it['ticker'])) $universe[] = (string)$it['ticker'];
	            }
	        }
	        $universe = array_values(array_unique(array_filter($universe, function ($x) { return is_string($x) && $x !== ''; })));
	    }
	    if ($this->scorecardCfg->includeWatchOnly) {
	        foreach ((array)($groupOut['watch_only'] ?? []) as $it) {
	            if (is_array($it) && !empty($it['ticker'])) $universe[] = (string)$it['ticker'];
	        }
	        $universe = array_values(array_unique(array_filter($universe, function ($x) { return is_string($x) && $x !== ''; })));
	    }

	    if (empty($universe)) {
	        return [
	            'status' => 'none',
	            'checked_count' => 0,
	            'by_ticker' => [],
	        ];
	    }

	    // Relevant universe: only tickers present in PLAN (strict). This allows confirm.status=partial when some tickers lack snapshots.
	    $relevant = [];
	    foreach ($universe as $t) {
	        $t = strtoupper(trim((string)$t));
	        if ($t === '') continue;
	        if (isset($planMap[$t]) && is_array($planMap[$t])) $relevant[] = $t;
	    }
	    $relevant = array_values(array_unique($relevant));
	    $totalRelevant = count($relevant);
	    if ($totalRelevant === 0) {
	        return [
	            'status' => 'none',
	            'checked_count' => 0,
	            'by_ticker' => [],
	        ];
	    }

	    $session = (array)($p['meta']['session'] ?? []);
	    $sessionOpen = (string)($session['open_time'] ?? $this->scorecardCfg->sessionOpenTimeDefault);
	    $sessionClose = (string)($session['close_time'] ?? $this->scorecardCfg->sessionCloseTimeDefault);

	    $guardsFallback = new CandidateGuardsDto(
	        (float)$this->scorecardCfg->maxChasePctDefault,
	        (float)$this->scorecardCfg->gapUpBlockPctDefault,
	        (float)$this->scorecardCfg->spreadMaxPctDefault,
	        (float)$this->scorecardCfg->breakoutBandPctDefault
	    );

	    $evaluator = new ExecutionEligibilityEvaluator($this->scorecardCfg);
	    $byTicker = [];

	    $coveredCount = 0;

	    foreach ($relevant as $ticker) {
	        $ticker = strtoupper(trim((string)$ticker));
	        if ($ticker === '') continue;

	        $planRow = (array)$planMap[$ticker];
	        $tp = isset($planRow['ticker_plan']) && is_array($planRow['ticker_plan']) ? (array)$planRow['ticker_plan'] : [];
	        $eodBar = isset($planRow['eod_bar']) && is_array($planRow['eod_bar']) ? (array)$planRow['eod_bar'] : [];
	
	        // Candidate payload adapter for strict evaluator (single source of truth for CONFIRM logic).
	        $reasonCodes = [];
	        foreach ((array)($planRow['reasons'] ?? []) as $rr) {
	            if (is_array($rr) && !empty($rr['code']) && is_string($rr['code'])) $reasonCodes[] = (string)$rr['code'];
	        }
	
	        $candArr = $planRow;
	        $candArr['ticker'] = $ticker;
	        $candArr['score'] = isset($planRow['score_total']) && is_numeric($planRow['score_total']) ? (float)$planRow['score_total'] : (float)($planRow['score'] ?? 0);
	        $candArr['rank'] = isset($planRow['rank']) && is_numeric($planRow['rank']) ? (int)$planRow['rank'] : 0;
	        $candArr['reason_codes'] = $reasonCodes;
	        // CandidateDto expects these keys.
	        $candArr['setup_type'] = (string)($tp['setup_type'] ?? 'BREAKOUT');
	        $candArr['entry_trigger'] = isset($tp['plan_entry']) ? (float)$tp['plan_entry'] : null;
	        $candArr['execution_slices'] = (array)($tp['execution_slices'] ?? []);

	        $candDto = CandidateDto::fromArray($candArr, $guardsFallback, (int)($candArr['rank'] ?? 0));
	        $run = StrategyRunDto::fromNormalized(
	            (string)($p['trade_date'] ?? ''),
	            (string)($p['exec_trade_date'] ?? ''),
	            $policy,
	            (string)($p['plan']['recommendations']['mode'] ?? ''),
	            (string)($p['meta']['generated_at'] ?? ''),
	            [$candDto],
	            [],
	            []
	        );

	        $snap = isset($snaps[$ticker]) && is_array($snaps[$ticker]) ? (array)$snaps[$ticker] : [];
	        $checkedAtIso = $this->toIsoCheckedAt(
	            (string)($snap['updated_at'] ?? ''),
	            (string)($p['exec_trade_date'] ?? ''),
	            (string)($snap['checked_at'] ?? '')
	        );
	        $checkedAt = $this->timeOnly($checkedAtIso);

	        // Snapshot coverage: if snapshot missing/invalid, treat as not checked (status may become partial).
	        if (empty($snap) || $checkedAtIso === '') {
	            continue;
	        }
	        $coveredCount++;

	        // Live inputs adapter.
	        $bid1 = isset($snap['bid1']) && is_numeric($snap['bid1']) ? (float)$snap['bid1'] : null;
	        $ask1 = isset($snap['ask1']) && is_numeric($snap['ask1']) ? (float)$snap['ask1'] : null;
	        $last = null;
	        if (isset($snap['open_or_last_exec']) && is_numeric($snap['open_or_last_exec'])) {
	            $last = (float)$snap['open_or_last_exec'];
	        } elseif (isset($snap['last']) && is_numeric($snap['last'])) {
	            $last = (float)$snap['last'];
	        }
	        $open = isset($snap['open']) && is_numeric($snap['open']) ? (float)$snap['open'] : null;

	        $prevClosePlan = isset($eodBar['close']) && is_numeric($eodBar['close']) ? (float)$eodBar['close'] : null;

	        $liveArr = [
                'ticker' => $ticker,
                'bid' => $bid1,
                'ask' => $ask1,
                'last' => $last,
                'open' => $open,
                'prev_close_plan' => $prevClosePlan,
                'prev_close_live' => null,
                // retry budget state persisted in intraday snapshots (best-effort)
                'retry_count' => (int)($snap['confirm_retry_count'] ?? 0),
                'retry_last_checked_at' => (string)($snap['confirm_last_checked_at'] ?? ''),
            ];
	
	        $snapshot = new LiveSnapshotDto(
	            $checkedAtIso,
	            'confirm',
	            $sessionOpen,
	            $sessionClose,
	            [ $ticker => LiveTickerDto::fromArray($liveArr) ]
	        );

	        $check = $evaluator->evaluate($run, $snapshot, $this->scorecardCfg);
	        $res = (isset($check->results[0]) && $check->results[0] instanceof \App\DTO\Watchlist\Scorecard\EligibilityResultDto)
	            ? $check->results[0]
	            : null;
	
	        if ($res === null) continue;
	        $r = $res->toArray();

	        // Map strict evaluator output to preopen contract confirm schema.
	        $computed = isset($r['computed']) && is_array($r['computed']) ? $r['computed'] : [];
	        $gapPct = array_key_exists('gap_pct', $computed) ? $computed['gap_pct'] : null;
	        $spreadPct = array_key_exists('spread_pct', $computed) ? $computed['spread_pct'] : null;
	        $chasePct = array_key_exists('chase_pct', $computed) ? $computed['chase_pct'] : null;
	        $ageSec = array_key_exists('snapshot_age_sec', $computed) ? $computed['snapshot_age_sec'] : null;

	        // Derive snapshot age from generated_at vs checked_at when evaluator doesn't provide it.
	        if ($ageSec === null || (is_int($ageSec) && $ageSec <= 0)) {
	            $genIso = (string)($p['meta']['generated_at'] ?? '');
	            $tGen = strtotime($genIso);
	            $tChk = strtotime($checkedAtIso);
	            if ($tGen !== false && $tChk !== false) {
	                $d = (int)($tGen - $tChk);
	                $ageSec = ($d >= 0) ? $d : (-$d);
	            } else {
	                $ageSec = 0;
	            }
	        }


				$retryCount = array_key_exists('retry_count', $computed) ? (int)$computed['retry_count'] : (int)($snap['confirm_retry_count'] ?? 0);
				$maxRetryWindows = array_key_exists('max_retry_windows', $computed) ? (int)$computed['max_retry_windows'] : (int)$this->scorecardCfg->maxRetryWindowsDefault;
				$nextCheckAtIso = (isset($r['next_check_at']) && is_string($r['next_check_at']) && $r['next_check_at'] !== '') ? (string)$r['next_check_at'] : null;
				$prevRetry = (int)($snap['confirm_retry_count'] ?? 0);
				$prevNext = isset($snap['confirm_next_check_at']) ? (string)$snap['confirm_next_check_at'] : '';
				$tickerId = isset($snap['ticker_id']) && is_numeric($snap['ticker_id']) ? (int)$snap['ticker_id'] : 0;
				if ($tickerId > 0) {
				    // Persist retry state only when it changes (preopen can be called repeatedly).
				    if ($retryCount !== $prevRetry || ((string)($nextCheckAtIso ?? '') !== $prevNext)) {
				        // Repository is the persistence layer for retry state.
				        $this->intraRepo->updateConfirmRetryState((string)($p['exec_trade_date'] ?? ''), $tickerId, $retryCount, $checkedAtIso, $nextCheckAtIso);
				    }
				}

	        // Preserve time windows from PLAN slices (strict evaluator uses tranche numbering only).
	        $sliceByTranche = [];
	        foreach ((array)($tp['execution_slices'] ?? []) as $s) {
	            if (!is_array($s)) continue;
	            $n = isset($s['n']) && is_numeric($s['n']) ? (int)$s['n'] : null;
	            if ($n === null || $n <= 0) continue;
	            $sliceByTranche[$n] = $s;
	        }

	        $decision = (string)($r['decision'] ?? 'REJECT');

	        $orders = [];
	        foreach ((array)($r['recommended_orders'] ?? []) as $o) {
	            if (!is_array($o)) continue;

	            $n = isset($o['n']) && is_numeric($o['n']) ? (int)$o['n'] : (count($orders) + 1);
	            $ps = $sliceByTranche[$n] ?? [];

	            $planLimit = isset($o['plan_limit_price']) && is_numeric($o['plan_limit_price'])
	                ? (int)$this->tickRule->roundDown((float)$o['plan_limit_price'])
	                : (isset($ps['plan_limit_price']) && is_numeric($ps['plan_limit_price']) ? (int)$ps['plan_limit_price'] : null);

	            $planCap = isset($o['plan_price_cap']) && is_numeric($o['plan_price_cap'])
	                ? (int)$this->tickRule->roundDown((float)$o['plan_price_cap'])
	                : (isset($ps['plan_price_cap']) && is_numeric($ps['plan_price_cap']) ? (int)$ps['plan_price_cap'] : null);

	            $recommended = null;
	            if (isset($o['recommended_limit_price']) && is_numeric($o['recommended_limit_price'])) {
	                $recommended = (int)$this->tickRule->roundDown((float)$o['recommended_limit_price']);
	            }

	            $action = (string)($o['action'] ?? 'WAIT');
	            // Hard guarantee: REJECT/DELAY must not emit PLACE_LIMIT.
	            if ($decision !== 'APPROVE' && strtoupper($action) === 'PLACE_LIMIT') {
	                $action = 'WAIT';
	                $recommended = null;
	            }

	            $reasons = [];
	            if (isset($o['reasons']) && is_array($o['reasons'])) {
	                $reasons = array_values($o['reasons']);
	            } else {
	                // backward-compat: reason_code string
	                $reasonCode = isset($o['reason_code']) && is_string($o['reason_code']) ? (string)$o['reason_code'] : '';
	                $lvl = (strtoupper($action) === 'WAIT') ? 'SOFT_BLOCK' : 'INFO';
	                $reasons = $this->buildCandidateReasonObjects($reasonCode !== '' ? [$reasonCode] : [], $lvl);
	            }

	            $orders[] = [
	                'n' => $n,
	                'time_window' => (string)($o['time_window'] ?? ($ps['time'] ?? '')),
	                'action' => $action,
	                'lots' => isset($o['lots']) && is_numeric($o['lots']) ? (int)$o['lots'] : null,
	                'plan_limit_price' => $planLimit,
	                'plan_price_cap' => $planCap,
	                'recommended_limit_price' => $recommended,
	                'reasons' => $this->normalizeCandidateReasons($reasons),
	                'inputs_used' => [
	                    'ask_best' => isset($o['inputs_used']['ask_best']) && is_numeric($o['inputs_used']['ask_best']) ? (int)$this->tickRule->roundDown((float)$o['inputs_used']['ask_best']) : (is_numeric($ask1) ? (int)$this->tickRule->roundDown((float)$ask1) : null),
	                    'bid_best' => isset($o['inputs_used']['bid_best']) && is_numeric($o['inputs_used']['bid_best']) ? (int)$this->tickRule->roundDown((float)$o['inputs_used']['bid_best']) : (is_numeric($bid1) ? (int)$this->tickRule->roundDown((float)$bid1) : null),
	                    'spread_pct' => $spreadPct,
	                    'snapshot_age_sec' => $ageSec,
	                ],
	            ];
	        }

            $byTicker[$ticker] = [
	            'checked_at' => $checkedAt,
	            'decision' => (string)($r['decision'] ?? 'REJECT'),
	            'eligible_now' => (bool)($r['eligible_now'] ?? false),
	            'next_check_at' => isset($r['next_check_at']) && $r['next_check_at'] !== '' ? (string)$r['next_check_at'] : null,
	            'reasons' => isset($r['reasons']) && is_array($r['reasons']) ? array_values($r['reasons']) : [],
	            'retry' => [
                    'retry_count' => $retryCount,
                    'max_retry_windows' => $maxRetryWindows,
                ],
                'computed' => [
                    'gap_pct' => $gapPct,
                    'spread_pct' => $spreadPct,
                    'chase_pct' => $chasePct,
                    'snapshot_age_sec' => $ageSec,
                ],
	            'recommended_orders' => $orders,
	        ];
	    }

	    $checked = count($byTicker);
	    if ($checked === 0) {
	        $status = 'none';
	    } elseif ($checked < $totalRelevant) {
	        $status = 'partial';
	    } else {
	        $status = 'complete';
	    }
	    if (!empty($byTicker)) ksort($byTicker);

	    return [
	        'status' => $status,
	        'checked_count' => $checked,
	        'by_ticker' => $byTicker,
	    ];
	}

	/**
	 * Resolve CONFIRM guards per policy from config.
	 *
	 * @return array{max_gap_up_pct?:float,max_chase_from_close_pct?:float,max_spread_pct?:float}
	 */
	private function confirmGuardsForPolicy(string $policy): array
	{
	    // Source-of-truth: ScorecardConfig per policy.
	    $g = $this->scorecardCfg->guardsForPolicy($policy);
	    return [
	        // legacy naming kept for internal helpers that still expect these keys
	        'max_gap_up_pct' => isset($g['gap_up_block_pct']) ? (float)$g['gap_up_block_pct'] : 0.03,
	        'max_chase_from_close_pct' => isset($g['max_chase_pct']) ? (float)$g['max_chase_pct'] : 0.02,
	        'max_spread_pct' => isset($g['spread_max_pct']) ? (float)$g['spread_max_pct'] : 0.015,
	        // depth guards (optional)
	        'depth_top_n' => isset($g['depth_top_n']) ? (int)$g['depth_top_n'] : 0,
	        'min_depth_lots_bid' => isset($g['min_depth_lots_bid']) ? (int)$g['min_depth_lots_bid'] : 0,
	        'min_depth_lots_ask' => isset($g['min_depth_lots_ask']) ? (int)$g['min_depth_lots_ask'] : 0,
	    ];
	}

	/**
	 * Best-effort: return HH:MM:SS from ISO datetime or already-time string.
	 */
	private function timeOnly(string $isoOrTime): string
	{
	    $s = trim($isoOrTime);
	    if ($s === '') return '';
	    // If already HH:MM(:SS)
	    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $s)) return (strlen($s) === 5) ? ($s . ':00') : $s;
	    // ISO 8601: YYYY-MM-DDTHH:MM:SS...
	    if (strpos($s, 'T') !== false) {
	        $t = explode('T', $s, 2)[1];
	        $t = preg_replace('/Z$/', '', $t);
	        $t = preg_replace('/[+\-]\d{2}:?\d{2}$/', '', $t);
	        $t = trim($t);
	        if (preg_match('/^\d{2}:\d{2}(:\d{2})?/', $t, $m)) {
	            $t = $m[0];
	            return (strlen($t) === 5) ? ($t . ':00') : $t;
	        }
	    }
	    // Space-separated datetime
	    if (strpos($s, ' ') !== false) {
	        $t = trim(explode(' ', $s)[1] ?? '');
	        if (preg_match('/^\d{2}:\d{2}(:\d{2})?/', $t, $m)) {
	            $t = $m[0];
	            return (strlen($t) === 5) ? ($t . ':00') : $t;
	        }
	    }
	    return $s;
	}

	/**
	 * Check whether HH:MM:SS is inside any windows.
	 * Window syntax: "HH:MM-HH:MM", and may use tokens "open"/"close".
	 */
    /** @return int|null minutes since 00:00 */
    private function toIsoCheckedAt(string $updatedAt, string $tradeDate, string $checkedAt): string
        {
	    $updatedAt = trim($updatedAt);
	    if ($updatedAt !== '') return $updatedAt;
	    $checkedAt = trim($checkedAt);
	    if ($checkedAt !== '' && $tradeDate !== '') {
	        return $tradeDate . 'T' . $checkedAt;
	    }
	    return '';
	}

    private function policyMeta(string $policy, array $opts, string $execTradeDate): array
    {
        // Base defaults per docs/watchlist/*.md
        $base = [
            'policy_version' => '1.0',
            'risk_per_trade_pct' => 0.0,
            'max_positions' => 0,
            'min_alloc_idr' => 0,
            'min_lots' => 0,
            // will be overridden by buildTiming
            'max_positions_today' => 0,
            'size_multiplier' => 0.0,
        ];

        switch ($policy) {
            case 'WEEKLY_SWING':
                $base['risk_per_trade_pct'] = 0.0075;
                $base['max_positions'] = 2;
                $base['min_alloc_idr'] = 500000;
                $base['min_lots'] = 1;
                $base['min_net_edge_pct'] = 0.008;
                break;
            case 'DIVIDEND_SWING':
                $base['risk_per_trade_pct'] = 0.0060;
                $base['max_positions'] = 2;
                $base['min_alloc_idr'] = 750000;
                $base['min_lots'] = 1;
                $base['min_net_edge_pct'] = 0.010;
                break;
            case 'INTRADAY_LIGHT':
                $base['risk_per_trade_pct'] = 0.0030;
                $base['max_positions'] = 1;
                $base['min_alloc_idr'] = 500000;
                $base['min_lots'] = 1;
                break;
            case 'POSITION_TRADE':
                $base['risk_per_trade_pct'] = 0.0100;
                $base['max_positions'] = 3;
                $base['min_alloc_idr'] = 1000000;
                $base['min_lots'] = 1;
                break;
            case 'NO_TRADE':
            default:
                // keep zeros
                break;
        }

        // Optional overrides
        if (isset($opts['risk_per_trade_pct']) && $opts['risk_per_trade_pct'] !== null && $opts['risk_per_trade_pct'] !== '') {
            $v = (float) $opts['risk_per_trade_pct'];
            if ($v >= 0) $base['risk_per_trade_pct'] = $v;
        }

        return $base;
    }

    private function buildTiming(string $policy, string $execTradeDate, array $policyMeta, array $globalLockCodes): array
    {
        // For NO_TRADE policy, treat as globally disabled even if there are no global lock codes.
        $tradeDisabled = (!empty($globalLockCodes) || $policy === 'NO_TRADE');

        $dow = $this->dayOfWeek($execTradeDate);
        $entry = [];
        $avoid = [];
        $maxToday = 0;
        $sizeMult = 0.0;

        if ($policy === 'WEEKLY_SWING') {
            $entry = ["09:20-10:30", "13:35-14:30"];
            $avoid = ["09:00-09:15", "15:50-close"];

            if ($dow === 'Tue') { $maxToday = 2; $sizeMult = 1.0; }
            elseif ($dow === 'Wed') { $maxToday = 2; $sizeMult = 0.8; }
            elseif ($dow === 'Thu') { $maxToday = 1; $sizeMult = 0.6; }
            elseif ($dow === 'Mon' || $dow === 'Fri') { $maxToday = 0; $sizeMult = 0.0; $entry = []; }
            else { $maxToday = 0; $sizeMult = 0.0; $entry = []; }
        }
        elseif ($policy === 'DIVIDEND_SWING') {
            $entry = ["09:20-10:30", "13:35-14:30"];
            $avoid = ["09:00-09:20", "14:30-close"];

            if ($dow === 'Fri') { $maxToday = 0; $sizeMult = 0.0; $entry = []; }
            else { $maxToday = 2; $sizeMult = 1.0; }
        }
        elseif ($policy === 'INTRADAY_LIGHT') {
            $entry = ["09:20-10:15", "13:35-14:15"];
            $avoid = ["09:00-09:15", "11:30-13:30", "15:15-close"];
            $maxToday = 1;
            $sizeMult = 1.0;
        }
        elseif ($policy === 'POSITION_TRADE') {
            $entry = ["09:20-10:30", "13:35-14:30"];
            $avoid = ["09:00-09:20", "14:30-close"];
            $maxToday = (int)($policyMeta['max_positions'] ?? 3);
            $sizeMult = 1.0;
        }
        else { // NO_TRADE or unknown
            $entry = [];
            $avoid = ["open-close"];
            $maxToday = 0;
            $sizeMult = 0.0;
        }

        // For global locks, enforce full-day avoid
        if ($tradeDisabled) {
            $entry = [];
            $avoid = ["open-close"];
            $maxToday = 0;
            $sizeMult = 0.0;
        }

        return [
            'entry_windows' => $entry,
            'avoid_windows' => $avoid,
            'max_positions_today' => $maxToday,
            'size_multiplier' => $sizeMult,
            'trade_disabled' => $tradeDisabled,
        ];
    }

    /**
     * Build one candidate. Return null for DROP (hard filter).
     *
     * @param array<int,array> $statusByTicker
     * @param array<int,array> $intradayByTicker
     * @param array<int,array> $divEventsByTicker
     * @param array<int,array> $openPositions
     * @param array<int,string> $globalLockCodes
     */
    private function buildCandidate(
        CandidateInput $ci,
        string $policy,
        string $execTradeDate,
        \DateTimeImmutable $now,
        array $session,
        array $statusByTicker,
        bool $hasAnyTickerStatus,
        array $divEventsByTicker,
        array $openPositions,
        array $globalLockCodes
    ): ?array {
        $r = $ci->toArray();

        $tickerId = (int)($r['ticker_id'] ?? 0);
        $tickerCode = (string)($r['ticker_code'] ?? '');
        $companyName = (string)($r['company_name'] ?? '');

        // Keep OHLC as nullable first; Universe Filter must DROP missing/invalid data (docs/watchlist/watchlist.md).
        $close = (isset($r['close']) && is_numeric($r['close'])) ? (float)$r['close'] : null;
        $open  = (isset($r['open']) && is_numeric($r['open'])) ? (float)$r['open'] : null;
        $high  = (isset($r['high']) && is_numeric($r['high'])) ? (float)$r['high'] : null;
        $low   = (isset($r['low']) && is_numeric($r['low'])) ? (float)$r['low'] : null;
        $volume = (isset($r['volume']) && is_numeric($r['volume'])) ? (float)$r['volume'] : null;

        $ma20 = $r['ma20'] ?? null;
        $ma50 = $r['ma50'] ?? null;
        $ma200 = $r['ma200'] ?? null;
        $rsi14 = $r['rsi14'] ?? null;
        $atr14 = $r['atr14'] ?? null;
        $volRatio = $r['vol_ratio'] ?? null;
        $liqBucket = (string)($r['liq_bucket'] ?? 'U');
        // Liquidity is stored as IDR metrics in repo output (dv20_idr / turnover20_idr).
        // $dv20 will be set later after Universe gates (dv20 preferred, else turnover20 fallback).
        $dv20 = null;
        $setupType = $this->setupClassifier->classify($ci);

        // Universe Filter (GLOBAL hard rules) — DROP jika data tidak lengkap / indikator missing.
        // Kontrak: ticker gagal Universe tidak boleh muncul di groups.* (docs/watchlist/watchlist.md).
        if ($tickerId <= 0 || $tickerCode === '') return null;

        // Data readiness gate: OHLCV wajib valid.
        if ($close === null || $open === null || $high === null || $low === null || $volume === null) return null;
        if ($close <= 0 || $open <= 0 || $high <= 0 || $low <= 0 || $volume <= 0) return null;

        // Basic OHLC sanity.
        if ($high < max($open, $close) || $low > min($open, $close) || $low > $high) return null;

        // Indicator readiness gate: ATR must exist.
        // NOTE: legacy global score_total from ticker_indicators_daily has been removed;
        // scoring now lives in the policy layer / watchlist snapshot.
        if ($atr14 === null || !is_numeric($atr14) || (float)$atr14 <= 0) return null;

        // Universe gates (docs/watchlist/watchlist.md): liquidity, price sanity, extreme volatility.
        $minPrice = (float)config('trade.watchlist.universe.min_price', 50);
        $maxAtrPct = (float)config('trade.watchlist.universe.max_atr_pct_universe', 0.20);
        $minDv20 = (float)config('trade.watchlist.universe.min_dv20_idr', 2000000000);
        $minTurnover20 = (float)config('trade.watchlist.universe.min_turnover20_idr', 2000000000);

        if ($close < $minPrice) return null;

        $atrPctUniverse = ($close > 0 && $atr14 !== null && is_numeric($atr14)) ? ((float)$atr14 / (float)$close) : null;
        if ($atrPctUniverse === null) return null;
        if ($atrPctUniverse > $maxAtrPct) return null;

        // Liquidity gate (dv20_idr preferred, else turnover20_idr fallback) — both in IDR, same scale.
        // Backward-compat keys: dv20/turnover20 may appear in older fixtures.
        $dv20Raw = $r['dv20_idr'] ?? ($r['dv20'] ?? null);
        $turnover20Raw = $r['turnover20_idr'] ?? ($r['turnover20'] ?? null);
        $dv20Val = (is_numeric($dv20Raw) ? (float)$dv20Raw : null);
        $turnover20Val = (is_numeric($turnover20Raw) ? (float)$turnover20Raw : null);

        if ($dv20Val !== null) {
            if ($dv20Val < $minDv20) return null;
        } elseif ($turnover20Val !== null) {
            if ($turnover20Val < $minTurnover20) return null;
        } else {
            return null;
        }

        // Effective liquidity passed into policy layer (dv20 preferred, else turnover20 fallback).
        $dv20 = ($dv20Val !== null) ? $dv20Val : $turnover20Val;

        // derived candle metrics (docs/watchlist/watchlist.md Section 2.5)
        $candle = $this->deriveCandleMetrics($open, $high, $low, $close);
        // ticker flags as-of exec date (docs 2.6)
        $st = $statusByTicker[$tickerId] ?? null;
        // Contract: missing status on trade date defaults to REGULAR (not UNKNOWN).
        $tickerFlags = [
            'special_notations' => $st ? (array)($st['special_notations'] ?? []) : [],
            'is_suspended' => $st ? (bool)($st['is_suspended'] ?? false) : false,
            'status_quality' => $st ? (string)($st['status_quality'] ?? 'OK') : 'DEFAULT',
            'status_asof_trade_date' => $st ? (string)($st['status_asof_trade_date'] ?? $execTradeDate) : null,
            'trading_mechanism' => $st ? (string)($st['trading_mechanism'] ?? 'REGULAR') : 'REGULAR',
        ];

        $reasonCodes = [];

        // Hard trade-disable (ticker-level). Global locks (session/no-entry/EOD readiness)
        // are applied later via global timing and must not affect PLAN selection.
        $tradeDisabled = false;
        $hardLockCodes = [];

        // status quality flags
        if ($hasAnyTickerStatus && $tickerFlags['status_quality'] === 'DEFAULT') $reasonCodes[] = 'GL_TICKER_STATUS_DEFAULTED_REGULAR';
        if ($tickerFlags['status_quality'] === 'UNKNOWN') $reasonCodes[] = 'GL_TICKER_STATUS_UNKNOWN';

        // global tradeability gating (docs 2.6.2)
        if ($tickerFlags['is_suspended'] === true) {
            $tradeDisabled = true;
            $reasonCodes[] = 'GL_SUSPENDED';
            $hardLockCodes[] = 'GL_SUSPENDED';
        }

        $hasX = in_array('X', $tickerFlags['special_notations'], true);
        $hasE = in_array('E', $tickerFlags['special_notations'], true);

        if ($hasE) $reasonCodes[] = 'GL_SPECIAL_NOTATION_E';

        if ($tickerFlags['trading_mechanism'] === 'FULL_CALL_AUCTION') {
            $tradeDisabled = true;
            $reasonCodes[] = 'GL_MECHANISM_FCA';
            $hardLockCodes[] = 'GL_MECHANISM_FCA';
        }
        if ($hasX) {
            $tradeDisabled = true;
            $reasonCodes[] = 'GL_SPECIAL_NOTATION_X';
            $hardLockCodes[] = 'GL_SPECIAL_NOTATION_X';
        }

        // policy hard filters and scoring
        $policyRes = $this->applyPolicyRules(
            $policy,
            [
                'ticker_id' => $tickerId,
                'ticker_code' => $tickerCode,
                'close' => $close,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'ma20' => $ma20,
                'ma50' => $ma50,
                'ma200' => $ma200,
                'rsi14' => $rsi14,
                'atr14' => $atr14,
                'atr_pct' => ($atr14 !== null && $close > 0) ? ((float)$atr14 / $close) : null,
                'vol_ratio' => $volRatio,
                'vol_sma20' => $r['vol_sma20'] ?? null,
                'liq_bucket' => $liqBucket,
                'dv20' => $dv20,
                'hh20' => $r['hh20'] ?? null,
                'hh50' => $r['hh50'] ?? null,
                'll5' => $r['ll5'] ?? null,
                'hh10' => $r['hh10'] ?? null,
                'll3' => $r['ll3'] ?? null,
                'roc5' => $r['roc5'] ?? null,
                'roc20' => $r['roc20'] ?? null,
                'signal_code' => $r['signal_code'] ?? null,
                'signal_age_days' => $r['signal_age_days'] ?? null,
                'setup_type' => $setupType,
                'div_event' => $divEventsByTicker[$tickerId] ?? null,
                'exec_trade_date' => $execTradeDate,
                'candle' => $candle,
                'prev_close' => $r['prev_close'] ?? null,
            ],
            $reasonCodes
        );

		// IMPORTANT (docs/watchlist/watchlist.md):
		// - Hard-rule FAIL must NOT remove the ticker from PLAN output.
		// - PLAN_INVALID must be EXCLUDED from this policy output.
		$reasonCodes = (array)($policyRes['reason_codes'] ?? $reasonCodes);
		$eligBlockCodes = (array)($policyRes['eligibility_block_codes'] ?? []);

		if (PlanInvalidClassifier::any($reasonCodes)) {
			return null; // EXCLUDE (PLAN_INVALID) for this policy
		}

		$policyHardFail = (($policyRes['drop'] ?? false) === true);

		// Score is for ranking/grouping. Contract: output score_total MUST be 0..1.
		// IMPORTANT (docs/watchlist/watchlist.md): hard-rule FAIL must NOT remove the ticker from PLAN output.
		// Therefore, even when policyHardFail=true, we keep a stable score_total for grouping (eligibility will be blocked).
		$rawScoreTotal = 0.0;
		if (isset($policyRes['score_total']) && is_numeric($policyRes['score_total'])) {
			$rawScoreTotal = (float)$policyRes['score_total'];
		} elseif (isset($policyRes['score']) && is_numeric($policyRes['score'])) {
			// Backward-compat: some policy helpers return 'score' (0..1) instead of 'score_total'.
			$rawScoreTotal = (float)$policyRes['score'];
		} elseif (isset($r['score_total']) && is_numeric($r['score_total'])) {
			$rawScoreTotal = (float)$r['score_total'];
		}
		$scoreTotal = WatchlistScoreScale::toScore01($rawScoreTotal);

		// no_trade is not a ranked bucket; never surface it as a "perfect" score.
		if (strtoupper((string)$policy) === 'NO_TRADE') {
			$scoreTotal = 0.0;
		}


		$entryStyle = (string)($policyRes['entry_style'] ?? 'Default');
		if ($policyHardFail) {
			// Ensure we actually block eligibility even if policy forgot to populate block codes.
			if (empty($eligBlockCodes)) {
				$eligBlockCodes[] = ($this->policyPrefix($policy) . '_HARD_RULE_FAIL');
			}
		}

		// Enrich reason message when policy input is missing (docs/watchlist/watchlist.md).
		$customReasons = [];
		if (in_array('GL_POLICY_INPUT_MISSING', $reasonCodes, true)) {
			$missing = [];
			$req = [];
			if ($policy === 'WEEKLY_SWING') $req = ['ma20','ma50','hh20','ll5','dv20_idr','atr14'];
			elseif ($policy === 'DIVIDEND_SWING') $req = ['ma20','ma50','hh20','hh50','ll5','atr14','div_event.ex_date'];
			elseif ($policy === 'INTRADAY_LIGHT') $req = ['dv20_idr','atr_pct','vol_ratio','hh10','ll3','roc5'];
			elseif ($policy === 'POSITION_TRADE') $req = ['ma200','ma50'];
			foreach ($req as $k) {
				if ($k === 'dv20_idr') {
					// $dv20 is already normalized (dv20 or turnover20 fallback) after universe gate.
					if (!isset($dv20) || !is_numeric($dv20) || (float)$dv20 <= 0) $missing[] = $k;
				} elseif ($k === 'atr_pct') {
					$ap = ($atr14 !== null && $close > 0) ? ((float)$atr14 / (float)$close) : null;
					if ($ap === null || $ap <= 0) $missing[] = $k;
				} elseif ($k === 'div_event.ex_date') {
					$ev = $divEventsByTicker[$tickerId] ?? null;
					$ex = (is_array($ev) ? ($ev['ex_date'] ?? null) : (is_object($ev) ? ($ev->ex_date ?? null) : null));
					if ($ex === null || (string)$ex === '') $missing[] = $k;
				} else {
					$val = $r[$k] ?? null;
					if ($val === null || $val === '') {
						$missing[] = $k;
					}
				}
			}
			$missing = array_values(array_unique(array_filter($missing, 'is_string')));
			if (!empty($missing)) {
				$customReasons[] = [
					'code' => 'GL_POLICY_INPUT_MISSING',
					'message' => 'Policy input missing. missing_fields=[' . implode(',', $missing) . ']',
					'severity_level' => 'HARD_EXCLUDE',
				];
			}
		}

        // Policy may override setup_type for deterministic plan (e.g. WEEKLY_SWING).
        $setupType = (string)($policyRes['setup_type'] ?? $setupType);

        $levels = (isset($policyRes['levels']) && is_array($policyRes['levels']))
            ? (array)$policyRes['levels']
            : $this->buildLevels($setupType, $r);
        // close_price is required for CONFIRM (gap/chase computations); keep inside levels for easy access
        $levels['close_price'] = (int) round((float)$close);
		if ($policyHardFail) {
			// Make it explicit for UI: hard-rule fail means monitoring only.
			$levels['entry_type'] = 'WATCH_ONLY';
		}
        if ($tradeDisabled) {
            $levels['entry_type'] = 'WATCH_ONLY';
        }

        $sizing = $this->buildSizing($levels);

        if ($policy === 'INTRADAY_LIGHT') {
            // Intraday Light is strict on level validity, but it should not silently DROP here.
            // If PLAN levels are incomplete, downgrade to WATCH_ONLY and block eligibility.
            $lvOk = true;
            foreach (['entry_trigger_price','stop_loss_price','tp1_price','tick_size'] as $k) {
                if (!isset($levels[$k]) || !is_numeric($levels[$k]) || (float)$levels[$k] <= 0) { $lvOk = false; break; }
            }
            if (!$lvOk) {
                $levels['entry_type'] = 'WATCH_ONLY';
                $reasonCodes[] = 'IL_LEVELS_INCOMPLETE';
                $eligBlockCodes[] = 'IL_LEVELS_INCOMPLETE';
                $eligBlockCodes = array_values(array_unique($eligBlockCodes));
            }
        }

        // Position context (optional)
        $pos = $openPositions[$tickerId] ?? null;
        $positionObj = null;
        if ($pos) {
            $entryDate = $pos['entry_date'] ?? null;
            $daysHeld = null;
            if ($entryDate) {
                $daysHeld = $this->tradingDaysBetweenInclusive((string)$entryDate, (string)$execTradeDate);
            }
            $positionObj = [
                'has_position' => true,
                'position_avg_price' => (float)($pos['avg_price'] ?? 0),
                'position_lots' => (int)($pos['position_lots'] ?? 0),
                'entry_date' => $entryDate,
                'days_held' => $daysHeld,
                'position_state' => 'OPEN',
                'action_windows' => [],
                'updated_stop_loss_price' => null,
            ];
        } else {
            $positionObj = [
                'has_position' => false,
                'position_avg_price' => null,
                'position_lots' => null,
                'entry_date' => null,
                'days_held' => null,
                'position_state' => null,
                'action_windows' => [],
                'updated_stop_loss_price' => null,
            ];
        }

        // Exit / management hints for existing positions (docs/watchlist/* Exit rules)
        if ($positionObj['has_position'] === true) {
            $pm = $this->buildPositionManagement(
                $policy,
                $positionObj,
                [
                    'ticker_id' => $tickerId,
                    'close' => $close,
                    'ma50' => $ma50,
                    'atr14' => $atr14,
                    'levels' => $levels,
                    'setup_type' => $setupType,
                ],
                $execTradeDate,
                $session,
                $now
            );

            $positionObj['position_state'] = $pm['position_state'];
            $positionObj['action_windows'] = $pm['action_windows'];
            $positionObj['updated_stop_loss_price'] = $pm['updated_stop_loss_price'];
            foreach ($pm['reason_codes'] as $rc) { $reasonCodes[] = $rc; }
            $reasonCodes = array_values(array_unique($reasonCodes));
        }

        // Eligibility flag (used for allocations / "NEW ENTRY" filtering; MUST NOT override PLAN grouping)
        $elig = $this->evaluateEligibilityForNewEntry($policy, $eligBlockCodes, $tradeDisabled);

        $tickRef = (int) $this->tickRule->tickSize(max(1.0, $close));
        $derived = (isset($policyRes['derived']) && is_array($policyRes['derived'])) ? (array)$policyRes['derived'] : [
            'dv20_idr' => ($dv20 !== null && is_numeric($dv20)) ? (int)round((float)$dv20) : null,
            'atr_pct' => ($atr14 !== null && $close > 0) ? round(((float)$atr14 / (float)$close), 4) : null,
            'tick_pct' => ($close > 0) ? round($tickRef / (float)$close, 6) : null,
            'roc20' => (isset($r['roc20']) && is_numeric($r['roc20'])) ? round((float)$r['roc20'], 4) : null,
            'rvol20' => ($volRatio !== null && is_numeric($volRatio)) ? round((float)$volRatio, 4) : null,
        ];

        return [
            'ticker_id' => $tickerId,
            'ticker_code' => $tickerCode,
            'company_name' => $companyName,
            'policy' => $policy,
            'policy_tags' => [$policy],
            'setup_type' => $setupType,
            'entry_style' => $entryStyle,
            'score_total' => max(0.0, min(1.0, (float)$scoreTotal)),
            'confidence' => (string)($policyRes['confidence'] ?? 'Medium'),
            'reason_codes' => array_values(array_unique($reasonCodes)),

            // Eligibility blocks MUST be visible in PREOPEN reasons; otherwise UI shows watch_only with no explanation.
            // These codes are also used to determine plan.is_eligible_new_entry.
            'eligibility_block_codes' => array_values(array_unique($eligBlockCodes)),

            'reasons' => $customReasons,

            'ticker_flags' => $tickerFlags,

            'basis' => [
                'trade_date' => (string)($r['trade_date'] ?? ''),
                'open' => (int) round($open),
                'high' => (int) round($high),
                'low' => (int) round($low),
                'close' => (int) round($close),
                'prev_close' => (int) round($this->toFloat($r['prev_close'] ?? null, 0.0)),
                'volume' => (int)($r['volume'] ?? 0),
                'adj_close' => $r['adj_close'] ?? null,
                'ca_hint' => $r['ca_hint'] ?? null,
                'ca_event' => $r['ca_event'] ?? null,
                'is_valid' => $r['is_valid'] ?? null,
                'invalid_reason' => $r['invalid_reason'] ?? null,
            ],

            'indicators' => [
                'ma20' => $ma20 !== null ? (float)$ma20 : null,
                'ma50' => $ma50 !== null ? (float)$ma50 : null,
                'ma200' => $ma200 !== null ? (float)$ma200 : null,
                'rsi14' => $rsi14 !== null ? (float)$rsi14 : null,
                'atr14' => $atr14 !== null ? (float)$atr14 : null,
                'atr_pct' => ($atr14 !== null && $close > 0) ? round(((float)$atr14 / $close), 4) : null,
                'vol_ratio' => $volRatio !== null ? (float)$volRatio : null,
                'vol_sma20' => isset($r['vol_sma20']) ? (float)$r['vol_sma20'] : null,
                'dv20' => $dv20 !== null ? (float)$dv20 : null,
                'liq_bucket' => $liqBucket,
                'support_20d' => $r['support_20d'] ?? null,
                'resistance_20d' => $r['resistance_20d'] ?? null,
                'signal_code' => $r['signal_code'] ?? null,
                'signal_age_days' => $r['signal_age_days'] ?? null,
            ],

            'derived' => $derived,

            'levels' => $levels,
            'sizing' => $sizing,

            'timing' => [
                'entry_windows' => [], // global timing is in payload.timing
                'avoid_windows' => [],
                'trade_disabled' => $tradeDisabled,
                'max_positions_today' => null,
                'size_multiplier' => null,
            ],

            'position' => $positionObj,

            'debug' => [
                'tick_size' => $levels['tick_size'],
                'rank_reason_codes' => [],
                'raw' => [
                    'decision_code' => $r['decision_code'] ?? null,
                    'signal_code' => $r['signal_code'] ?? null,
                    'volume_label_code' => $r['volume_label_code'] ?? null,
                ],
            ],

            // internal
            '_eligibility' => [
                'is_eligible_new_entry' => $elig,
                'block_codes' => $eligBlockCodes,
            ],
            '_hard_lock_codes' => array_values(array_unique($hardLockCodes)),
            '_policy_rules' => [
                'size_multiplier_adj' => (float)($policyRes['size_multiplier_adj'] ?? 1.0),
                'shift_entry_windows' => $policyRes['shift_entry_windows'] ?? null,
            ],
        ];
    }

    /**
     * Build exit/management hints for an existing position.
     *
     * Output is intentionally lightweight: it enriches candidate.position with
     * suggested action windows, updated trailing stop (if computable), and
     * emits policy reason codes for UI clarity.
     *
     * @param array<string,mixed> $positionObj
     * @param array<string,mixed> $ctx
     * @param array<string,mixed> $session
     * @return array{position_state:string,action_windows:array<int,string>,updated_stop_loss_price:int|null,reason_codes:array<int,string>}
     */
    private function buildPositionManagement(string $policy, array $positionObj, array $ctx, string $execTradeDate, array $session, \DateTimeImmutable $now): array
    {
        $entryDate = (string)($positionObj['entry_date'] ?? '');
        $avg = (float)($positionObj['position_avg_price'] ?? 0);
        $daysHeld = $positionObj['days_held'] !== null ? (int)$positionObj['days_held'] : null;

        $tickerId = (int)($ctx['ticker_id'] ?? 0);
        $close = (float)($ctx['close'] ?? 0);
        $ma50 = $ctx['ma50'] !== null ? (float)$ctx['ma50'] : null;
        $atr14 = $ctx['atr14'] !== null ? (float)$ctx['atr14'] : null;
        $levels = is_array($ctx['levels'] ?? null) ? $ctx['levels'] : [];
        $tick = (int)($levels['tick_size'] ?? 1);
        $tp1 = isset($levels['tp1_price']) ? (int)$levels['tp1_price'] : null;
        $be = isset($levels['be_price']) ? (int)$levels['be_price'] : null;

        $retPct = null;
        if ($avg > 0 && $close > 0) $retPct = ($close / $avg) - 1.0;

        $reason = [];
        $actionWindows = $this->defaultActionWindowsForPolicy($policy, $session);

        $posState = 'HOLD';
        $updatedSL = null;

        // Helpers for EOD-derived trailing context (only when entry_date is known)
        $mxClose = null;
        $mxHigh = null;
        if ($tickerId > 0 && $entryDate !== '' && $entryDate <= $execTradeDate) {
            $mxClose = $this->watchRepo->maxCloseBetween($tickerId, $entryDate, $execTradeDate);
            $mxHigh = $this->watchRepo->maxHighBetween($tickerId, $entryDate, $execTradeDate);
        }

        $dow = $this->dayOfWeek($execTradeDate);

        if ($policy === 'NO_TRADE') {
            // Carry-only management (docs/watchlist/no_trade.md)
            $reason[] = 'NT_CARRY_ONLY_MANAGEMENT';
            $posState = 'HOLD';
            $actionWindows = ['open-close'];
            return [
                'position_state' => $posState,
                'action_windows' => $actionWindows,
                'updated_stop_loss_price' => $updatedSL,
                'reason_codes' => $reason,
            ];
        }

        if ($policy === 'INTRADAY_LIGHT') {
            // docs/watchlist/intraday_light.md: flat before close + 90m time-stop when no follow-through.
            $reason[] = 'IL_FLAT_BEFORE_CLOSE';

            // Approximation: same-day position, >= 90 minutes after open, and return still small.
            if ($entryDate !== '' && $entryDate === $execTradeDate) {
                $tz = $now->getTimezone();
                $openHm = (string)($session['open_time'] ?? '09:00');
                try {
                    $openTs = new \DateTimeImmutable($execTradeDate . ' ' . $openHm . ':00', $tz);
                    $cut = $openTs->modify('+90 minutes');
                    if ($now >= $cut) {
                        if ($retPct === null || $retPct < 0.005) {
                            $reason[] = 'IL_TIME_STOP_90M';
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $posState = 'EXIT';
            // keep default windows so UI can guide exits
            return [
                'position_state' => $posState,
                'action_windows' => $actionWindows,
                'updated_stop_loss_price' => $updatedSL,
                'reason_codes' => $reason,
            ];
        }

        if ($policy === 'WEEKLY_SWING') {
            if ($daysHeld !== null && $retPct !== null) {
                if ($daysHeld >= 2 && $retPct < 0.010) $reason[] = 'WS_TIME_STOP_T2';
                if ($daysHeld >= 3 && $retPct < 0.015) $reason[] = 'WS_TIME_STOP_T3';
                if ($daysHeld >= 7) $reason[] = 'WS_MAX_HOLDING_REACHED';
            }
            if ($dow === 'Fri' && $retPct !== null && $retPct < 0.020) {
                $reason[] = 'WS_FRIDAY_EXIT_BIAS';
            }

            // Trailing stop: highest_close_since_entry - 2.0*ATR14
            if ($atr14 !== null) {
                $base = ($mxClose !== null) ? (float)$mxClose : $close;
                if ($base > 0) {
                    $trail = $base - (2.0 * (float)$atr14);
                    $trail = max((float)$tick, $trail);
                    $trail = $this->roundToTick($trail, $tick, 'down');
                    $trail = max((float)$tick, $trail);
                    $updatedSL = (int)$trail;
                    if ($close > 0 && $close <= (float)$trail) $reason[] = 'WS_TRAIL_STOP_HIT';
                }
            }
        }
        elseif ($policy === 'DIVIDEND_SWING') {
            if ($daysHeld !== null && $retPct !== null) {
                if ($daysHeld >= 2 && $retPct < 0.008) $reason[] = 'DS_TIME_STOP_T2';
                if ($daysHeld >= 6) $reason[] = 'DS_MAX_HOLDING_REACHED';
            }
            // (optional) trailing not defined in docs, so no updatedSL here
        }
        elseif ($policy === 'POSITION_TRADE') {
            // Partial take profit -> move SL to BE
            $tp1Hit = false;
            if ($tp1 !== null && $mxHigh !== null && (float)$mxHigh >= (float)$tp1) {
                $tp1Hit = true;
                $reason[] = 'PT_MOVE_SL_TO_BE';
                if ($be !== null) $updatedSL = (int)$be;
            }

            if ($daysHeld !== null) {
                if ($daysHeld >= 40) $reason[] = 'PT_MAX_HOLDING_REACHED';
            }

            // Fail-to-move time stop
            if ($daysHeld !== null && $daysHeld >= 20 && !$tp1Hit && $ma50 !== null && $close > 0 && $close < $ma50) {
                $reason[] = 'PT_TIME_STOP_T1';
            }

            // Trailing stop: highest_close_since_entry - 2.5*ATR14
            if ($atr14 !== null) {
                $base = ($mxClose !== null) ? (float)$mxClose : $close;
                if ($base > 0) {
                    $trail = $base - (2.5 * (float)$atr14);
                    $trail = max((float)$tick, $trail);
                    $trail = $this->roundToTick($trail, $tick, 'down');
                    $trail = max((float)$tick, $trail);

                    // pick the tighter (higher) stop between BE and trailing, if both exist
                    if ($updatedSL === null) $updatedSL = (int)$trail;
                    else $updatedSL = max((int)$updatedSL, (int)$trail);

                    if ($close > 0 && $close <= (float)$trail) $reason[] = 'PT_TRAIL_STOP_HIT';
                }
            }
        }

        // Position state inference for UI (not a contract enum; safe additive)
        $exitSignals = array_filter($reason, function($rc) {
            return (strpos($rc, '_MAX_HOLDING_') !== false)
                || (strpos($rc, '_TRAIL_STOP_HIT') !== false)
                || (strpos($rc, '_TIME_STOP_') !== false)
                || (strpos($rc, '_FRIDAY_EXIT_BIAS') !== false)
                || (strpos($rc, 'IL_FLAT_BEFORE_CLOSE') === 0);
        });

        if (!empty($exitSignals)) $posState = 'EXIT';
        elseif (in_array('PT_MOVE_SL_TO_BE', $reason, true)) $posState = 'REDUCE';

        return [
            'position_state' => $posState,
            'action_windows' => $actionWindows,
            'updated_stop_loss_price' => $updatedSL,
            'reason_codes' => array_values(array_unique($reason)),
        ];
    }

    /**
     * Reward/Risk ratio helper used by policy PLAN enrichment.
     */
    public function rrRatio(int $entry, int $sl, int $tp1): ?float
    {
        $risk = max(0, $entry - $sl);
        $reward = max(0, $tp1 - $entry);
        if ($risk <= 0) return null;
        return round($reward / $risk, 3);
    }

    /**
     * Net edge percentage helper used by policy PLAN enrichment.
     */
    public function netEdgePct(int $entry, int $lotSize, ?int $profitNet): ?float
    {
        if ($profitNet === null) return null;
        $cost = $entry * $lotSize;
        if ($cost <= 0) return null;
        return round($profitNet / $cost, 4);
    }

    /**
     * Default action windows for managing existing positions.
     * Reuses policy timing windows to keep UI consistent.
     *
     * @param array<string,mixed> $session
     * @return array<int,string>
     */
    private function defaultActionWindowsForPolicy(string $policy, array $session): array
    {
        try {
            $policyFactory = new \App\Trade\Watchlist\Policies\PolicyFactory();
            $policyObj = $policyFactory->make($policy);
            return $policyObj->defaultActionWindows($session);
        } catch (\Throwable $e) {
            // fallback: full session
            return ['open-close'];
        }
    }
    
    private function applyPolicyRules(string $policy, array $x, array $reasonCodes): array
    {
        // Delegated per-policy implementation (Policies/*). WatchlistEngine remains orchestrator.
        $factory = new \App\Trade\Watchlist\Policies\PolicyFactory();
        $policyObj = $factory->make($policy);
        return $policyObj->apply($x, $reasonCodes, $this);
    }

    public function policyRes(bool $drop, float $score, string $entryStyle, string $confidence, array $reasonCodes, array $blockCodes, float $sizeMultiplierAdj = 1.0, ?string $shiftEntryWindows = null): array
    {
        return [
            'drop' => $drop,
            'score' => max(0.0, $score),
            'entry_style' => $entryStyle,
            'confidence' => $confidence,
            'reason_codes' => array_values(array_unique($reasonCodes)),
            'eligibility_block_codes' => array_values(array_unique($blockCodes)),
            'size_multiplier_adj' => $sizeMultiplierAdj,
            'shift_entry_windows' => $shiftEntryWindows,
        ];
    }

    public function evaluateEligibilityForNewEntry(string $policy, array $blockCodes, bool $tradeDisabled): bool
    {
        if ($tradeDisabled) return false;
        if (!empty($blockCodes)) return false;
        // NO_TRADE never eligible
        if ($policy === 'NO_TRADE') return false;
        return true;
    }

    /**
     * WEEKLY_SWING deterministic levels (EOD-only).
     * Spec: docs/watchlist/policy/weekly_swing.md
     *
     * @param string $setupType Expected: Breakout|Pullback (others treated as Pullback).
     * @param array{close:float,low:float,hh20:float|null,ll5:float|null} $ctx
     * @return array<string,mixed>
     */
    public function buildIntradayLightLevels(array $ctx, string $setupType): array
    {
        $close = (float)($ctx['close'] ?? 0);
        $low = (float)($ctx['low'] ?? 0);
        $ll3 = $ctx['ll3'] ?? null;

        if ($close <= 0 || $low <= 0) {
            return [
                'entry_trigger_price' => null,
                'stop_loss_price' => null,
                'tp1_price' => null,
                'tp2_price' => null,
                'tick_size' => null,
            ];
        }

        // Entry = round_up(close)
        $entry = (float)$this->tickRule->roundUp($close);

        // Stop = round_down(min(low, ll3) - tick)
        $minLow = $low;
        if ($ll3 !== null && is_numeric($ll3) && (float)$ll3 > 0) {
            $minLow = min($minLow, (float)$ll3);
        }
        $tickMin = (float)$this->tickRule->tickSize(max(1.0, $minLow));
        $sl = (float)$this->tickRule->roundDown($minLow - $tickMin);

        $tp1 = null;
        $tp2 = null;
        $R = $entry - $sl;
        if ($entry > 0 && $sl > 0 && $R > 0) {
            $tp1 = (float)$this->tickRule->roundDown($entry + (1.0 * $R));
            // tp2 is not locked by policy docs; keep deterministic for sizing/UI
            $tp2 = (float)$this->tickRule->roundDown($entry + (2.0 * $R));
        }

        $tickEntry = (int)$this->tickRule->tickSize(max(1.0, $entry));

        return [
            'entry_trigger_price' => (int)round($entry),
            'stop_loss_price' => (int)round($sl),
            'tp1_price' => $tp1 !== null ? (int)round((float)$tp1) : null,
            'tp2_price' => $tp2 !== null ? (int)round((float)$tp2) : null,
            'tick_size' => $tickEntry > 0 ? $tickEntry : null,
        ];
    }

    public function clamp01(float $v): float
    {
        if ($v < 0.0) return 0.0;
        if ($v > 1.0) return 1.0;
        return $v;
    }

    public function norm01(float $v, float $lo, float $hi): float
    {
        if ($hi <= $lo) return 0.0;
        return $this->clamp01(($v - $lo) / ($hi - $lo));
    }

    /**
     * Map PatternClassifier's signal_code to [0..1] score.
     * Deterministic; used by WEEKLY_SWING scoring.
     */
    public function wsPatternScoreFromSignal($signalCode): float
    {
        $s = is_numeric($signalCode) ? (int)$signalCode : 0;
        $map = [
            10 => 0.0, // false breakout
            9 => 0.1,  // climax
            8 => 0.2,  // distribution
            7 => 0.8,  // pullback healthy
            6 => 0.85, // breakout retest
            5 => 1.0,  // strong breakout
            4 => 0.9,  // breakout
            3 => 0.7,  // accumulation
            2 => 0.6,  // early uptrend
            1 => 0.4,  // base
            0 => 0.3,  // unknown
        ];
        return $this->clamp01($map[$s] ?? 0.3);
    }

    /**
     * Build price levels from EOD basis.
     *
     * Critical: this must be deterministic and MUST NOT depend on intraday/preopen snapshot.
     * Snapshot checks belong to CONFIRM output.
     *
     * @param string $setupType
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private function buildLevels(string $setupType, array $r): array
    {
        $close = (float)($r['close'] ?? 0);
        $atr = (float)($r['atr14'] ?? 0);
        $support = $r['support_20d'] ?? null;
        $resist = $r['resistance_20d'] ?? null;

        $tick = $this->tickRule->tickSize(max(1.0, $close));
        if ($tick <= 0) $tick = 1;

        // Entry trigger (limit/stop) logic simplified but deterministic
        $entry = $close;
        if ($setupType === 'Breakout') {
            $entry = $resist !== null ? max($close + $tick, (float)$resist) : ($close + $tick);
        }

        $risk = max((float)$tick, $atr * 1.5);
        $sl = $close - $risk;
        if ($support !== null) $sl = min($sl, (float)$support);
        $sl = max((float)$tick, $sl);

        $tp1 = $entry + 2.0 * max((float)$tick, ($entry - $sl));
        $tp2 = $entry + 3.0 * max((float)$tick, ($entry - $sl));

        $entry = $this->roundToTick($entry, $tick, 'up');
        $sl = $this->roundToTick($sl, $tick, 'down');
        $tp1 = $this->roundToTick($tp1, $tick, 'down');
        $tp2 = $this->roundToTick($tp2, $tick, 'down');

        return [
            'tick_size' => $tick,
            'entry_type' => 'LIMIT',
            'entry_trigger_price' => (int)$entry,
            // breakeven reference (simplified): entry price
            'be_price' => (int)$entry,
            'stop_loss_price' => (int)$sl,
            'tp1_price' => (int)$tp1,
            'tp2_price' => (int)$tp2,
            'max_chase_from_close_pct' => null,
        ];
    }

    private function buildSizing(array $levels): array
    {
        $entry = (int)($levels['entry_trigger_price'] ?? 0);
        $sl = (int)($levels['stop_loss_price'] ?? 0);
        $tp2 = (int)($levels['tp2_price'] ?? 0);
        $tick = (int)($levels['tick_size'] ?? 1);

        $riskPerShare = max($tick, $entry - $sl);
        $rrTp2 = ($riskPerShare > 0) ? (($tp2 - $entry) / $riskPerShare) : null;

        // net PnL per 1 lot (100 shares) using fee model (docs 2.4)
        $shares = 100;
        $buyNotional = max(0, $entry * $shares);
        $sellNotional = max(0, $tp2 * $shares);

        $buyFee = (int) ceil($buyNotional * $this->buyFeePct);
        $sellFee = (int) ceil($sellNotional * $this->sellFeePct);
        $slipBuy = (int) ceil($buyNotional * $this->slippagePct);
        $slipSell = (int) ceil($sellNotional * $this->slippagePct);

        $gross = $sellNotional - $buyNotional;
        $net = $gross - $buyFee - $sellFee - $slipBuy - $slipSell;

        $riskIdr = $riskPerShare * $shares;
        $rrNet = ($riskIdr > 0) ? ($net / $riskIdr) : null;

        return [
            'lot_size' => 100,
            'risk_per_share' => $riskPerShare,
            'rr_tp2' => $rrTp2 !== null ? round((float)$rrTp2, 3) : null,
            'profit_tp2_net' => (int) $net,
            'rr_tp2_net' => $rrNet !== null ? round((float)$rrNet, 3) : null,
            'lots_recommended' => null,
            'estimated_cost' => null,
        ];
    }

    private function buildRecommendations(
        string $policy,
        array $policyMeta,
        array $globalLockCodes,
        array $openPositions,
        ?int $capitalTotal,
        array &$candidates,
        array $topPickIndices
    ): array {
        $riskPct = (float)($policyMeta['risk_per_trade_pct'] ?? 0.0);
        $maxPos = (int)($policyMeta['max_positions'] ?? 0);
        $maxToday = (int)($policyMeta['max_positions_today'] ?? 0);
        $sizeMult = (float)($policyMeta['size_multiplier'] ?? 0.0);

        // default
        $mode = 'NO_TRADE';
        $allocs = [];
        $skipped = [];

        $openCount = 0;
        if (!empty($openPositions)) {
            // $openPositions is keyed by ticker_id
            $openCount = count($openPositions);
        }
        $hasOpenPositions = ($openCount > 0);

        // If caller does not provide capital, we still produce PLAN (pure EOD selection) without allocations.
        if ($capitalTotal === null) {
            // Mode A: no capital sizing. Still return a ranked list of recommended tickers
            // so UI/users can see what the system would pick (docs/watchlist/watchlist.md).
            // Exposure control: if there are already open positions, reduce how many NEW positions we propose today.
            // target_today = min(max_positions_today, max_positions - open_positions_count)
            $targetNoCap = min($maxToday, max(0, $maxPos - $openCount));
            $targetNoCap = max(0, $targetNoCap);

            $allocsNoCap = [];
            $selectedIdxNoCap = [];
            $skippedNoCap = [];
            $skippedMap = [];

            $addSkip = function (array $row) use (&$skippedNoCap, &$skippedMap): void {
                $tk = (string)($row['ticker_code'] ?? '');
                if ($tk === '') return;
                if (isset($skippedMap[$tk])) return;
                $skippedMap[$tk] = true;
                $skippedNoCap[] = $row;
            };

            foreach ($topPickIndices as $idx) {
                if ($targetNoCap > 0 && count($allocsNoCap) >= $targetNoCap) break;
                $c = $candidates[$idx] ?? null;
                if (!$c) continue;

                // Already-held positions are not NEW recommendations.
                $tid = (int)($c['ticker_id'] ?? 0);
                if ($tid > 0 && isset($openPositions[$tid])) {
                    $addSkip([
                        'ticker_code' => (string)($c['ticker_code'] ?? ''),
                        'reason_code' => 'GL_ALREADY_HELD',
                        'alloc_budget' => null,
                        'entry_price_ref' => (int)($c['levels']['entry_trigger_price'] ?? 0),
                    ]);
                    continue;
                }

                // Hard locks never get recommendations.
                if (!empty($c['plan']['hard_lock_codes'] ?? [])) {
                    $addSkip([
                        'ticker_code' => (string)($c['ticker_code'] ?? ''),
                        'reason_code' => (string)($c['plan']['hard_lock_codes'][0] ?? 'GL_HARD_LOCK'),
                        'alloc_budget' => null,
                        'entry_price_ref' => (int)($c['levels']['entry_trigger_price'] ?? 0),
                    ]);
                    continue;
                }

                // Must be eligible for new entry.
                if (isset($c['plan']) && array_key_exists('is_eligible_new_entry', $c['plan']) && !$c['plan']['is_eligible_new_entry']) {
                    $rc = (string)(($c['plan']['block_codes'][0] ?? null) ?: ($this->policyPrefix($policy) . '_BLOCKED'));
                    $addSkip([
                        'ticker_code' => (string)($c['ticker_code'] ?? ''),
                        'reason_code' => $rc,
                        'alloc_budget' => null,
                        'entry_price_ref' => (int)($c['levels']['entry_trigger_price'] ?? 0),
                    ]);
                    continue;
                }

                // Keep allocation template fields null in Mode A.
                $selectedIdxNoCap[] = $idx;
                $allocsNoCap[] = [
                    'ticker_code' => (string)($c['ticker_code'] ?? ''),
                    'alloc_pct' => null,
                    'alloc_budget' => null,
                    'entry_price_ref' => (int)($c['levels']['entry_trigger_price'] ?? 0),
                    'lots_recommended' => null,
                    'estimated_cost' => null,
                ];
            }

            // Mode A still requires deterministic weight_pct per docs (selection-only, no sizing).
            if (!empty($selectedIdxNoCap)) {
                $w = $this->computeRecommendationWeights($selectedIdxNoCap, $candidates);
                foreach ($w as $i => $pct) {
                    if (isset($allocsNoCap[$i])) {
                        $allocsNoCap[$i]['alloc_pct'] = $pct;
                    }
                }
            }

            return [
                'mode' => 'PLAN',
                'risk_per_trade_pct' => $riskPct,
                'capital_idr' => null,
                'cash_remaining_idr' => null,
                'max_positions_today' => (int)($policyMeta['max_positions_today'] ?? 0),
                'allocations' => $allocsNoCap,
                'skipped' => $skippedNoCap,
            ];
        }

        // NO_TRADE is manual-only and must never create allocations.
        if ($policy === 'NO_TRADE') {
            return [
                'mode' => $hasOpenPositions ? 'CARRY_ONLY' : 'NO_TRADE',
                'risk_per_trade_pct' => $riskPct,
                'capital_idr' => $capitalTotal,
                'cash_remaining_idr' => $capitalTotal,
                'max_positions_today' => 0,
                'allocations' => [],
                'skipped' => [],
            ];
        }

        if (!empty($globalLockCodes)) {
            $mode = $hasOpenPositions ? 'CARRY_ONLY' : 'NO_TRADE';
            return [
                'mode' => $mode,
                'risk_per_trade_pct' => $riskPct,
                'capital_idr' => $capitalTotal,
                'cash_remaining_idr' => $capitalTotal,
                'max_positions_today' => 0,
                'allocations' => [],
                'skipped' => [],
            ];
        }

        // Exposure control: limit NEW positions by total max_positions minus already-open positions.
        $target = min($maxToday, max(0, $maxPos - $openCount));
        $target = max(0, $target);

        if ($target <= 0 || empty($topPickIndices)) {
            return [
                'mode' => $hasOpenPositions ? 'CARRY_ONLY' : 'NO_TRADE',
                'risk_per_trade_pct' => $riskPct,
                'capital_idr' => $capitalTotal,
                'cash_remaining_idr' => $capitalTotal,
                'max_positions_today' => 0,
                'allocations' => [],
                'skipped' => [],
            ];
        }
            
        // Build allocation pool from top-picks in rank order, filtering by PLAN eligibility.
        // IMPORTANT (LOCKED): If a top pick is not feasible for min 1 lot under the provided capital,
        // we must backfill with the next ranked candidate (docs/watchlist/watchlist.md).
        $poolIdx = [];
        $skippedMap = [];

        $addSkip = function (array $row) use (&$skipped, &$skippedMap): void {
            $tk = (string)($row['ticker_code'] ?? '');
            if ($tk === '') return;
            if (isset($skippedMap[$tk])) return;
            $skippedMap[$tk] = true;
            $skipped[] = $row;
        };

        foreach ($topPickIndices as $idx) {
            $c = $candidates[$idx] ?? null;
            if (!$c) continue;

            // Do not allocate NEW entries for tickers already held.
            $tid = (int)($c['ticker_id'] ?? 0);
            if ($tid > 0 && isset($openPositions[$tid])) {
                $addSkip([
                    'ticker_code' => (string)($c['ticker_code'] ?? ''),
                    'reason_code' => 'GL_ALREADY_HELD',
                    'alloc_budget' => null,
                    'entry_price_ref' => (int)($c['levels']['entry_trigger_price'] ?? 0),
                ]);
                continue;
            }

            // Hard locks never get allocations.
            if (!empty($c['plan']['hard_lock_codes'] ?? [])) {
                $addSkip([
                    'ticker_code' => (string)($c['ticker_code'] ?? ''),
                    'reason_code' => (string)($c['plan']['hard_lock_codes'][0] ?? 'GL_HARD_LOCK'),
                    'alloc_budget' => null,
                    'entry_price_ref' => (int)($c['levels']['entry_trigger_price'] ?? 0),
                ]);
                continue;
            }

            // Eligibility blocks (DOW windows, confirm-required markers, etc) must not override PLAN,
            // but must stop allocations.
            if (isset($c['plan']) && array_key_exists('is_eligible_new_entry', $c['plan']) && !$c['plan']['is_eligible_new_entry']) {
                $rc = (string)(($c['plan']['block_codes'][0] ?? null) ?: ($this->policyPrefix($policy) . '_BLOCKED'));
                $addSkip([
                    'ticker_code' => (string)($c['ticker_code'] ?? ''),
                    'reason_code' => $rc,
                    'alloc_budget' => null,
                    'entry_price_ref' => (int)($c['levels']['entry_trigger_price'] ?? 0),
                ]);
                continue;
            }

            // Capital-dependent viability (e.g. WeeklySwing min lots / min edge) blocks allocations only.
            if (!empty($c['plan']['trade_viability']['evaluated']) && ($c['plan']['trade_viability']['is_viable'] === false)) {
                $rc = (string)(($c['plan']['trade_viability']['reason_codes'][0] ?? null) ?: ($this->policyPrefix($policy) . '_NOT_VIABLE'));
                $addSkip([
                    'ticker_code' => (string)($c['ticker_code'] ?? ''),
                    'reason_code' => $rc,
                    'alloc_budget' => null,
                    'entry_price_ref' => (int)($c['levels']['entry_trigger_price'] ?? 0),
                ]);
                continue;
            }

            $poolIdx[] = $idx;
        }

        if (empty($poolIdx)) {
            return [
                'mode' => $hasOpenPositions ? 'CARRY_ONLY' : 'NO_TRADE',
                'risk_per_trade_pct' => $riskPct,
                'capital_idr' => $capitalTotal,
                'cash_remaining_idr' => $capitalTotal,
                'max_positions_today' => 0,
                'allocations' => [],
                'skipped' => $skipped,
            ];
        }

        // Initial selection = first N in pool.
        $selectedIdx = array_slice($poolIdx, 0, $target);
        $nextPoolPos = count($selectedIdx);

        $minLots = (int)($policyMeta['min_lots'] ?? 1);
        $minAlloc = (int)($policyMeta['min_alloc_idr'] ?? 0);

        $allocs = [];
        $lastSignature = null;
        $iter = 0;
        $maxIter = 20; // defensive; pool is small in practice

        while ($iter++ < $maxIter) {
            if (empty($selectedIdx)) break;

            // Prevent infinite loops.
            $sig = implode(',', $selectedIdx);
            if ($sig === $lastSignature) break;
            $lastSignature = $sig;

            // Recompute weights after each backfill (LOCKED: renormalize after drops).
            $weights = $this->computeRecommendationWeights($selectedIdx, $candidates);
            $remaining = $capitalTotal;

            $allocsPass = [];
            $allocatedIdxMap = [];

            foreach ($selectedIdx as $k => $idx) {
                $c = $candidates[$idx];
                $w = $weights[$k] ?? (1.0 / max(1, count($selectedIdx)));

                // Budget is derived from total capital and weight, but allocations must never overspend remaining cash.
                $intendedBudget = (int) floor($capitalTotal * $w * $sizeMult);
                $budget = (int) min($intendedBudget, $remaining);

                $entryRef = (int)($c['levels']['entry_trigger_price'] ?? 0);
                $lotSize = 100;

                // Mini tranche profile (LOCKED, watchlist.md) and conservative price cap for affordability.
                $setupKind = $this->inferSetupKind((string)($c['setup_type'] ?? 'BREAKOUT'));
                $stopRef = (int)($c['levels']['stop_loss_price'] ?? 0);
                $tp1Ref = (int)($c['levels']['tp1_price'] ?? 0);
                $rrEst = $this->computeRrEst($entryRef, $stopRef, $tp1Ref);
                $atrPct = null;
                if (isset($c['derived']['atr_pct']) && is_numeric($c['derived']['atr_pct'])) $atrPct = (float)$c['derived']['atr_pct'];
                $tickPct = null;
                if (isset($c['derived']['tick_pct']) && is_numeric($c['derived']['tick_pct'])) $tickPct = (float)$c['derived']['tick_pct'];
                $hasCaEvent = !empty($c['basis']['ca_event'] ?? null) || !empty($c['basis']['ca_hint'] ?? null);
                $profile = $this->selectMiniTrancheProfile($policy, $rrEst, $atrPct, $tickPct, $hasCaEvent);
                $priceCapRef = $this->computePlanPriceCap($policy, $setupKind, $entryRef);

                if ($budget <= 0 || $remaining <= 0 || $entryRef <= 0) {
                    $code = $this->policyPrefix($policy) . '_INSUFFICIENT_CASH';
                    $addSkip([
                        'ticker_code' => (string)($c['ticker_code'] ?? ''),
                        'reason_code' => $code,
                        'alloc_budget' => $budget,
                        'entry_price_ref' => $entryRef,
                    ]);
                    continue;
                }

                // First-pass lots from budget, then enforce affordability against remaining cash (include fee + slippage).
                $refPrice = ($priceCapRef > 0) ? $priceCapRef : $entryRef;

                $lots = (int) floor($budget / ($refPrice * $lotSize));
                $lots = min($lots, (int) floor($remaining / ($refPrice * $lotSize)));
                $lots = $this->maxAffordableLots($remaining, $refPrice, $lotSize, $lots);

                if ($lots < $minLots || $budget < $minAlloc) {
                    $code = $this->policyPrefix($policy) . '_MIN_TRADE_VIABILITY_FAIL';
                    $addSkip([
                        'ticker_code' => (string)($c['ticker_code'] ?? ''),
                        'reason_code' => $code,
                        'alloc_budget' => $budget,
                        'entry_price_ref' => $entryRef,
                    ]);
                    continue;
                }

                $shares = $lots * $lotSize;
                $estCost = $this->estimateBuyTotalCost($refPrice, $shares);

                // Absolute guard: never overspend remaining cash.
                if ($estCost > $remaining) {
                    $code = $this->policyPrefix($policy) . '_INSUFFICIENT_CASH';
                    $addSkip([
                        'ticker_code' => (string)($c['ticker_code'] ?? ''),
                        'reason_code' => $code,
                        'alloc_budget' => $budget,
                        'entry_price_ref' => $entryRef,
                    ]);
                    continue;
                }

                $remainingAfter = $remaining - $estCost;
                $remaining = $remainingAfter;

                $allocsPass[] = [
                    'ticker_code' => (string)($c['ticker_code'] ?? ''),
                    'alloc_pct' => round($w, 4),
                    'alloc_budget' => $budget,
                    'entry_price_ref' => $entryRef,
                    'lots_recommended' => $lots,
                    // NOTE: estimated_cost is TOTAL cost (buy + fee + slippage) so remaining_cash is consistent.
                    'estimated_cost' => (int)$estCost,
                    'execution_slices' => $this->buildExecutionSlices($policy, $setupKind, $entryRef, $lots, $profile),
                    'remaining_cash' => (int)$remainingAfter,
                ];
                $allocatedIdxMap[$idx] = true;

                if ($remaining <= 0) break;
            }

            // If we already hit target allocations, accept this pass.
            if (count($allocsPass) >= $target) {
                $allocs = $allocsPass;
                break;
            }

            // If we cannot backfill any more, accept best effort and stop.
            if ($nextPoolPos >= count($poolIdx) || $remaining <= 0) {
                $allocs = $allocsPass;
                break;
            }

            // Remove selected tickers that failed to allocate, then backfill with next ranked pool tickers.
            $nextSelected = [];
            foreach ($selectedIdx as $idx) {
                if (isset($allocatedIdxMap[$idx])) {
                    $nextSelected[] = $idx;
                }
            }

            // Backfill until we reach target selection size or pool exhausted.
            while (count($nextSelected) < $target && $nextPoolPos < count($poolIdx)) {
                $nextSelected[] = $poolIdx[$nextPoolPos++];
            }

            // If selection does not change, stop.
            if (implode(',', $nextSelected) === $sig) {
                $allocs = $allocsPass;
                break;
            }

            $selectedIdx = $nextSelected;
        }

        // LEFTOVER distribution (LOCKED, docs/watchlist/watchlist.md):
        // Distribute remaining cash deterministically by ranking order to add +1 lot where feasible.
        if (!empty($allocs)) {
            // Current remaining cash is based on the last allocation's remaining_cash.
            $cashRemainingTmp = $capitalTotal;
            $lastTmp = end($allocs);
            if (is_array($lastTmp) && isset($lastTmp['remaining_cash']) && is_numeric($lastTmp['remaining_cash'])) {
                $cashRemainingTmp = (int)$lastTmp['remaining_cash'];
            }
            reset($allocs);

            $remainingExtra = $cashRemainingTmp;
            if ($remainingExtra > 0) {
                foreach ($allocs as $i => $a) {
                    if (!is_array($a)) continue;
                    $entryRef = (int)($a['entry_price_ref'] ?? 0);
                    if ($entryRef <= 0) continue;

                    // Conservative affordability ref price: use plan_price_cap if present, else entry.
                    $refPrice = $entryRef;
                    $slices = (isset($a['execution_slices']) && is_array($a['execution_slices'])) ? $a['execution_slices'] : [];
                    if (!empty($slices) && is_array($slices[0]) && isset($slices[0]['plan_price_cap']) && is_numeric($slices[0]['plan_price_cap'])) {
                        $cap = (int)$slices[0]['plan_price_cap'];
                        if ($cap > 0) $refPrice = $cap;
                    }

                    $perLotCost = (int)$this->estimateBuyTotalCost($refPrice, 100);
                    if ($perLotCost <= 0) continue;

                    if ($remainingExtra >= $perLotCost) {
                        $lotsOld = (int)($a['lots_recommended'] ?? 0);
                        if ($lotsOld <= 0) continue;

                        $lotsNew = $lotsOld + 1;
                        $allocs[$i]['lots_recommended'] = $lotsNew;
                        $allocs[$i]['estimated_cost'] = (int)((int)($a['estimated_cost'] ?? 0) + $perLotCost);

                        // Rebalance tranche lots using locked rounding formulas based on current number of tranches.
                        if (!empty($slices)) {
                            $nTranches = count($slices);
                            if ($nTranches === 1) {
                                $slices[0]['lots'] = $lotsNew;
                            } elseif ($nTranches === 2) {
                                $t1 = (int)ceil(0.6 * $lotsNew);
                                $t2 = (int)($lotsNew - $t1);
                                $slices[0]['lots'] = $t1;
                                $slices[1]['lots'] = $t2;
                            } elseif ($nTranches === 3) {
                                $t1 = (int)ceil(0.5 * $lotsNew);
                                $t2 = (int)ceil(0.3 * $lotsNew);
                                $t3 = (int)($lotsNew - $t1 - $t2);
                                $slices[0]['lots'] = $t1;
                                $slices[1]['lots'] = $t2;
                                $slices[2]['lots'] = $t3;
                            }
                            $allocs[$i]['execution_slices'] = $slices;
                        }

                        $remainingExtra -= $perLotCost;
                        if ($remainingExtra <= 0) break;
                    }
                }

                // Recompute remaining_cash chain so it stays consistent after leftover adjustments.
                $remainingChain = $capitalTotal;
                foreach ($allocs as $j => $a2) {
                    if (!is_array($a2)) continue;
                    $est2 = (int)($a2['estimated_cost'] ?? 0);
                    if ($est2 < 0) $est2 = 0;
                    if ($est2 > $remainingChain) $est2 = $remainingChain;
                    $remainingChain -= $est2;
                    $allocs[$j]['remaining_cash'] = (int)$remainingChain;
                    if ($remainingChain <= 0) break;
                }
            }
        }
        // Top-level remaining cash for contract mapping (used by mapRecommendations).
        // If no allocations were made, remaining equals total capital.
        $cashRemaining = $capitalTotal;
        if (!empty($allocs)) {
            $last = end($allocs);
            if (is_array($last) && isset($last['remaining_cash']) && is_numeric($last['remaining_cash'])) {
                $cashRemaining = (int)$last['remaining_cash'];
            }
            reset($allocs);
        }

        $nAlloc = count($allocs);
        if ($nAlloc <= 0) {
            $mode = $hasOpenPositions ? 'CARRY_ONLY' : 'NO_TRADE';
            $maxToday = 0;
        } else {
            $mode = ($nAlloc === 1) ? 'BUY_1' : (($nAlloc === 2) ? 'BUY_2_SPLIT' : 'BUY_3_SMALL');
        }

        return [
            'mode' => $mode,
            'risk_per_trade_pct' => $riskPct,
            'capital_idr' => $capitalTotal,
            'cash_remaining_idr' => $cashRemaining,
            // cap for the day (policy meta), not the count we managed to allocate
            'max_positions_today' => $maxToday,
            'allocations_count' => $nAlloc,
            'allocations' => $allocs,
            'skipped' => $skipped,
        ];
    }



    /**
     * Estimate total BUY cost including fee + slippage (both rounded up) for the given shares.
     * This function is used to enforce no-overspend in recommendations.
     */
    private function estimateBuyTotalCost(int $entryPrice, int $shares): int
    {
        $rawCost = $entryPrice * $shares;
        if ($rawCost <= 0) return 0;

        $buyFee = (int) ceil($rawCost * $this->buyFeePct);
        $slip = (int) ceil($rawCost * $this->slippagePct);

        return (int) ($rawCost + $buyFee + $slip);
    }

    /**
     * Find max lots such that estimated BUY cost (incl fee+slippage) does not exceed remaining cash.
     * Uses binary search to avoid slow decrement loops.
     */
    private function maxAffordableLots(int $remaining, int $entryPrice, int $lotSize, int $lots): int
    {
        if ($remaining <= 0 || $entryPrice <= 0 || $lotSize <= 0 || $lots <= 0) return 0;

        $perLotRaw = $entryPrice * $lotSize;
        if ($perLotRaw <= 0) return 0;

        $lots = min($lots, (int) floor($remaining / $perLotRaw));
        if ($lots <= 0) return 0;

        $lo = 0;
        $hi = $lots;

        while ($lo < $hi) {
            $mid = (int) floor(($lo + $hi + 1) / 2);
            $shares = $mid * $lotSize;
            $cost = $this->estimateBuyTotalCost($entryPrice, $shares);
            if ($cost <= $remaining) {
                $lo = $mid;
            } else {
                $hi = $mid - 1;
            }
        }

        return $lo;
    }

    private function policyPrefix(string $policy): string
    {
        if ($policy === 'WEEKLY_SWING') return 'WS';
        if ($policy === 'DIVIDEND_SWING') return 'DS';
        if ($policy === 'INTRADAY_LIGHT') return 'IL';
        if ($policy === 'POSITION_TRADE') return 'PT';
        if ($policy === 'NO_TRADE') return 'NT';
        return 'GL';
    }

    private function policyDocExists(string $policy): bool
    {
        $res = $this->policyDocs->check($policy);
        return (bool) $res->ok;
    }

    /**
     * Compute deterministic recommendation weights from score_total.
     *
     * LOCKED defaults:
     * - W_MIN = 0.10
     * - W_MAX = 0.60
     * - If N == 1 -> 1.00
     * - Else: w_raw = score_total, w_clamped = clamp(w_raw, W_MIN, W_MAX), w = w_clamped / sum(w_clamped)
     *
     * @param int[] $indices  Candidate indices, in rank order.
     * @param array<int,array<string,mixed>> $candidates
     * @return float[] weights in the same order as $indices
     */
    private function computeRecommendationWeights(array $indices, array $candidates): array
    {
        $n = count($indices);
        if ($n <= 0) return [];
        if ($n === 1) return [1.0];

        $W_MIN = 0.10;
        $W_MAX = 0.60;

        $clamped = [];
        $sum = 0.0;
        foreach ($indices as $idx) {
            $s = 0.0;
            if (isset($candidates[$idx]) && array_key_exists('score_total', $candidates[$idx]) && is_numeric($candidates[$idx]['score_total'])) {
                $s = (float) $candidates[$idx]['score_total'];
            }
            // score_total is defined in docs as [0..1], but clamp defensively.
            $w = max($W_MIN, min($W_MAX, $s));
            $clamped[] = $w;
            $sum += $w;
        }

        // Defensive fallback: if something goes wrong, return equal weights.
        if ($sum <= 0.0) {
            $eq = 1.0 / max(1, $n);
            return array_fill(0, $n, $eq);
        }

        $weights = [];
        foreach ($clamped as $w) {
            $weights[] = $w / $sum;
        }
        return $weights;
    }

    /**
     * @return array<int,float>
     */
    private function allocationWeights(int $n): array
    {
        if ($n <= 1) return [1.0];
        if ($n === 2) return [0.6, 0.4];
        return [0.5, 0.3, 0.2];
    }

    /**
     * @return array{open_time:string,close_time:string,breaks:array<int,string>}
     */
    private function sessionForDate(string $date): array
    {
        $row = $this->calRepo->getCalendarRow($date);
        $open = '09:00';
        $close = '16:00';
        $breaks = [];

        if ($row) {
            if (!empty($row['session_open_time'])) {
                $open = substr((string)$row['session_open_time'], 0, 5);
            }
            if (!empty($row['session_close_time'])) {
                $close = substr((string)$row['session_close_time'], 0, 5);
            }
            $bj = $row['breaks_json'] ?? null;
            if ($bj) {
                $arr = null;
                if (is_string($bj)) {
                    $decoded = json_decode($bj, true);
                    if (is_array($decoded)) $arr = $decoded;
                } elseif (is_array($bj)) {
                    $arr = $bj;
                }
                if (is_array($arr)) {
                    $breaks = [];
                    foreach ($arr as $b) {
                        if (is_string($b) && strpos($b, '-') !== false) $breaks[] = $b;
                    }
                }
            }
        }

        return [
            'open_time' => $open,
            'close_time' => $close,
            'breaks' => $breaks,
        ];
    }

    private function computeAsOfTradeDate(\DateTimeImmutable $now, string $cutoffHms): string
    {
        $tz = $now->getTimezone();
        $today = $now->format('Y-m-d');
        // normalize cutoff to HH:MM:SS
        $defaultCutoff = sprintf(
            '%02d:%02d:00',
            (int) $this->clockCfg->eodCutoffHour(),
            (int) $this->clockCfg->eodCutoffMin()
        );
        $cutoffHms = preg_match('/^\d{2}:\d{2}:\d{2}$/', $cutoffHms)
            ? $cutoffHms
            : (preg_match('/^\d{2}:\d{2}$/', $cutoffHms) ? ($cutoffHms . ':00') : $defaultCutoff);
        $cutoff = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $today . ' ' . $cutoffHms, $tz);

        $prev = $this->calRepo->prevTradingDay($today) ?? $today;
        if (!$cutoff) return $prev;

        // before cutoff => previous trading day
        if ($now < $cutoff) return $prev;

        // after cutoff: only move to today if today is a trading day AND EOD is ready/published.
        if (!$this->calRepo->isTradingDay($today)) return $prev;
        try {
            $cov = $this->watchRepo->coverageSnapshot($today);
            if ($this->isEodReady($cov)) return $today;
        } catch (\Throwable $e) {
            // ignore
        }
        return $prev;
    }

    /**
     * Replace token 'open'/'close' in windows with actual HH:MM.
     * @param string[] $windows
     * @return string[]
     */
    private function resolveWindows(array $windows, string $openTime, string $closeTime): array
    {
        $out = [];
        foreach ($windows as $w) {
            if (!is_string($w) || strpos($w, '-') === false) continue;
            [$a, $b] = explode('-', $w, 2);
            $a = trim($a);
            $b = trim($b);
            if ($a === 'open') $a = $openTime;
            if ($b === 'open') $b = $openTime;
            if ($a === 'close') $a = $closeTime;
            if ($b === 'close') $b = $closeTime;
            // normalize to HH:MM
            $a = substr($a, 0, 5);
            $b = substr($b, 0, 5);
            $out[] = $a . '-' . $b;
        }
        return $out;
    }

    private function normalizeConfidence(string $val): string
    {
        $v = strtolower(trim($val));
        if (in_array($v, ['high','h'], true)) return 'High';
        if (in_array($v, ['med','medium','m'], true)) return 'Med';
        if (in_array($v, ['low','l'], true)) return 'Low';
        return 'Med';
    }

    private function normalizeEntryStyle(string $val): string
    {
        $s = trim($val);
        return $s !== '' ? $s : 'Default';
    }

    /**
     * @param array<string,mixed> $sizing
     * @param array<string,mixed> $policyMeta
     * @return array<string,mixed>
     */
    private function normalizeSizing(array $sizing, array $policyMeta): array
    {
        return [
            'lot_size' => (int)($sizing['lot_size'] ?? 100),
            'slices' => (int)($sizing['slices'] ?? 1),
            'slice_pct' => (float)($sizing['slice_pct'] ?? 1.0),
            'lots_recommended' => $sizing['lots_recommended'] ?? null,
            'estimated_cost' => $sizing['estimated_cost'] ?? null,
            'remaining_cash' => $sizing['remaining_cash'] ?? null,
            'risk_pct' => (float)($sizing['risk_pct'] ?? ($policyMeta['risk_per_trade_pct'] ?? 0.0)),
            'profit_tp2_net' => $sizing['profit_tp2_net'] ?? null,
            'rr_tp2_net' => $sizing['rr_tp2_net'] ?? null,
        ];
    }

    /**
     * Normalize levels keys to watchlist contract.
     * @param array<string,mixed> $levels
     * @param string $setupType
     * @return array<string,mixed>
     */
    private function normalizeLevels(array $levels, string $setupType): array
    {
        $tick = (int)($levels['tick_size'] ?? 1);
        $entry = $levels['entry_trigger_price'] ?? null;
        $sl = $levels['stop_loss_price'] ?? null;
        $tp1 = $levels['tp1_price'] ?? ($levels['tp1'] ?? null);
        $tp2 = $levels['tp2_price'] ?? ($levels['tp2'] ?? null);

        $entryType = (string)($levels['entry_type'] ?? '');
        if ($entryType === '' || $entryType === 'LIMIT' || $entryType === 'TRIGGER') {
            if ($setupType === 'Pullback') $entryType = 'PULLBACK_LIMIT';
            elseif ($setupType === 'Reversal') $entryType = 'REVERSAL_CONFIRM';
            else $entryType = 'BREAKOUT_TRIGGER';
        }

        $limitLow = $levels['entry_limit_low'] ?? null;
        $limitHigh = $levels['entry_limit_high'] ?? null;
        if ($entryType === 'PULLBACK_LIMIT' && $entry !== null) {
            $entryInt = (int)$entry;
            if ($limitLow === null) $limitLow = max(0, $entryInt - (2 * $tick));
            if ($limitHigh === null) $limitHigh = $entryInt;
        }

        $be = $levels['be_price'] ?? ($entry !== null ? (int)$entry : null);

        return [
            'entry_type' => $entryType,
            'entry_trigger_price' => $entry !== null ? (int)$entry : null,
            'entry_limit_low' => $limitLow !== null ? (int)$limitLow : null,
            'entry_limit_high' => $limitHigh !== null ? (int)$limitHigh : null,
            'stop_loss_price' => $sl !== null ? (int)$sl : null,
            'tp1_price' => $tp1 !== null ? (int)$tp1 : null,
            'tp2_price' => $tp2 !== null ? (int)$tp2 : null,
            'close_price' => isset($levels['close_price']) && $levels['close_price'] !== null ? (int)$levels['close_price'] : null,
            'be_price' => $be !== null ? (int)$be : null,
        ];
    }

    
    /**
     * Compute confidence (High/Med/Low) based on percentile of score_total across universe.
     * Docs: top 20% High, middle 40% Med, bottom 40% Low.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string> map ticker_id => confidence
     */
    private function computeConfidenceMap(array $rows): array
    {
        $sorted = $rows;
        usort($sorted, function($a, $b) {
            $wa = (float)($a['score_total'] ?? 0);
            $wb = (float)($b['score_total'] ?? 0);
            if ($wa !== $wb) return ($wa < $wb) ? 1 : -1;
            return strcmp((string)($a['ticker_code'] ?? ''), (string)($b['ticker_code'] ?? ''));
        });

        $n = count($sorted);
        $map = [];
        if ($n <= 0) return $map;

        for ($i = 0; $i < $n; $i++) {
            $row = $sorted[$i];
            $tid = (int)($row['ticker_id'] ?? 0);
            if ($tid <= 0) continue;

            $pct = ($n === 1) ? 100.0 : (100.0 * (1.0 - ($i / max(1, ($n - 1)))));
            $conf = 'Low';
            if ($pct >= 80.0) $conf = 'High';
            elseif ($pct >= 40.0) $conf = 'Med';

            // Policy caps (docs): DS_YIELD_LOW caps confidence to Med
            $rc = (array)($row['reason_codes'] ?? []);
            if ($conf === 'High' && in_array('DS_YIELD_LOW', $rc, true)) $conf = 'Med';

            $map[$tid] = $conf;
        }

        return $map;
    }

    private function isEodReady(array $coverage): bool
    {
        $minCanon = $this->cfg->minCanonicalCoveragePct();
        $minInd = $this->cfg->minIndicatorCoveragePct();
        $canon = $coverage['canonical_coverage_pct'] ?? null;
        $ind = $coverage['indicators_coverage_pct'] ?? ($coverage['indicator_coverage_pct'] ?? null);
        $sig = $coverage['signals_coverage_pct'] ?? null;
        if ($canon === null || $ind === null) return false;

        $ok = ((float)$canon >= $minCanon) && ((float)$ind >= $minInd);
        // If ticker_signals_daily exists (coverage provided), treat it as required too.
        if ($sig !== null) {
            $ok = $ok && ((float)$sig >= $minInd);
        }
        return $ok;
    }

    private function dayOfWeek(string $date): string
    {
        $ts = strtotime($date . ' 00:00:00');
        if ($ts === false) return 'UNK';
        $n = (int)date('N', $ts); // 1=Mon..7=Sun
        $map = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
        return $map[$n] ?? 'UNK';
    }

    private function dividendWindow(string $execTradeDate): array
    {
        // LOCKED by docs/watchlist/policy/dividend_swing.md: T+2 .. T+12 trading days
        $from = $this->addTradingDays($execTradeDate, 2);
        $to   = $this->addTradingDays($execTradeDate, 12);
        return ['from' => $from, 'to' => $to];
    }

    private function addTradingDays(string $date, int $n): ?string
    {
        if ($n === 0) return $date;
        $step = ($n > 0) ? 1 : -1;
        $remain = abs($n);
        $cur = $date;
        while ($remain > 0) {
            $cur = $step > 0 ? ($this->calRepo->nextTradingDay($cur) ?: '') : ($this->calRepo->prevTradingDay($cur) ?: '');
            if ($cur === '') return null;
            $remain--;
        }
        return $cur;
    }

    /**
     * Global contract: trading_days_between(a,b) counts trading days d where a < d <= b.
     */
    public function tradingDaysBetween(string $fromDate, string $toDate): int
    {
        return $this->calRepo->countTradingDaysBetweenExclusiveInclusive($fromDate, $toDate);
    }

    public function tradingDaysAhead(string $fromDate, string $toDate): int
    {
        // Backward-compatible alias. Prefer tradingDaysBetween().
        return $this->tradingDaysBetween($fromDate, $toDate);
    }

    /**
     * Trading-day difference where same day => 0.
     * If fromDate > toDate, it swaps (absolute forward diff).
     */
    private function tradingDaysDiff(string $fromDate, string $toDate): int
    {
        if ($fromDate === '' || $toDate === '') return 0;
        if ($fromDate === $toDate) return 0;
        if ($fromDate > $toDate) {
            $tmp = $fromDate;
            $fromDate = $toDate;
            $toDate = $tmp;
        }
        return $this->tradingDaysAhead($fromDate, $toDate);
    }

    /**
     * Policy selection is explicit (docs/watchlist/preopen.md).
     * NO_TRADE is manual-only and has no automatic triggers.
     */
    private function selectPolicy(
        string $requestedPolicy,
        array $divEventsByTicker,
        array $intradayByTicker,
        bool $hasOpenPositions
    ): string {
        $p = strtoupper(trim($requestedPolicy));
        $allowed = ['WEEKLY_SWING','DIVIDEND_SWING','INTRADAY_LIGHT','POSITION_TRADE','NO_TRADE'];
        if (!in_array($p, $allowed, true)) $p = 'WEEKLY_SWING';
        return $p;
    }

    /**
     * Subtract market breaks from resolved entry windows.
     * Input format: ["HH:MM-HH:MM", ...]
     */
    private function subtractBreaks(array $entryWindows, array $breaks): array
    {
        $toMin = function(string $t): int {
            $t = trim($t);
            if (!preg_match('/^(\d{2}):(\d{2})$/', $t, $m)) return 0;
            return ((int)$m[1]) * 60 + (int)$m[2];
        };
        $fmt = function(int $min): string {
            $min = max(0, $min);
            $h = (int) floor($min / 60);
            $m = $min % 60;
            return sprintf('%02d:%02d', $h, $m);
        };

        $segments = [];
        foreach ($entryWindows as $w) {
            $w = (string)$w;
            if (!preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $w, $m)) continue;
            $s = $toMin($m[1]);
            $e = $toMin($m[2]);
            if ($e <= $s) continue;
            $segments[] = [$s, $e];
        }

        $br = [];
        foreach ($breaks as $b) {
            $b = (string)$b;
            if (!preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $b, $m)) continue;
            $s = $toMin($m[1]);
            $e = $toMin($m[2]);
            if ($e <= $s) continue;
            $br[] = [$s, $e];
        }
        usort($br, function($a, $b) { return $a[0] <=> $b[0]; });

        foreach ($br as [$bs, $be]) {
            $next = [];
            foreach ($segments as [$s, $e]) {
                // no overlap
                if ($be <= $s || $bs >= $e) {
                    $next[] = [$s, $e];
                    continue;
                }
                // break covers whole
                if ($bs <= $s && $be >= $e) {
                    continue;
                }
                // overlap left
                if ($bs <= $s && $be < $e) {
                    $ns = $be;
                    if ($e > $ns) $next[] = [$ns, $e];
                    continue;
                }
                // overlap right
                if ($bs > $s && $be >= $e) {
                    $ne = $bs;
                    if ($ne > $s) $next[] = [$s, $ne];
                    continue;
                }
                // inside -> split
                if ($bs > $s && $be < $e) {
                    if ($bs > $s) $next[] = [$s, $bs];
                    if ($e > $be) $next[] = [$be, $e];
                    continue;
                }
            }
            $segments = $next;
        }

        usort($segments, function($a, $b) { return $a[0] <=> $b[0]; });
        $out = [];
        foreach ($segments as [$s, $e]) {
            if ($e - $s < 1) continue;
            $out[] = $fmt($s) . '-' . $fmt($e);
        }
        return array_values(array_unique($out));
    }

    public function tradingDaysBetweenInclusive(string $fromDate, string $toDate): ?int
    {
        $dates = $this->calRepo->tradingDatesBetween($fromDate, $toDate);
        if (empty($dates)) return null;
        // inclusive count
        $n = 0;
        foreach ($dates as $d) {
            if ($d >= $fromDate && $d <= $toDate) $n++;
        }
        return $n;
    }

    private function deriveCandleMetrics(float $open, float $high, float $low, float $close): array
    {
        $range = max(1.0, $high - $low);
        $body = abs($close - $open);
        $upper = $high - max($open, $close);
        $lower = min($open, $close) - $low;

        $bodyPct = $body / $range;
        $upperPct = $upper / $range;
        $lowerPct = $lower / $range;

        $closeNearHigh = (($high - $close) / $range) <= 0.25;

        return [
            'candle_body_pct' => round($bodyPct, 4),
            'upper_wick_pct' => round($upperPct, 4),
            'lower_wick_pct' => round($lowerPct, 4),
            'close_near_high' => $closeNearHigh,
        ];
    }

    private function roundToTick(float $price, int $tick, string $dir): int
    {
        if ($tick <= 0) $tick = 1;
        $x = $price / $tick;
        if ($dir === 'down') return (int)(floor($x) * $tick);
        if ($dir === 'up') return (int)(ceil($x) * $tick);
        return (int)(round($x) * $tick);
    }
}