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
    // =========================================================
    // Policy thresholds (LOCKED by docs/watchlist/policy/*)
    // NOTE: Do not read these from ENV. Docs are the contract.
    // =========================================================

    // WEEKLY_SWING (docs/watchlist/policy/weekly_swing.md)
    private const WS_MIN_DV20_IDR = 5000000000.0; // Rp 5B
    private const WS_MIN_RR       = 1.3;
    private const WS_MIN_ATR_PCT  = 0.02;
    private const WS_MAX_ATR_PCT  = 0.20;
    private const WS_MAX_TICK_PCT = 0.015;
    private const WS_TP2_R_MULT   = 2.0; // docs: TP2 = 2.0 * R

    // DIVIDEND_SWING (docs/watchlist/policy/dividend_swing.md)
    private const DS_MIN_RR                    = 1.2;
    private const DS_MAX_STOP_PCT              = 0.06;
    private const DS_MAX_EXTEND_ATR            = 1.0;
    private const DS_BREAKOUT_MIN_VOL_RATIO    = 1.0;
    private const DS_BREAKOUT_MIN_CLOSE_POS    = 0.70;
    private const DS_PULLBACK_MAX_MA_DIST_ATR  = 0.5;
    private const DS_PULLBACK_MIN_LOWER_WICK   = 0.30;
    private const DS_PULLBACK_MIN_CLOSE_POS    = 0.60;
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

    /**
     * Build watchlist payload.
     *
     * @param array{
     *   eod_date?:string|null,
     *   policy?:string|null,
     *   capital_total?:int|float|string|null,
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

        $allowedPolicies = ['WEEKLY_SWING','DIVIDEND_SWING','INTRADAY_LIGHT','POSITION_TRADE','NO_TRADE'];
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
        if (!$this->policyDocExists($policy)) {
            $globalLockCodes[] = 'GL_POLICY_DOC_MISSING';
            $notes[] = 'Policy doc missing for selected policy: ' . $policy;
            $policy = 'NO_TRADE';
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
        $statusByTicker = $this->statusRepo->statusByTickerAsOf((string)$execTradeDate);

        $rows = [];
        foreach ($candidates as $ci) {
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

// Policy-specific viability checks. IMPORTANT:
// - PLAN grouping remains pure EOD (no hard cap, no "asal isi").
// - Capital-dependent checks MUST NOT disable PLAN; they only affect allocations.
$rr = function(int $entry, int $sl, int $tp1) {
    $risk = max(0, $entry - $sl);
    $reward = max(0, $tp1 - $entry);
    if ($risk <= 0) return null;
    return round($reward / $risk, 3);
};
$netEdgePct = function(int $entry, int $lotSize, ?int $profitNet) {
    if ($profitNet === null) return null;
    $cost = $entry * $lotSize;
    if ($cost <= 0) return null;
    return round($profitNet / $cost, 4);
};

$capitalTotal = $opts['capital_total'] ?? null;

foreach ($rows as $i => $r) {
    $levels = $r['levels'] ?? [];
    $entry = $levels['entry_trigger_price'] ?? null;
    $sl = $levels['stop_loss_price'] ?? null;
    $tp1 = $levels['take_profit_1_price'] ?? null;
    $tick = (float)($levels['tick_size'] ?? 1);

    $sizing = $r['sizing'] ?? [];
    $lotSize = (int)($sizing['lot_size'] ?? 100);

    if ($policy === 'WEEKLY_SWING') {
        // WeeklySwing: evaluate viability when capital_total is provided.
        $rows[$i]['plan']['trade_viability']['evaluated'] = ($capitalTotal !== null);
        if ($capitalTotal === null) {
            $rows[$i]['plan']['trade_viability']['is_viable'] = null;
            $rows[$i]['plan']['trade_viability']['reason_codes'] = ['WS_VIABILITY_NOT_EVALUATED'];
            continue;
        }

        $isViable = true;
        $vReasons = [];

        $lotsRec = $sizing['lots_recommended'] ?? null;
        $minLots = (int)($policyMeta['min_lots'] ?? 1);
        if ($lotsRec !== null && (int)$lotsRec < $minLots) {
            $isViable = false;
            $vReasons[] = 'WS_MIN_LOTS_FAIL';
        }

        $profitNet = $sizing['profit_tp2_net'] ?? null;
        $edge = ($entry !== null) ? $netEdgePct((int)$entry, $lotSize, is_int($profitNet) ? $profitNet : null) : null;
        $minEdge = (float)($policyMeta['min_net_edge_pct'] ?? 0.0);
        if ($edge !== null && $edge < $minEdge) {
            $isViable = false;
            $vReasons[] = 'WS_MIN_NET_EDGE_FAIL';
        }

        $rows[$i]['plan']['trade_viability']['is_viable'] = $isViable;
        $rows[$i]['plan']['trade_viability']['reason_codes'] = $vReasons;

        continue;
    }

    if ($policy === 'DIVIDEND_SWING') {
        if ($entry === null || $sl === null || $tp1 === null) {
            $rows[$i]['plan']['is_eligible_new_entry'] = false;
            $rows[$i]['plan']['block_codes'][] = 'DS_LEVELS_INCOMPLETE';
            $rows[$i]['plan']['block_codes'] = array_values(array_unique($rows[$i]['plan']['block_codes']));
            $rows[$i]['reason_codes'][] = 'DS_LEVELS_INCOMPLETE';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            continue;
        }

        $rval = $rr((int)$entry, (int)$sl, (int)$tp1);

        $profitNet = $sizing['profit_tp2_net'] ?? null;
        $edge = $netEdgePct((int)$entry, $lotSize, is_int($profitNet) ? $profitNet : null);
        $minEdge = (float)($policyMeta['min_net_edge_pct'] ?? 0.0);

        if (($rval !== null && $rval < 1.8) || ($edge !== null && $edge < $minEdge)) {
            $rows[$i]['reason_codes'][] = 'DS_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            // Hard-rule fail: keep ticker for monitoring (Watch Only), but block new entry.
            $rows[$i]['plan']['is_eligible_new_entry'] = false;
            $rows[$i]['plan']['block_codes'][] = 'DS_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['plan']['block_codes'] = array_values(array_unique($rows[$i]['plan']['block_codes']));
        }
        continue;
    }

    if ($policy === 'INTRADAY_LIGHT') {
        if ($entry === null || $sl === null || $tp1 === null) {
            $rows[$i]['plan']['is_eligible_new_entry'] = false;
            $rows[$i]['plan']['block_codes'][] = 'IL_LEVELS_INCOMPLETE';
            $rows[$i]['plan']['block_codes'] = array_values(array_unique($rows[$i]['plan']['block_codes']));
            $rows[$i]['reason_codes'][] = 'IL_LEVELS_INCOMPLETE';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            continue;
        }
        $rval = $rr((int)$entry, (int)$sl, (int)$tp1);
        if ($rval !== null && $rval < 1.6) {
            $rows[$i]['reason_codes'][] = 'IL_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            // Hard-rule fail: keep ticker for monitoring (Watch Only), but block new entry.
            $rows[$i]['plan']['is_eligible_new_entry'] = false;
            $rows[$i]['plan']['block_codes'][] = 'IL_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['plan']['block_codes'] = array_values(array_unique($rows[$i]['plan']['block_codes']));
        }
        continue;
    }

    if ($policy === 'POSITION_TRADE') {
        if ($entry === null || $sl === null || $tp1 === null) {
            $rows[$i]['plan']['is_eligible_new_entry'] = false;
            $rows[$i]['plan']['block_codes'][] = 'PT_LEVELS_INCOMPLETE';
            $rows[$i]['plan']['block_codes'] = array_values(array_unique($rows[$i]['plan']['block_codes']));
            $rows[$i]['reason_codes'][] = 'PT_LEVELS_INCOMPLETE';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            continue;
        }
        $rval = $rr((int)$entry, (int)$sl, (int)$tp1);
        if ($rval !== null && $rval < 2.0) {
            $rows[$i]['reason_codes'][] = 'PT_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            // Hard-rule fail: keep ticker for monitoring (Watch Only), but block new entry.
            $rows[$i]['plan']['is_eligible_new_entry'] = false;
            $rows[$i]['plan']['block_codes'][] = 'PT_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['plan']['block_codes'] = array_values(array_unique($rows[$i]['plan']['block_codes']));
        }
        continue;
    }
}

// NOTE: Do not remove hard-rule fails. Mereka tetap ditampilkan untuk monitoring (Watch Only), sesuai docs/watchlist/watchlist.md.

// Grouping per docs/watchlist/watchlist.md (pure EOD selection; no hard cap)
$topPickMin = 0.70;
$topPickGap = 0.08;
$secondaryMin = 0.55;
$watchMin = 0.35;

// Q = lolos hard rules policy + tradeability enabled (docs/watchlist/watchlist.md)
$qIdx = [];
foreach ($rows as $i => $r0) {
    $tradeable = (bool)($r0['plan']['is_tradeable'] ?? true);
    $elig = (bool)($r0['plan']['is_eligible_new_entry'] ?? true);
    $hardLocks = (array)($r0['plan']['hard_lock_codes'] ?? []);
    if ($tradeable && $elig && empty($hardLocks)) {
        $qIdx[] = $i;
    }
}

$maxScoreQ = 0.0;
foreach ($qIdx as $i) {
    $maxScoreQ = max($maxScoreQ, (float)($rows[$i]['score_total'] ?? 0));
}
$scoreCutTop = !empty($qIdx) ? max($topPickMin, $maxScoreQ - $topPickGap) : $topPickMin;

$topPickIndices = [];

foreach ($rows as $i => $r) {
    $tradeable = (bool)($r['plan']['is_tradeable'] ?? true);
    $hardLocks = (array)($r['plan']['hard_lock_codes'] ?? []);

    // Avoid guards (tradeability disabled) → groups.avoid
    if (!$tradeable || !empty($hardLocks)) {
        $rows[$i]['group'] = 'avoid';
        continue;
    }

    // NO_TRADE policy → groups.no_trade (monitoring only)
    if (strtoupper((string)$policy) === 'NO_TRADE') {
        $rows[$i]['group'] = 'no_trade';
        continue;
    }

    $elig = (bool)($r['plan']['is_eligible_new_entry'] ?? true);
    $st = (float)($r['score_total'] ?? 0);

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

    // Failed hard rules policy: may appear as watch_only only if score meets WATCH_ONLY_MIN_SCORE.
    $rows[$i]['group'] = ($st >= $watchMin) ? 'watch_only' : 'excluded';
}

// Recommendations (allocations) use top-picks as the universe, but do NOT cap top-picks.
$hasOpenPositions = !empty($openPositions);

$recs = $this->buildRecommendations(
    $policy,
    $policyMeta,
    $globalLockCodes,
    $hasOpenPositions,
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
                'counts' => [
                    'total' => count($rows),
                    'top_picks' => count($top),
                    'secondary' => count($secondary),
                    'watch_only' => count($watch),
                    'avoid' => count($avoid),
                    'no_trade' => count($noTrade),
                ],
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

	    $reasonCodes = array_values(array_unique(array_filter((array)($row['reason_codes'] ?? []), 'is_string')));
	    $reasons = $this->buildReasonObjects($reasonCodes);

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
	            $map[$c] = [
	                'code' => $c,
	                'message' => (string)($cr['message'] ?? $c),
	                'severity' => (string)($cr['severity'] ?? 'INFO'),
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
	private function buildReasonObjects(array $codes, string $defaultSeverity = 'INFO'): array
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

	/** @return array<string,mixed> */
	private function buildEodBar(array $row, string $asofEodDate): array
	{
	    	    $open  = (int)round((float)($row['open'] ?? 0));
	    $high  = (int)round((float)($row['high'] ?? 0));
	    $low   = (int)round((float)($row['low'] ?? 0));
	    $close = (int)round((float)($row['close'] ?? 0));
	    $volumeShares = (int)round((float)($row['volume'] ?? 0));

	    $prevClose = (int)round((float)($row['prev_close'] ?? 0));

	    // Best-effort value estimate (IDR). Prefer value_est if provided.
	    $valueEst = null;
	    if (isset($row['value_est']) && is_numeric($row['value_est'])) {
	        $valueEst = (float)$row['value_est'];
	    } else {
	        $valueEst = (float)$close * (float)$volumeShares;
	    }
	    $valueIdr = (int)round($valueEst);

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

	private function computeRrEst(int $entry, int $stop, int $tp1): ?float
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
	                'severity' => 'INFO',
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
	                'severity' => 'INFO',
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
	    $chasePct = (float)($this->confirmGuardsForPolicy($policy)['max_chase_from_close_pct'] ?? 0.02);
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
	    if (isset($recs['capital_total']) && is_numeric($recs['capital_total'])) {
	        $capital = (int)round((float)$recs['capital_total']);
	    }
	
	    $mode = ($capital === null || $capital <= 0) ? 'A_NO_CAPITAL' : 'B_WITH_CAPITAL';
	
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
	                'reasons' => $ref ? (array)($ref['reasons'] ?? []) : [],
                'execution_slices' => (isset($a['execution_slices']) && is_array($a['execution_slices']))
                    ? (array)$a['execution_slices']
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
	        'cash_remaining_idr' => ($canonicalReady && isset($recs['cash_remaining']) && is_numeric($recs['cash_remaining']))
	            ? (int)round((float)$recs['cash_remaining'])
	            : null,
	    ];
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
	            $tr = isset($o['tranche']) && is_numeric($o['tranche']) ? (int)$o['tranche'] : null;
	            if ($tr === null || $tr <= 0) $tr = count($orders) + 1;
	
	            $ps = $sliceByTranche[$tr] ?? [];
	            $planLimit = isset($ps['plan_limit_price']) && is_numeric($ps['plan_limit_price']) ? (int)$ps['plan_limit_price'] : null;
	            $planCap = isset($ps['plan_price_cap']) && is_numeric($ps['plan_price_cap']) ? (int)$ps['plan_price_cap'] : null;
	
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

	            $reasonCode = isset($o['reason_code']) && is_string($o['reason_code']) ? (string)$o['reason_code'] : '';
	            $severity = (strtoupper($action) === 'WAIT') ? 'WARN' : 'INFO';
	
	            $orders[] = [
	                'n' => $tr,
	                'time_window' => (string)($ps['time'] ?? ''),
	                'action' => $action,
	                'lots' => isset($o['lots']) && is_numeric($o['lots']) ? (int)$o['lots'] : null,
	                'plan_limit_price' => $planLimit,
	                'plan_price_cap' => $planCap,
	                'recommended_limit_price' => $recommended,
	                'reasons' => $this->buildReasonObjects($reasonCode !== '' ? [$reasonCode] : [], $severity),
	                'inputs_used' => [
	                    'ask_best' => isset($o['inputs_used']['ask1']) && is_numeric($o['inputs_used']['ask1']) ? (float)$o['inputs_used']['ask1'] : $ask1,
	                    'bid_best' => $bid1,
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
	    $all = $this->cfg->confirmGuards();
	    $g = [];
	    if (isset($all[$policy]) && is_array($all[$policy])) {
	        $g = $all[$policy];
	    } elseif (isset($all[strtoupper($policy)]) && is_array($all[strtoupper($policy)])) {
	        $g = $all[strtoupper($policy)];
	    } elseif (isset($all[strtolower($policy)]) && is_array($all[strtolower($policy)])) {
	        $g = $all[strtolower($policy)];
	    }
	    return [
	        'max_gap_up_pct' => isset($g['max_gap_up_pct']) ? (float)$g['max_gap_up_pct'] : 0.03,
	        'max_chase_from_close_pct' => isset($g['max_chase_from_close_pct']) ? (float)$g['max_chase_from_close_pct'] : 0.02,
	        'max_spread_pct' => isset($g['max_spread_pct']) ? (float)$g['max_spread_pct'] : 0.015,
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

        // Indicator readiness gate: scoring + ATR must exist.
        if (!isset($r['score_total']) || !is_numeric($r['score_total'])) return null;
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
        $tickerFlags = [
            'special_notations' => $st ? (array)($st['special_notations'] ?? []) : [],
            'is_suspended' => $st ? (bool)($st['is_suspended'] ?? false) : false,
            'status_quality' => $st ? (string)($st['status_quality'] ?? 'UNKNOWN') : 'UNKNOWN',
            'status_asof_trade_date' => $st ? (string)($st['status_asof_trade_date'] ?? null) : null,
            'trading_mechanism' => $st ? (string)($st['trading_mechanism'] ?? 'REGULAR') : 'REGULAR',
        ];

        $reasonCodes = [];

        // Hard trade-disable (ticker-level). Global locks (session/no-entry/EOD readiness)
        // are applied later via global timing and must not affect PLAN selection.
        $tradeDisabled = false;
        $hardLockCodes = [];

        // status quality flags
        if ($tickerFlags['status_quality'] === 'STALE') $reasonCodes[] = 'GL_TICKER_STATUS_STALE';
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

		// Score is for ranking/grouping. On hard-rule FAIL, keep the base score (do not force 0).
		if (!$policyHardFail && isset($policyRes['score_total']) && is_numeric($policyRes['score_total'])) {
			$scoreTotal = (float)$policyRes['score_total'];
		} else {
			$scoreTotal = (isset($r['score_total']) && is_numeric($r['score_total'])) ? (float)$r['score_total'] : 0.0;
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
					'severity' => 'ERROR',
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

            'reasons' => $customReasons,

            'ticker_flags' => $tickerFlags,

            'basis' => [
                'trade_date' => (string)($r['trade_date'] ?? ''),
                'open' => (int) round($open),
                'high' => (int) round($high),
                'low' => (int) round($low),
                'close' => (int) round($close),
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
     * Default action windows for managing existing positions.
     * Reuses policy timing windows to keep UI consistent.
     *
     * @param array<string,mixed> $session
     * @return array<int,string>
     */
    private function defaultActionWindowsForPolicy(string $policy, array $session): array
    {
        // Prefer explicit, deterministic windows (avoid the noisiest first minutes).
        if ($policy === 'INTRADAY_LIGHT') {
            return ['09:20-10:15', '13:35-14:15', '15:15-close'];
        }
        if ($policy === 'WEEKLY_SWING') {
            return ['09:20-10:30', '13:35-14:30', '14:30-close'];
        }
        if ($policy === 'DIVIDEND_SWING') {
            return ['09:20-10:30', '13:35-14:30'];
        }
        if ($policy === 'POSITION_TRADE') {
            return ['09:20-10:30', '13:35-14:30'];
        }

        // fallback: full session
        return ['open-close'];
    }

    private function applyPolicyRules(string $policy, array $x, array $reasonCodes): array
    {
        // PLAN-only rules (EOD). CONFIRM rules (preopen/intraday) must not alter PLAN selection.
        $score = 100.0;
        $entryStyle = 'Default';
        $confidence = 'High';
        $drop = false;
        $blockCodes = [];

        // Policy-specific timing adjustments
        $sizeAdj = 1.0;
        $shiftEntryWindows = null;

        $setup = (string)($x['setup_type'] ?? 'Base');
        $liq = strtoupper((string)($x['liq_bucket'] ?? 'U'));
        $atrPct = $x['atr_pct'] ?? null;
        $rsi = $x['rsi14'] ?? null;
        $close = (float)($x['close'] ?? 0);

        $requiredOk = function (array $reqKeys) use ($x): bool {
            foreach ($reqKeys as $k) {
                if (!array_key_exists($k, $x) || $x[$k] === null) return false;
            }
            if (($x['open'] ?? 0) <= 0) return false;
            if (($x['high'] ?? 0) <= 0) return false;
            if (($x['low'] ?? 0) <= 0) return false;
            if (($x['close'] ?? 0) <= 0) return false;
            return true;
        };

        // Soft EOD gap risk (uses EOD open vs prev close if available)
        $gapRiskEod = false;
        $prevClose = $x['prev_close'] ?? null;
        if ($prevClose !== null && (float)$prevClose > 0 && ($x['open'] ?? null) !== null) {
            $gap = abs(((float)$x['open'] - (float)$prevClose) / (float)$prevClose);
            if ($gap >= 0.04) $gapRiskEod = true;
        }

        // Universal sanity
        if ($close <= 0) {
            $drop = true;
            $reasonCodes[] = 'GL_INVALID_PRICE';
            return $this->policyRes($drop, 0.0, 'WATCH_ONLY', 'Low', $reasonCodes, ['GL_INVALID_PRICE']);
        }

        // Policy routers
        if ($policy === 'WEEKLY_SWING') {
            // Policy spec: docs/watchlist/policy/weekly_swing.md
            // Hard rules (must pass to be eligible for new entry), plus deterministic plan levels.

            $minDv20 = self::WS_MIN_DV20_IDR;
            $minAtrPct = self::WS_MIN_ATR_PCT;
            $maxAtrPct = self::WS_MAX_ATR_PCT;
            $maxTickPct = self::WS_MAX_TICK_PCT;
            $minRr = self::WS_MIN_RR;
            $tp2RMult = self::WS_TP2_R_MULT;

            $ma20 = $x['ma20'] ?? null;
            $ma50 = $x['ma50'] ?? null;
            $dv20 = $x['dv20'] ?? null;
            $hh20 = $x['hh20'] ?? null;
            $ll5 = $x['ll5'] ?? null;
            $roc20 = $x['roc20'] ?? null;
            $signalCode = $x['signal_code'] ?? null;
            $volRatio = $x['vol_ratio'] ?? null;

            // tick/atr derived
            $tickRef = (int) $this->tickRule->tickSize(max(1.0, $close));
            $tickPct = ($close > 0) ? ($tickRef / $close) : null;
            $atrPctF = ($atrPct !== null && is_numeric($atrPct)) ? (float)$atrPct : null;

            // resistance_20 = highest_high(20) + tick (tick is ladder at hh20 price)
            $resistance20 = null;
            $setupOverride = null;
            if ($hh20 !== null && is_numeric($hh20)) {
                $hh20f = (float)$hh20;
                $tickHh = (int) $this->tickRule->tickSize(max(1.0, $hh20f));
                $resistance20 = $hh20f + $tickHh;
                // setup_type: BREAKOUT if close >= resistance_20 - tick => close >= hh20
                $setupOverride = ($close >= ($resistance20 - $tickHh)) ? 'Breakout' : 'Pullback';
                $entryStyle = ($setupOverride === 'Breakout') ? 'Breakout' : 'Pullback';
            } else {
                $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
                $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
            }

            // Trend/structure gate: (close>=ma20 && ma20>=ma50) OR (close>=resistance20 && ma20>=ma50)
            if ($ma20 === null || $ma50 === null || !is_numeric($ma20) || !is_numeric($ma50) || $resistance20 === null) {
                $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
                $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
            } else {
                $ma20f = (float)$ma20;
                $ma50f = (float)$ma50;
                $condA = ($close >= $ma20f) && ($ma20f >= $ma50f);
                $condB = ($close >= $resistance20) && ($ma20f >= $ma50f);
                if (!$condA && !$condB) {
                    $reasonCodes[] = 'WS_TREND_GATE_FAIL';
                    $blockCodes[] = 'WS_TREND_GATE_FAIL';
                }
            }

            // Liquidity/volatility/tick gates
            if ($dv20 === null || !is_numeric($dv20)) {
                $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
                $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
            } elseif ((float)$dv20 < $minDv20) {
                $reasonCodes[] = 'WS_MIN_DV20_IDR';
                $blockCodes[] = 'WS_MIN_DV20_IDR';
            }

            if ($atrPctF === null) {
                $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
                $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
            } else {
                if ($atrPctF < $minAtrPct) { $reasonCodes[] = 'WS_MIN_ATR_PCT'; $blockCodes[] = 'WS_MIN_ATR_PCT'; }
                if ($atrPctF > $maxAtrPct) { $reasonCodes[] = 'WS_MAX_ATR_PCT'; $blockCodes[] = 'WS_MAX_ATR_PCT'; }
            }

            if ($tickPct === null) {
                $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
                $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
            } elseif ((float)$tickPct > $maxTickPct) {
                $reasonCodes[] = 'WS_MAX_TICK_PCT';
                $blockCodes[] = 'WS_MAX_TICK_PCT';
            }

            // Hard-rule fails => DROP immediately (gugur dari kandidat policy ini).
            if (!empty($blockCodes)) {
                $drop = true;
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, $blockCodes);
            }

            // Build plan levels (deterministic, EOD-only)
            $levels = $this->buildWeeklySwingLevels(
                (string)($setupOverride ?? $setup),
                [
                    'close' => $close,
                    'low' => (float)($x['low'] ?? 0),
                    'hh20' => $hh20 !== null && is_numeric($hh20) ? (float)$hh20 : null,
                    'll5' => $ll5 !== null && is_numeric($ll5) ? (float)$ll5 : null,
                ],
                $minRr,
                $tp2RMult
            );

            // Binding checks: STOP/R invalid OR RR(tp1) too low => DROP
            $entry = $levels['entry_trigger_price'] ?? null;
            $sl = $levels['stop_loss_price'] ?? null;
            $tp1 = $levels['tp1_price'] ?? null;
            $tick = $levels['tick_size'] ?? null;

            if ($entry === null || $sl === null || $tp1 === null || $tick === null) {
                // Missing required level inputs makes the plan non-actionable.
                $drop = true;
                $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
                $blockCodes[] = 'GL_POLICY_INPUT_MISSING';
            } else {
                $R = (float)$entry - (float)$sl;
                $tickF = max(1.0, (float)$tick);
                if ($R <= 0.0) {
                    $drop = true;
                    $reasonCodes[] = 'WS_R_INVALID_NONPOSITIVE';
                    $blockCodes[] = 'WS_R_INVALID_NONPOSITIVE';
                } elseif ($R < $tickF) {
                    $drop = true;
                    $reasonCodes[] = 'WS_R_INVALID_LT_TICK';
                    $blockCodes[] = 'WS_R_INVALID_LT_TICK';
                } else {
                    $rrEst = ((float)$tp1 - (float)$entry) / $R;
                    if ($rrEst + 1e-9 < $minRr) {
                        $drop = true;
                        $reasonCodes[] = 'WS_RR_TOO_LOW';
                        $blockCodes[] = 'WS_RR_TOO_LOW';
                    }
                }
            }

            // Score only if eligible and not dropped.
            $scoreTotal = 0.0;
            if (!$drop && empty($blockCodes)) {
                $sPattern = $this->wsPatternScoreFromSignal($signalCode);
                $closeVsMa20 = ($ma20 !== null && is_numeric($ma20) && (float)$ma20 > 0) ? (($close / (float)$ma20) - 1.0) : null;
                $sTrend = $closeVsMa20 !== null ? $this->norm01($closeVsMa20, -0.02, 0.05) : 0.0;
                $sMomentum = ($roc20 !== null && is_numeric($roc20)) ? $this->norm01((float)$roc20, -0.03, 0.12) : 0.0;
                $sVolume = ($volRatio !== null && is_numeric($volRatio)) ? $this->norm01((float)$volRatio, 1.0, 3.0) : 0.0;

                $stopPct = ($entry !== null && (float)$entry > 0 && $sl !== null && (float)$sl < (float)$entry)
                    ? (((float)$entry - (float)$sl) / (float)$entry)
                    : null;
                $invStop = $stopPct !== null ? (1.0 - $this->norm01($stopPct, 0.01, 0.08)) : 0.0;
                $invAtr = $atrPctF !== null ? (1.0 - $this->norm01($atrPctF, 0.01, 0.12)) : 0.0;
                $sRisk = $this->clamp01(0.5 * $invStop + 0.5 * $invAtr);

                $scoreTotal = $this->clamp01(
                    0.30 * $sPattern +
                    0.25 * $sTrend +
                    0.20 * $sMomentum +
                    0.15 * $sVolume +
                    0.10 * $sRisk
                );
            }

            $res = $this->policyRes($drop, $scoreTotal * 100.0, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            $res['score_total'] = $scoreTotal;
            if ($setupOverride !== null) $res['setup_type'] = $setupOverride;
            $res['levels'] = $levels;
            $res['derived'] = [
                'dv20_idr' => ($dv20 !== null && is_numeric($dv20)) ? (int)round((float)$dv20) : null,
                'atr_pct' => $atrPctF !== null ? round($atrPctF, 4) : null,
                'tick_pct' => $tickPct !== null ? round((float)$tickPct, 6) : null,
                'roc20' => ($roc20 !== null && is_numeric($roc20)) ? round((float)$roc20, 4) : null,
                'rvol20' => ($volRatio !== null && is_numeric($volRatio)) ? round((float)$volRatio, 4) : null,
            ];
            return $res;
        }

        if ($policy === 'DIVIDEND_SWING') {
            // LOCKED by docs/watchlist/policy/dividend_swing.md

            // --- Event gate ---
            $div = $x['div_event'] ?? null;
            $exDate = is_array($div) ? (string)($div['ex_date'] ?? '') : '';
            $execDate = (string)($x['exec_trade_date'] ?? '');

            if ($exDate === '' || $execDate === '') {
                $drop = true;
                $reasonCodes[] = 'DS_EVENT_MISSING';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_EVENT_MISSING']);
            }

            $daysToEx = $this->tradingDaysAhead($execDate, $exDate);
            if ($daysToEx === null) {
                $drop = true;
                $reasonCodes[] = 'DS_EVENT_MISSING';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_EVENT_MISSING']);
            }

            $minDays = 2;
            $maxDays = 12;
            if ($daysToEx < $minDays || $daysToEx > $maxDays) {
                $drop = true;
                $reasonCodes[] = 'DS_OUTSIDE_EVENT_WINDOW';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_OUTSIDE_EVENT_WINDOW']);
            }

            // --- Required inputs ---
            if (!$requiredOk(['ma20','ma50','atr14','hh20','hh50','ll5'])) {
                $drop = true;
                $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['GL_POLICY_INPUT_MISSING']);
            }

            $ma20 = (float)$x['ma20'];
            $ma50 = (float)$x['ma50'];
            $atr14 = (float)$x['atr14'];
            $hh20 = (float)$x['hh20'];
            $hh50 = (float)$x['hh50'];
            $ll5 = (float)$x['ll5'];

            // --- Trend sanity ---
            $trendOk = ($close >= $ma20) || ($ma20 >= $ma50);
            if (!$trendOk) {
                $drop = true;
                $reasonCodes[] = 'DS_TREND_WEAK';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_TREND_WEAK']);
            }

            // Not-extended gate
            $maxExtendAtr = self::DS_MAX_EXTEND_ATR;
            if ($atr14 > 0 && (($close - $ma20) > ($maxExtendAtr * $atr14))) {
                $drop = true;
                $reasonCodes[] = 'DS_PRICE_EXTENDED';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_PRICE_EXTENDED']);
            }

            // --- Setup checks (need at least one) ---
            $range = max(0.0, $high - $low);
            $closePos = ($range > 0) ? (($close - $low) / $range) : 0.0;
            $c = is_array($x['candle'] ?? null) ? (array)$x['candle'] : [];
            $lowerWick = (float)($c['lower_wick_pct'] ?? 0.0);

            $breakoutMinVol = self::DS_BREAKOUT_MIN_VOL_RATIO;
            $breakoutOk = ($close > $hh20)
                && ($closePos >= self::DS_BREAKOUT_MIN_CLOSE_POS)
                && ($volRatio !== null && is_numeric($volRatio) && (float)$volRatio >= $breakoutMinVol);

            $pbMaxMaDistAtr = self::DS_PULLBACK_MAX_MA_DIST_ATR;
            $pbMinLowerWick = self::DS_PULLBACK_MIN_LOWER_WICK;
            $pbMinClosePos = self::DS_PULLBACK_MIN_CLOSE_POS;
            $pullbackOk = $trendOk
                && ($atr14 > 0)
                && (abs($close - $ma20) <= ($pbMaxMaDistAtr * $atr14))
                && ($close > $open)
                && ($lowerWick >= $pbMinLowerWick)
                && ($closePos >= $pbMinClosePos);

            if (!$pullbackOk && !$breakoutOk) {
                $drop = true;
                $reasonCodes[] = 'DS_NO_SETUP';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_NO_SETUP']);
            }

            // --- Plan levels (locked formulas) ---
            $tickHh = (float)$this->tickRule->tickSize(max(1.0, $hh20));
            $res20 = $hh20 + $tickHh; // resistance_20 = hh20 + tick
            $setupOverride = ($close >= $res20) ? 'Breakout' : 'Pullback';

            $minRr = self::DS_MIN_RR;
            $maxStopPct = self::DS_MAX_STOP_PCT;
            $levels = $this->buildDividendSwingLevels($setupOverride, [
                'close' => $close,
                'low' => $low,
                'hh20' => $hh20,
                'hh50' => $hh50,
                'll5' => $ll5,
            ], $minRr);

            $entry = (int)($levels['entry_trigger_price'] ?? 0);
            $sl = (int)($levels['stop_loss_price'] ?? 0);
            $tp1 = (int)($levels['tp1_price'] ?? 0);
            $tick = (int)($levels['tick_size'] ?? 1);

            if ($entry <= 0 || $sl <= 0 || $tp1 <= 0 || $tick <= 0) {
                $drop = true;
                $reasonCodes[] = 'GL_POLICY_INPUT_MISSING';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['GL_POLICY_INPUT_MISSING']);
            }

            $R = $entry - $sl;
            if ($R <= 0) {
                $drop = true;
                $reasonCodes[] = 'DS_R_INVALID_NONPOSITIVE';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_R_INVALID_NONPOSITIVE']);
            }
            if ($R < $tick) {
                $drop = true;
                $reasonCodes[] = 'DS_R_INVALID_LT_TICK';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_R_INVALID_LT_TICK']);
            }
            if ($tp1 <= $entry) {
                $drop = true;
                $reasonCodes[] = 'DS_TP1_NOT_ABOVE_ENTRY';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_TP1_NOT_ABOVE_ENTRY']);
            }

            $rrEst = ($tp1 - $entry) / (float)$R;
            if ($rrEst < $minRr) {
                $drop = true;
                $reasonCodes[] = 'DS_RR_TOO_LOW';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_RR_TOO_LOW']);
            }

            $stopDistPct = $entry > 0 ? ((float)$R / (float)$entry) : 1.0;
            if ($stopDistPct > $maxStopPct) {
                $drop = true;
                $reasonCodes[] = 'DS_STOP_TOO_WIDE';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['DS_STOP_TOO_WIDE']);
            }

            // --- Risk flags (avoid) ---
            $atrPctF = ($atr14 > 0 && $close > 0) ? ($atr14 / $close) : null;
            if ($atrPctF !== null && $atrPctF >= 0.12) {
                $reasonCodes[] = 'DS_VOL_CHAOS';
                $blockCodes[] = 'DS_VOL_CHAOS';
            }

            if ($daysToEx <= 2
                && $volRatio !== null && is_numeric($volRatio) && (float)$volRatio >= 1.8
                && $rsi !== null && is_numeric($rsi) && (float)$rsi >= 75.0) {
                $reasonCodes[] = 'DS_EVENT_RUNUP_RISK';
                $blockCodes[] = 'DS_EVENT_RUNUP_RISK';
            }

            $nearResPct = ($res20 > 0 && $entry > 0) ? (($res20 - $entry) / $entry) : null;
            if ($nearResPct !== null && $nearResPct <= 0.02) {
                $reasonCodes[] = 'DS_NEAR_RESISTANCE';
                $blockCodes[] = 'DS_NEAR_RESISTANCE';
            }

            // --- Scoring (0..1) ---
            $ideal = (int)floor(($minDays + $maxDays) / 2);
            $sEvent = 0.0;
            if ($daysToEx <= $ideal) {
                $sEvent = 1.0 - (abs($daysToEx - $ideal) / max(1.0, (float)($ideal - $minDays)));
            } else {
                $sEvent = 1.0 - (abs($daysToEx - $ideal) / max(1.0, (float)($maxDays - $ideal)));
            }
            $sEvent = $this->clamp01((float)$sEvent);

            $sLiq = 0.5;
            if ($dv20 !== null && is_numeric($dv20)) {
                $sLiq = $this->norm01((float)$dv20, 1e9, 2e10);
            }

            $closeVsMa20 = ($ma20 > 0) ? (($close / $ma20) - 1.0) : 0.0;
            $sTrend = $this->norm01((float)$closeVsMa20, -0.03, 0.04);

            $sVolRisk = 0.5;
            if ($atrPctF !== null) {
                $sVolRisk = 1.0 - $this->norm01((float)$atrPctF, 0.01, 0.10);
            }

            $gapPct = null;
            if ($prevClose !== null && is_numeric($prevClose) && (float)$prevClose > 0) {
                $gapPct = abs(((float)$open - (float)$prevClose) / (float)$prevClose);
            }
            $sGapRisk = 0.5;
            if ($gapPct !== null) {
                $sGapRisk = 1.0 - $this->norm01((float)$gapPct, 0.0, 0.05);
            } elseif ($atrPctF !== null) {
                $sGapRisk = 1.0 - $this->norm01((float)$atrPctF, 0.01, 0.10);
            }

            $scoreTotal = $this->clamp01(
                0.30 * $sEvent +
                0.25 * $sLiq +
                0.20 * $sTrend +
                0.15 * $sVolRisk +
                0.10 * $sGapRisk
            );

            // Yield sanity (optional): below 0.8% caps confidence (handled later in confidence map)
            $yieldEst = null;
            if (is_array($div) && isset($div['dividend_yield_est']) && $div['dividend_yield_est'] !== null && is_numeric($div['dividend_yield_est'])) {
                $yieldEst = (float)$div['dividend_yield_est'];
                if ($yieldEst < 0.008) {
                    $reasonCodes[] = 'DS_YIELD_LOW';
                }
            }

            $entryStyle = ($setupOverride === 'Breakout') ? 'Breakout' : 'Pullback';
            $confidence = ($scoreTotal >= 0.75) ? 'High' : (($scoreTotal >= 0.55) ? 'Med' : 'Low');

            $res = $this->policyRes($drop, $scoreTotal * 100.0, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            $res['score_total'] = $scoreTotal;
            $res['setup_type'] = $setupOverride;
            $res['levels'] = $levels;
            $res['derived'] = [
                'days_to_ex' => $daysToEx,
                'ex_date' => $exDate,
                'dv20_idr' => ($dv20 !== null && is_numeric($dv20)) ? (int)round((float)$dv20) : null,
                'atr_pct' => $atrPctF !== null ? round((float)$atrPctF, 4) : null,
                'gap_pct_open' => $gapPct !== null ? round((float)$gapPct, 4) : null,
                'yield_est' => $yieldEst !== null ? round((float)$yieldEst, 4) : null,
            ];
            return $res;
        }

        if ($policy === 'INTRADAY_LIGHT') {
            // Spec: docs/watchlist/policy/intraday_light.md (PLAN from EOD, execute after CONFIRM)
            $reasonCodes[] = 'IL_CONFIRM_REQUIRED';

            // Required inputs
            if (!$requiredOk(['dv20','atr_pct','vol_ratio','hh10','ll3','roc5'])) {
                $drop = true;
                $reasonCodes[] = 'IL_DATA_INCOMPLETE';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_DATA_INCOMPLETE']);
            }

            $dv20 = (float)$x['dv20'];
            if ($dv20 < 3000000000.0) {
                $drop = true;
                $reasonCodes[] = 'IL_LIQ_TOO_LOW';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_LIQ_TOO_LOW']);
            }

            $atrPctF = (float)$atrPct;
            if ($atrPctF < 0.02) {
                $drop = true;
                $reasonCodes[] = 'IL_VOL_BAND_FAIL';
                $reasonCodes[] = 'IL_VOL_BAND_LOW';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_VOL_BAND_FAIL']);
            }
            if ($atrPctF > 0.15) {
                $drop = true;
                $reasonCodes[] = 'IL_VOL_BAND_FAIL';
                $reasonCodes[] = 'IL_VOL_BAND_HIGH';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_VOL_BAND_FAIL']);
            }

            // Candle-derived
            $c = $x['candle'] ?? null;
            $upperWickPct = is_array($c) ? (float)($c['upper_wick_pct'] ?? 0.0) : 0.0;
            $range = max(1.0, (float)$high - (float)$low);
            $closePos = ((float)$close - (float)$low) / $range;
            $closePos = $this->clamp01($closePos);

            // Momentum gate (Breakout_10 OR Strong close continuation)
            $hh10 = (float)$x['hh10'];
            $roc5 = (float)$x['roc5'];
            $volRatioF = (float)$x['vol_ratio'];

            $breakoutOk = ((float)$close > $hh10) && ($closePos >= 0.75) && ($volRatioF >= 1.0);
            $contOk = ($roc5 >= 0.02) && ($closePos >= 0.70) && ($volRatioF >= 1.5);

            if (!$breakoutOk && !$contOk) {
                $drop = true;
                $reasonCodes[] = 'IL_NO_SETUP';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_NO_SETUP']);
            }

            $setupOverride = $breakoutOk ? 'Breakout' : 'Continuation';
            $entryStyle = $breakoutOk ? 'Breakout' : 'Continuation';

            // Build PLAN levels (EOD-based)
            $levels = $this->buildIntradayLightLevels($x, $setupOverride);
            $entry = $levels['entry_trigger_price'] ?? null;
            $stop = $levels['stop_loss_price'] ?? null;
            $tp1 = $levels['tp1_price'] ?? null;
            $tick = $levels['tick_size'] ?? null;

            if (!is_numeric($entry) || !is_numeric($stop) || !is_numeric($tp1) || !is_numeric($tick) || (float)$entry <= 0 || (float)$tick <= 0) {
                $drop = true;
                $reasonCodes[] = 'IL_DATA_INCOMPLETE';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_DATA_INCOMPLETE']);
            }

            $R = (float)$entry - (float)$stop;
            if ($R <= 0) {
                $drop = true;
                $reasonCodes[] = 'IL_R_INVALID_R_LE_0';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_R_INVALID_R_LE_0']);
            }
            if ($R < (float)$tick) {
                $drop = true;
                $reasonCodes[] = 'IL_R_INVALID_R_LT_TICK';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_R_INVALID_R_LT_TICK']);
            }

            $stopPct = $R / (float)$entry;
            if ($stopPct > 0.02) {
                $drop = true;
                $reasonCodes[] = 'IL_STOP_TOO_WIDE';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_STOP_TOO_WIDE']);
            }

            $rrEst = $this->computeRrEst((float)$entry, (float)$stop, (float)$tp1);
            if ($rrEst < 1.0) {
                $drop = true;
                $reasonCodes[] = 'IL_RR_TOO_LOW';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['IL_RR_TOO_LOW']);
            }

            // Scoring (0..1)
            $rsiF = ($rsi !== null) ? (float)$rsi : null;
            $sRoc = $this->norm01($roc5, 0.0, 0.08);
            $sRsi = ($rsiF !== null) ? $this->norm01($rsiF, 45.0, 70.0) : 0.5;
            if ($rsiF !== null && $rsiF >= 75.0) {
                $reasonCodes[] = 'IL_RSI_OVERHEAT';
                $sRsi = max(0.0, $sRsi - 0.15);
            }
            $sMomentum = $this->clamp01(0.6 * $sRoc + 0.4 * $sRsi);

            $sVol = $this->norm01($volRatioF, 1.0, 3.0);

            $sClosePos = $this->norm01($closePos, 0.50, 0.90);
            $sWick = 1.0 - $this->norm01($upperWickPct, 0.20, 0.55);
            $sCandle = $this->clamp01(0.65 * $sClosePos + 0.35 * $sWick);

            $sRiskAtr = 1.0 - $this->norm01($atrPctF, 0.02, 0.15);
            $sRiskWick = 1.0 - $this->norm01($upperWickPct, 0.35, 0.65);
            $sRisk = $this->clamp01(0.7 * $sRiskAtr + 0.3 * $sRiskWick);

            $scoreTotal = $this->clamp01(
                0.35 * $sMomentum +
                0.25 * $sVol +
                0.20 * $sCandle +
                0.20 * $sRisk
            );

            // Soft labels / risks
            if ($volRatioF >= 1.5) $reasonCodes[] = 'IL_VOL_STRONG';
            elseif ($volRatioF >= 1.0) $reasonCodes[] = 'IL_VOL_CONFIRM';

            if ($upperWickPct <= 0.35 && $closePos >= 0.75) $reasonCodes[] = 'IL_CLEAN_CANDLE';
            if ($rsiF !== null && $rsiF >= 78.0 && $closePos >= 0.85) $reasonCodes[] = 'IL_BLOWOFF_RISK';
            if ($atrPctF >= 0.12 && $upperWickPct >= 0.55) $reasonCodes[] = 'IL_CHAOS_RISK';

            $confidence = ($scoreTotal >= 0.75) ? 'High' : (($scoreTotal >= 0.55) ? 'Med' : 'Low');

            $res = $this->policyRes($drop, $scoreTotal * 100.0, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            $res['score_total'] = $scoreTotal;
            $res['setup_type'] = $setupOverride;
            $res['levels'] = $levels;
            $res['derived'] = [
                'dv20_idr' => (int)round($dv20),
                'atr_pct' => round($atrPctF, 4),
                'roc5' => round($roc5, 4),
                'close_pos' => round($closePos, 4),
                'upper_wick_pct' => round($upperWickPct, 4),
                'rr_est' => round($rrEst, 4),
                'stop_pct' => round($stopPct, 4),
            ];
            return $res;
        }


        if ($policy === 'POSITION_TRADE') {
            if (!in_array($liq, ['A','B'], true)) {
                $drop = true;
                $reasonCodes[] = 'PT_LIQ_TOO_LOW';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['PT_LIQ_TOO_LOW']);
            }

            // Trend gate (PT uses ma200)
            if (!$requiredOk(['ma200','ma50'])) {
                $drop = true;
                $reasonCodes[] = 'PT_MISSING_TREND_INPUT';
                return $this->policyRes($drop, 0.0, $entryStyle, 'Low', $reasonCodes, ['PT_MISSING_TREND_INPUT']);
            }

            $ma200 = (float)$x['ma200'];
            $ma50 = (float)$x['ma50'];
            $trendOk = ($close > $ma200 && $ma50 > $ma200);
            if (!$trendOk && $setup === 'Breakout') {
                $reasonCodes[] = 'PT_BREAKOUT_BLOCK_TREND_NOT_OK';
                $blockCodes[] = 'PT_BREAKOUT_BLOCK_TREND_NOT_OK';
            }

            if ($rsi !== null && (float)$rsi >= 78.0) {
                $score -= 6.0;
                $reasonCodes[] = 'PT_RSI_OVERHEAT';
            }

            $c = $x['candle'] ?? null;
            if (is_array($c)) {
                $upper = (float)($c['upper_wick_pct'] ?? 0);
                $closeNearHigh = (bool)($c['close_near_high'] ?? false);
                if ($upper >= 0.55 && !$closeNearHigh) {
                    $score -= 6.0;
                    $reasonCodes[] = 'PT_WICK_DISTRIBUTION';
                }
            }

            if ($setup === 'Reversal') {
                $reasonCodes[] = 'PT_REVERSAL_DISABLED_DEFAULT';
                $blockCodes[] = 'PT_REVERSAL_DISABLED_DEFAULT';
            } elseif (!in_array($setup, ['Breakout','Pullback','Continuation'], true)) {
                $blockCodes[] = 'PT_SETUP_NOT_ALLOWED';
            }

            $reasonCodes[] = 'PT_CONFIRM_OPTIONAL';
            return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
        }

        // NO_TRADE: keep candidates as watch_only
        $blockCodes[] = 'GL_POLICY_INACTIVE';
        return $this->policyRes(false, 0.0, 'WATCH_ONLY', 'Low', $reasonCodes, $blockCodes);
    }
    private function policyRes(bool $drop, float $score, string $entryStyle, string $confidence, array $reasonCodes, array $blockCodes, float $sizeMultiplierAdj = 1.0, ?string $shiftEntryWindows = null): array
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

    private function evaluateEligibilityForNewEntry(string $policy, array $blockCodes, bool $tradeDisabled): bool
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
    private function buildWeeklySwingLevels(string $setupType, array $ctx, float $minRr, float $tp2RMult): array
    {
        $close = (float)($ctx['close'] ?? 0);
        $low = (float)($ctx['low'] ?? 0);
        $hh20 = $ctx['hh20'] ?? null;
        $ll5 = $ctx['ll5'] ?? null;

        if ($close <= 0 || $low <= 0) {
            return [
                'entry_trigger_price' => null,
                'stop_loss_price' => null,
                'tp1_price' => null,
                'tp2_price' => null,
                'tick_size' => null,
            ];
        }

        // resistance_20 = hh20 + tick(hh20)
        $resistance20 = null;
        if ($hh20 !== null && is_numeric($hh20) && (float)$hh20 > 0) {
            $hh20f = (float)$hh20;
            $tickHh = (float)$this->tickRule->tickSize(max(1.0, $hh20f));
            $resistance20 = $hh20f + $tickHh;
        }

        $isBreakout = (strcasecmp($setupType, 'Breakout') === 0);

        // Entry
        if ($isBreakout && $resistance20 !== null) {
            $entry = (float)$this->tickRule->roundUp($resistance20);
        } else {
            $entry = (float)$this->tickRule->roundUp($close);
        }

        // Stop: round_down(min(low, ll5) - tick)
        $minLow = $low;
        if ($ll5 !== null && is_numeric($ll5) && (float)$ll5 > 0) {
            $minLow = min($minLow, (float)$ll5);
        }
        $tickMin = (float)$this->tickRule->tickSize(max(1.0, $minLow));
        $stopRaw = $minLow - $tickMin;
        $sl = (float)$this->tickRule->roundDown($stopRaw);

        // Targets (based on R)
        $tp1 = null;
        $tp2 = null;
        $R = $entry - $sl;
        if ($entry > 0 && $sl > 0 && $R > 0) {
            $tp1 = (float)$this->tickRule->roundDown($entry + ($minRr * $R));
            $tp2 = (float)$this->tickRule->roundDown($entry + ($tp2RMult * $R));
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

    /**
     * Build Dividend Swing levels (LOCKED by docs/watchlist/policy/dividend_swing.md).
     *
     * - resistance_20 = highest_high(20) + tick
     * - resistance_50 = highest_high(50) + tick
     * - entry:
     *   - Pullback: round_up(close)
     *   - Breakout: round_up(resistance_20)
     * - stop: round_down(min(low, support_5) - tick)
     * - tp1_raw = entry + minRR * (entry - stop)
     * - cap:
     *   - Pullback: resistance_20
     *   - Breakout: resistance_50
     * - tp1 = round_down(min(tp1_raw, cap))
     */
    private function buildDividendSwingLevels(string $setupType, array $ctx, float $minRr): array
    {
        $close = (float)($ctx['close'] ?? 0);
        $low = (float)($ctx['low'] ?? 0);
        $hh20 = $ctx['hh20'] ?? null;
        $hh50 = $ctx['hh50'] ?? null;
        $ll5 = $ctx['ll5'] ?? null;

        if ($close <= 0 || $low <= 0) {
            return [
                'entry_trigger_price' => null,
                'stop_loss_price' => null,
                'tp1_price' => null,
                'tp2_price' => null,
                'tick_size' => null,
            ];
        }

        $res20 = null;
        if ($hh20 !== null && is_numeric($hh20) && (float)$hh20 > 0) {
            $hh20f = (float)$hh20;
            $tickHh = (float)$this->tickRule->tickSize(max(1.0, $hh20f));
            $res20 = $hh20f + $tickHh;
        }

        $res50 = null;
        if ($hh50 !== null && is_numeric($hh50) && (float)$hh50 > 0) {
            $hh50f = (float)$hh50;
            $tickHh = (float)$this->tickRule->tickSize(max(1.0, $hh50f));
            $res50 = $hh50f + $tickHh;
        }

        $isBreakout = (strcasecmp($setupType, 'Breakout') === 0);

        // Entry
        if ($isBreakout && $res20 !== null) {
            $entry = (float)$this->tickRule->roundUp($res20);
        } else {
            $entry = (float)$this->tickRule->roundUp($close);
        }

        // Stop: round_down(min(low, ll5) - tick)
        $minLow = $low;
        if ($ll5 !== null && is_numeric($ll5) && (float)$ll5 > 0) {
            $minLow = min($minLow, (float)$ll5);
        }
        $tickMin = (float)$this->tickRule->tickSize(max(1.0, $minLow));
        $sl = (float)$this->tickRule->roundDown($minLow - $tickMin);

        // R and TP
        $tp1 = null;
        $tp2 = null;
        $R = $entry - $sl;
        if ($entry > 0 && $sl > 0 && $R > 0) {
            $tp1Raw = $entry + ($minRr * $R);
            $cap = $isBreakout ? $res50 : $res20;
            if ($cap !== null && $cap > 0) {
                $tp1Raw = min($tp1Raw, (float)$cap);
            }
            $tp1 = (float)$this->tickRule->roundDown($tp1Raw);
            // Optional tp2 for sizing/UX (not locked by policy; keep deterministic)
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



    private function buildIntradayLightLevels(array $ctx, string $setupType): array
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
    private function clamp01(float $v): float
    {
        if ($v < 0.0) return 0.0;
        if ($v > 1.0) return 1.0;
        return $v;
    }

    private function norm01(float $v, float $lo, float $hi): float
    {
        if ($hi <= $lo) return 0.0;
        return $this->clamp01(($v - $lo) / ($hi - $lo));
    }

    /**
     * Map PatternClassifier's signal_code to [0..1] score.
     * Deterministic; used by WEEKLY_SWING scoring.
     */
    private function wsPatternScoreFromSignal($signalCode): float
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
            bool $hasOpenPositions,
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

            // If caller does not provide capital, we still produce PLAN (pure EOD selection) without allocations.
            if ($capitalTotal === null) {
                // Mode A: no capital sizing. Still return a ranked list of recommended tickers
                // so UI/users can see what the system would pick (docs/watchlist/watchlist.md).
                $targetNoCap = min($maxToday, $maxPos);
                $targetNoCap = max(0, $targetNoCap);

                $allocsNoCap = [];
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
                    $allocsNoCap[] = [
                        'ticker_code' => (string)($c['ticker_code'] ?? ''),
                        'alloc_pct' => null,
                        'alloc_budget' => null,
                        'entry_price_ref' => (int)($c['levels']['entry_trigger_price'] ?? 0),
                        'lots_recommended' => null,
                        'estimated_cost' => null,
                    ];
                }

                return [
                    'mode' => 'PLAN',
                    'risk_per_trade_pct' => $riskPct,
                    'capital_total' => null,
                    'cash_remaining' => null,
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
                    'capital_total' => $capitalTotal,
                    'cash_remaining' => $capitalTotal,
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
                    'capital_total' => $capitalTotal,
                    'cash_remaining' => $capitalTotal,
                    'max_positions_today' => 0,
                    'allocations' => [],
                    'skipped' => [],
                ];
            }

            $target = min($maxToday, $maxPos);
            $target = max(0, $target);

            if ($target <= 0 || empty($topPickIndices)) {
                return [
                    'mode' => $hasOpenPositions ? 'CARRY_ONLY' : 'NO_TRADE',
                    'risk_per_trade_pct' => $riskPct,
                    'capital_total' => $capitalTotal,
                    'cash_remaining' => $capitalTotal,
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
                    'capital_total' => $capitalTotal,
                    'cash_remaining' => $capitalTotal,
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
                'capital_total' => $capitalTotal,
                'cash_remaining' => $cashRemaining,
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
        if ($canon === null || $ind === null) return false;
        return ((float)$canon >= $minCanon) && ((float)$ind >= $minInd);
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

    private function tradingDaysAhead(string $fromDate, string $toDate): int
    {
        if ($toDate <= $fromDate) return 0;
        $dates = $this->calRepo->tradingDatesBetween($fromDate, $toDate);
        // tradingDatesBetween includes both ends? repo returns inclusive start/end? Let's inspect: it likely returns between exclusive? We'll treat as list between (>=start and <=end)
        // We want number of trading days strictly after fromDate up to toDate.
        $n = 0;
        foreach ($dates as $d) {
            if ($d > $fromDate && $d <= $toDate) $n++;
        }
        return $n;
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

    private function tradingDaysBetweenInclusive(string $fromDate, string $toDate): ?int
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
