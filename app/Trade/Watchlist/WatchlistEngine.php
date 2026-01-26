<?php

namespace App\Trade\Watchlist;

use App\DTO\Watchlist\CandidateInput;
use App\Trade\Explain\LabelCatalog;
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
use App\Trade\Watchlist\Config\WatchlistPolicyConfig;
use App\Trade\Watchlist\Contracts\PolicyDocLocator;
use App\Trade\Watchlist\Contracts\WatchlistContractValidator;

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

    private WatchlistContractValidator $validator;
    private SetupTypeClassifier $setupClassifier;

    private CandidateDerivedMetricsBuilder $derivedBuilder;
    private TradeClockConfig $clockCfg;
    private WatchlistPolicyConfig $cfg;

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
        $this->policyDocs = $policyDocs;
        $this->derivedBuilder = $derivedBuilder;

        $this->validator = new WatchlistContractValidator();
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
    
    

public function build(array $opts = []): array
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

        // Requested policy can be explicit (e.g. WEEKLY_SWING) or AUTO (router).
        $requestedPolicy = strtoupper(trim((string)($opts['policy'] ?? '')));
        if ($requestedPolicy === '') {
            $requestedPolicy = strtoupper($this->cfg->policyDefault());
        }

        $allowedPolicies = ['WEEKLY_SWING','DIVIDEND_SWING','INTRADAY_LIGHT','POSITION_TRADE','NO_TRADE','AUTO'];
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

        // Breadth crash trigger is optional: only evaluate when the data exists (docs/watchlist/no_trade.md)
        $breadthCrash = false;
        $bnl = $marketSnapshot['breadth_new_low_20d'] ?? null;
        $adr = $marketSnapshot['breadth_adv_decl_ratio'] ?? null;
        if ($bnl !== null && $adr !== null && is_numeric($bnl) && is_numeric($adr)) {
            if (((float)$bnl) >= 120.0 && ((float)$adr) <= 0.40) {
                $breadthCrash = true;
            }
        }

        // Global locks (NO_TRADE gates)
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
        if ($marketRegime === 'risk-off') {
            $globalLockCodes[] = 'GL_MARKET_RISK_OFF';
            $notes[] = 'Market regime risk-off → NO_TRADE.';
        }
        if ($breadthCrash) {
            $globalLockCodes[] = 'GL_BREADTH_CRASH';
            $notes[] = 'Breadth crash (new_low_20d>=120 & adv_decl_ratio<=0.40) → NO_TRADE.';
        }

        // Load datasets used both by router & candidate rules
        $intradayByTicker = $this->intraRepo->snapshotsByTicker($execTradeDate);
        $w = $this->dividendWindow($execTradeDate);
        $divEventsByTicker = $this->divRepo->eventsByTickerInWindow((string)($w['from'] ?? ''), (string)($w['to'] ?? ''));
        $openPositions = $this->posRepo->openPositionsByTicker();
        $hasOpenPositions = !empty($openPositions);

        // Policy selection: global lock overrides everything.
        if (!empty($globalLockCodes)) {
            $policy = 'NO_TRADE';
        } else {
            $policy = $this->selectPolicy($requestedPolicy, $divEventsByTicker, $intradayByTicker, $hasOpenPositions);
        }

        // Policy doc presence gate (docs/watchlist/watchlist.md)
        if (!$this->policyDocExists($policy)) {
            $globalLockCodes[] = 'GL_POLICY_DOC_MISSING';
            $notes[] = 'Policy doc missing for selected policy: ' . $policy;
            $policy = 'NO_TRADE';
        }

        // NO_TRADE behavior: if there are open positions, expose carry-only management reason.
        if ($policy === 'NO_TRADE' && $hasOpenPositions) {
            $globalLockCodes[] = 'NT_CARRY_ONLY_MANAGEMENT';
            $notes[] = 'NO_TRADE aktif, tapi ada posisi berjalan → CARRY_ONLY management.';
        }

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

        // WEEKLY_SWING: mark default entry window selection (docs/watchlist/weekly_swing.md)
        $wsEntryWindowDefault = false;
        if ($policy === 'WEEKLY_SWING') {
            $wsDefEntry = $this->resolveWindows(["09:20-10:30", "13:35-14:30"], $session['open_time'], $session['close_time']);
            $wsDefEntry = $this->subtractBreaks($wsDefEntry, (array)($session['breaks'] ?? []));
            $wsDefAvoid = $this->resolveWindows(["09:00-09:15", "15:50-close"], $session['open_time'], $session['close_time']);
            // Compare against raw (break-adjusted) entry windows, not effective windows.
            $wsEntryWindowDefault = ($entryWindowsRaw === $wsDefEntry) && ($avoidWindows === $wsDefAvoid);
        }

        // If there is no executable entry window for today, treat as global no-trade for strict contract consistency.
        // BUT: if the policy itself declares "no-entry day" (e.g., WEEKLY_SWING Mon/Fri; DIVIDEND_SWING Fri),
        // emit the policy reason code (not GL_NO_EXEC_WINDOW) to keep auditability aligned with docs.
        if (empty($entryWindows) && empty($globalLockCodes) && $policy !== 'NO_TRADE') {
            $dow = $this->dayOfWeek((string)$execTradeDate);

            $lock = 'GL_NO_EXEC_WINDOW';
            if ($policy === 'WEEKLY_SWING' && ($dow === 'Mon' || $dow === 'Fri' || $dow === 'Sat' || $dow === 'Sun')) {
                $lock = 'WS_DOW_NO_ENTRY';
            } elseif ($policy === 'DIVIDEND_SWING' && ($dow === 'Fri' || $dow === 'Sat' || $dow === 'Sun')) {
                $lock = 'DS_DOW_NO_ENTRY';
            }

            $globalLockCodes[] = $lock;
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
                $intradayByTicker,
                $divEventsByTicker,
                $openPositions,
                $globalLockCodes
            );
            if ($row) $rows[] = $row;
        }

        // Sort deterministically (score desc, watchlist_score desc, ticker_code asc)
        usort($rows, function($a, $b) {
            $sa = (float)($a['score'] ?? 0);
            $sb = (float)($b['score'] ?? 0);
            if ($sa !== $sb) return ($sa < $sb) ? 1 : -1;
            $wa = (float)($a['watchlist_score'] ?? 0);
            $wb = (float)($b['watchlist_score'] ?? 0);
            if ($wa !== $wb) return ($wa < $wb) ? 1 : -1;
            return strcmp((string)($a['ticker_code'] ?? ''), (string)($b['ticker_code'] ?? ''));
        });

        // Confidence is based on watchlist_score percentile (docs/watchlist/watchlist.md)
        $confidenceMap = $this->computeConfidenceMap($rows);

        // Attach global timing, assign rank
        $rank = 1;
        foreach ($rows as &$r) {
            $r['rank'] = $rank++;

            // Confidence is cross-universe percentile (docs/watchlist/watchlist.md Section 7.2.1)
            $tid = (int)($r['ticker_id'] ?? 0);
            if ($tid > 0 && isset($confidenceMap[$tid])) {
                $r['confidence'] = $confidenceMap[$tid];
            } else {
                $r['confidence'] = $this->normalizeConfidence((string)($r['confidence'] ?? 'Med'));
            }

            $r['timing'] = [
                'entry_windows' => $entryWindows,
                'avoid_windows' => $avoidWindows,
                'entry_style' => $this->normalizeEntryStyle((string)($r['entry_style'] ?? 'No-trade')),
                'size_multiplier' => (float)($timingGlobal['size_multiplier'] ?? 0),
                'trade_disabled' => (bool)($timingGlobal['trade_disabled'] ?? false),
                'trade_disabled_reason' => null,
                'trade_disabled_reason_codes' => [],
            ];

            if ($wsEntryWindowDefault && !empty($r['timing']['entry_windows']) && ($r['timing']['trade_disabled'] ?? false) === false) {
                $r['reason_codes'][] = 'WS_ENTRY_WINDOW_DEFAULT';
                $r['reason_codes'] = array_values(array_unique($r['reason_codes']));
            }

            // Apply candidate-specific timing adjustments from policy rules
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

            if (!empty($globalLockCodes)) {
                $r['timing']['trade_disabled'] = true;
                $r['timing']['trade_disabled_reason'] = $globalLockCodes[0];
                $r['timing']['trade_disabled_reason_codes'] = array_values($globalLockCodes);
                $r['timing']['entry_style'] = 'No-trade';
                $r['timing']['size_multiplier'] = 0.0;
            }

                        // If global timing disables entry windows, lock candidate.
            // Do NOT override an existing primary reason (policy/global locks); only fall back to GL_NO_EXEC_WINDOW when unset.
            if (empty($entryWindows)) {
                $r['timing']['trade_disabled'] = true;

                if ($r['timing']['trade_disabled_reason'] === null) {
                    $r['timing']['trade_disabled_reason'] = 'GL_NO_EXEC_WINDOW';
                    $codes = $r['timing']['trade_disabled_reason_codes'];
                    $codes[] = 'GL_NO_EXEC_WINDOW';
                    $r['timing']['trade_disabled_reason_codes'] = array_values(array_unique($codes));
                }

                $r['timing']['entry_style'] = 'No-trade';
                $r['timing']['size_multiplier'] = 0.0;
            }


            // Ensure checklist exists
            if (!isset($r['checklist'])) $r['checklist'] = [];

            // Ensure sizing schema keys
            $r['sizing'] = $this->normalizeSizing($r['sizing'] ?? [], $policyMeta);

            // Ensure levels schema keys
            $r['levels'] = $this->normalizeLevels($r['levels'] ?? [], $r['setup_type'] ?? 'Base');

            // Candidate-specific eligibility blocks (policy docs): if ineligible for NEW ENTRY -> WATCH_ONLY today.
            $candElig = $r['_eligibility']['is_eligible_new_entry'] ?? true;
            $candBlocks = (array)($r['_eligibility']['block_codes'] ?? []);
            if ($candElig === false) {
                $r['timing']['trade_disabled'] = true;
                $r['timing']['entry_windows'] = [];
                $r['timing']['avoid_windows'] = [$session['open_time'] . '-' . $session['close_time']];
                $r['timing']['entry_style'] = 'No-trade';
                $r['timing']['size_multiplier'] = 0.0;

                $r['levels']['entry_type'] = 'WATCH_ONLY';

                                $existingPrimary = $r['timing']['trade_disabled_reason'] ?? null;

                // Prefer policy-specific block codes as the *primary* reason.
                // If we already have a strong/global lock (e.g. GL_EOD_NOT_READY, GL_SUSPENDED, GL_MECHANISM_FCA),
                // keep it. If the current reason is generic (GL_NO_EXEC_WINDOW) or unset, override with the policy block.
                $genericPrimaries = ['GL_NO_EXEC_WINDOW', 'GL_LEGACY_CODE_UNMAPPED'];
                if ($existingPrimary === null || in_array((string)$existingPrimary, $genericPrimaries, true)) {
                    $r['timing']['trade_disabled_reason'] = !empty($candBlocks) ? (string)$candBlocks[0] : 'GL_LEGACY_CODE_UNMAPPED';
                }

                $codes = (array)($r['timing']['trade_disabled_reason_codes'] ?? []);
                foreach ($candBlocks as $bc) { $codes[] = (string)$bc; }

                // Ensure the primary reason is always present in reason_codes (even when candBlocks is empty).
                if ($r['timing']['trade_disabled_reason'] !== null) {
                    $codes[] = (string)$r['timing']['trade_disabled_reason'];
                }

                $r['timing']['trade_disabled_reason_codes'] = array_values(array_unique($codes));
            }

            // remove internal eligibility helper to keep payload clean
            if (isset($r['_eligibility'])) unset($r['_eligibility']);
            if (isset($r['_policy_rules'])) unset($r['_policy_rules']);
        }

unset($r);

// Policy-specific viability checks (docs/watchlist/*).
// May downgrade to WATCH_ONLY or DROP deterministically.
$capitalTotal = $opts['capital_total'] ?? null;

$rr = function(?int $entry, ?int $sl, ?int $tp1, int $tick) {
    if ($entry === null || $sl === null || $tp1 === null) return null;
    $risk = max(($entry - $sl), $tick);
    if ($risk <= 0) return null;
    return ((float)($tp1 - $entry)) / (float)$risk;
};
$netEdgePct = function(?int $entry, int $lotSize, ?int $profitNet) {
    if ($entry === null || $profitNet === null) return null;
    $notional = (float)$entry * (float)$lotSize;
    if ($notional <= 0) return null;
    return ((float)$profitNet) / $notional;
};

foreach ($rows as $i => $row) {
    $tradeDisabled = (bool)($row['timing']['trade_disabled'] ?? false);
    if ($tradeDisabled) continue;

    $levels = is_array($row['levels'] ?? null) ? $row['levels'] : [];
    $sizing = is_array($row['sizing'] ?? null) ? $row['sizing'] : [];
    $tick = (int)($levels['tick_size'] ?? 1);
    $lotSize = (int)($sizing['lot_size'] ?? 100);

    $entry = $levels['entry_trigger_price'] ?? null;
    $sl = $levels['stop_loss_price'] ?? null;
    $tp1 = $levels['tp1_price'] ?? null;

    if ($policy === 'WEEKLY_SWING') {
        if ($capitalTotal === null) {
            $rows[$i]['reason_codes'][] = 'WS_VIABILITY_NOT_EVAL_NO_CAPITAL';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            continue;
        }

        $minLots = (int)($policyMeta['min_lots'] ?? 1);
        $minEdge = (float)($policyMeta['min_net_edge_pct'] ?? 0.0);

        $lotsRec = $sizing['lots_recommended'] ?? null;
        $profitNet = $sizing['profit_tp2_net'] ?? null;
        $edge = $netEdgePct(is_int($entry) ? $entry : null, $lotSize, is_int($profitNet) ? $profitNet : null);

        $fail = false;
        if ($lotsRec !== null && (int)$lotsRec < $minLots) $fail = true;
        if ($edge !== null && $edge < $minEdge) $fail = true;

        if ($fail) {
            $rows[$i]['reason_codes'][] = 'WS_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            $rows[$i]['timing']['trade_disabled'] = true;
            $rows[$i]['timing']['entry_windows'] = [];
            $rows[$i]['timing']['avoid_windows'] = [$session['open_time'] . '-' . $session['close_time']];
            $rows[$i]['timing']['entry_style'] = 'No-trade';
            $rows[$i]['timing']['size_multiplier'] = 0.0;
            $rows[$i]['timing']['trade_disabled_reason'] = 'WS_MIN_TRADE_VIABILITY_FAIL';
            $codes = (array)($rows[$i]['timing']['trade_disabled_reason_codes'] ?? []);
            $codes[] = 'WS_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['timing']['trade_disabled_reason_codes'] = array_values(array_unique($codes));
            $rows[$i]['levels']['entry_type'] = 'WATCH_ONLY';
        }
        continue;
    }

    if ($policy === 'DIVIDEND_SWING') {
        if ($entry === null || $sl === null || $tp1 === null) {
            $rows[$i]['reason_codes'][] = 'DS_LEVELS_INCOMPLETE';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            $rows[$i]['timing']['trade_disabled'] = true;
            $rows[$i]['timing']['entry_windows'] = [];
            $rows[$i]['timing']['avoid_windows'] = [$session['open_time'] . '-' . $session['close_time']];
            $rows[$i]['timing']['entry_style'] = 'No-trade';
            $rows[$i]['timing']['size_multiplier'] = 0.0;
            $rows[$i]['timing']['trade_disabled_reason'] = 'DS_LEVELS_INCOMPLETE';
            $codes = (array)($rows[$i]['timing']['trade_disabled_reason_codes'] ?? []);
            $codes[] = 'DS_LEVELS_INCOMPLETE';
            $rows[$i]['timing']['trade_disabled_reason_codes'] = array_values(array_unique($codes));
            $rows[$i]['levels']['entry_type'] = 'WATCH_ONLY';
            continue;
        }

        $rval = $rr((int)$entry, (int)$sl, (int)$tp1, $tick);

        $profitNet = $sizing['profit_tp2_net'] ?? null;
        $edge = $netEdgePct((int)$entry, $lotSize, is_int($profitNet) ? $profitNet : null);
        $minEdge = (float)($policyMeta['min_net_edge_pct'] ?? 0.0);

        if (($rval !== null && $rval < 1.8) || ($edge !== null && $edge < $minEdge)) {
            $rows[$i]['reason_codes'][] = 'DS_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            $rows[$i]['_drop'] = true;
        }
        continue;
    }

    if ($policy === 'INTRADAY_LIGHT') {
        if ($entry === null || $sl === null || $tp1 === null) {
            $rows[$i]['reason_codes'][] = 'IL_LEVELS_INCOMPLETE';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            $rows[$i]['timing']['trade_disabled'] = true;
            $rows[$i]['timing']['entry_windows'] = [];
            $rows[$i]['timing']['avoid_windows'] = [$session['open_time'] . '-' . $session['close_time']];
            $rows[$i]['timing']['entry_style'] = 'No-trade';
            $rows[$i]['timing']['size_multiplier'] = 0.0;
            $rows[$i]['timing']['trade_disabled_reason'] = 'IL_LEVELS_INCOMPLETE';
            $codes = (array)($rows[$i]['timing']['trade_disabled_reason_codes'] ?? []);
            $codes[] = 'IL_LEVELS_INCOMPLETE';
            $rows[$i]['timing']['trade_disabled_reason_codes'] = array_values(array_unique($codes));
            $rows[$i]['levels']['entry_type'] = 'WATCH_ONLY';
            continue;
        }
        $rval = $rr((int)$entry, (int)$sl, (int)$tp1, $tick);
        if ($rval !== null && $rval < 1.6) {
            $rows[$i]['reason_codes'][] = 'IL_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            $rows[$i]['_drop'] = true;
        }
        continue;
    }

    if ($policy === 'POSITION_TRADE') {
        if ($entry === null || $sl === null || $tp1 === null) {
            $rows[$i]['reason_codes'][] = 'PT_LEVELS_INCOMPLETE';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            $rows[$i]['timing']['trade_disabled'] = true;
            $rows[$i]['timing']['entry_windows'] = [];
            $rows[$i]['timing']['avoid_windows'] = [$session['open_time'] . '-' . $session['close_time']];
            $rows[$i]['timing']['entry_style'] = 'No-trade';
            $rows[$i]['timing']['size_multiplier'] = 0.0;
            $rows[$i]['timing']['trade_disabled_reason'] = 'PT_LEVELS_INCOMPLETE';
            $codes = (array)($rows[$i]['timing']['trade_disabled_reason_codes'] ?? []);
            $codes[] = 'PT_LEVELS_INCOMPLETE';
            $rows[$i]['timing']['trade_disabled_reason_codes'] = array_values(array_unique($codes));
            $rows[$i]['levels']['entry_type'] = 'WATCH_ONLY';
            continue;
        }
        $rval = $rr((int)$entry, (int)$sl, (int)$tp1, $tick);
        if ($rval !== null && $rval < 2.0) {
            $rows[$i]['reason_codes'][] = 'PT_MIN_TRADE_VIABILITY_FAIL';
            $rows[$i]['reason_codes'] = array_values(array_unique($rows[$i]['reason_codes']));
            $rows[$i]['_drop'] = true;
        }
        continue;
    }
}

// Remove dropped candidates
$rows = array_values(array_filter($rows, function($r){
    return empty($r['_drop']);
}));

// Re-rank after drops (deterministic)
$rank = 1;
foreach ($rows as $j => $r2) {
    $rows[$j]['rank'] = $rank++;
    if (isset($rows[$j]['_drop'])) unset($rows[$j]['_drop']);
}

// Grouping per policy thresholds (top>=80, secondary>=65)
        $maxToday = (int)($timingGlobal['max_positions_today'] ?? 0);
        $topPickIndices = [];

        foreach ($rows as $i => $r) {
            $tradeDisabled = (bool)($r['timing']['trade_disabled'] ?? false);
            if ($tradeDisabled) {
                $rows[$i]['group'] = 'watch_only';
                continue;
            }

            $score = (float)($r['score'] ?? 0);
            if ($score >= 80 && $maxToday > 0) {
                $rows[$i]['group'] = 'top_picks';
                $topPickIndices[] = $i;
            } elseif ($score >= 65) {
                $rows[$i]['group'] = 'secondary';
            } else {
                $rows[$i]['group'] = 'watch_only';
            }
        }

        // cap top picks by maxToday (spill to secondary)
        if ($maxToday > 0 && count($topPickIndices) > $maxToday) {
            $spill = array_slice($topPickIndices, $maxToday);
            $topPickIndices = array_slice($topPickIndices, 0, $maxToday);
            foreach ($spill as $idx) {
                $rows[$idx]['group'] = 'secondary';
            }
        }

        // Recommendations (allocations)
        $hasOpenPositions = !empty($openPositions);
        $capitalTotal = $opts['capital_total'] ?? null;

        $recs = $this->buildRecommendations(
            $policy,
            $policyMeta,
            $globalLockCodes,
            $hasOpenPositions,
            $capitalTotal,
            $rows,
            $topPickIndices
        );

        // Build groups after allocations may downgrade candidates
        $top = [];
        $secondary = [];
        $watch = [];
        foreach ($rows as $r) {
            $g = (string)($r['group'] ?? 'watch_only');
            if ($g === 'top_picks') $top[] = $r;
            elseif ($g === 'secondary') $secondary[] = $r;
            else $watch[] = $r;
        }

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
                ],
                'notes' => $notes,
                'session' => $session,
            ],
            'recommendations' => $recs,
            'groups' => [
                'top_picks' => array_values(array_map(function($r) use ($policy) { return $this->toContractCandidate($r, $policy); }, $top)),
                'secondary' => array_values(array_map(function($r) use ($policy) { return $this->toContractCandidate($r, $policy); }, $secondary)),
                'watch_only' => array_values(array_map(function($r) use ($policy) { return $this->toContractCandidate($r, $policy); }, $watch)),
            ],
        ];

        // strict contract validation
        $this->validator->validate($payload);

        return $payload;
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
        array $intradayByTicker,
        array $divEventsByTicker,
        array $openPositions,
        array $globalLockCodes
    ): ?array {
        $r = $ci->toArray();

        $tickerId = (int)($r['ticker_id'] ?? 0);
        $tickerCode = (string)($r['ticker_code'] ?? '');
        $companyName = (string)($r['company_name'] ?? '');

        $close = (float)($r['close'] ?? 0);
        $open = (float)($r['open'] ?? 0);
        $high = (float)($r['high'] ?? 0);
        $low  = (float)($r['low'] ?? 0);

        $ma20 = $r['ma20'] ?? null;
        $ma50 = $r['ma50'] ?? null;
        $ma200 = $r['ma200'] ?? null;
        $rsi14 = $r['rsi14'] ?? null;
        $atr14 = $r['atr14'] ?? null;
        $volRatio = $r['vol_ratio'] ?? null;
        $liqBucket = (string)($r['liq_bucket'] ?? 'U');
        $dv20 = $r['dv20'] ?? null;

        $watchlistScore = (float)($r['score_total'] ?? 0);

        $setupType = $this->setupClassifier->classify($ci);

        // derived candle metrics (docs/watchlist/watchlist.md Section 2.5)
        $candle = $this->deriveCandleMetrics($open, $high, $low, $close);

        // optional exec snapshot
        $snap = $intradayByTicker[$tickerId] ?? null;
        $openOrLastExec = $snap ? (float)($snap['open_or_last_exec'] ?? 0) : null;
        if ($openOrLastExec !== null && $openOrLastExec <= 0) $openOrLastExec = null;

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
        $tradeDisabled = !empty($globalLockCodes);

        // status quality flags
        if ($tickerFlags['status_quality'] === 'STALE') $reasonCodes[] = 'GL_TICKER_STATUS_STALE';
        if ($tickerFlags['status_quality'] === 'UNKNOWN') $reasonCodes[] = 'GL_TICKER_STATUS_UNKNOWN';

        // global tradeability gating (docs 2.6.2)
        if ($tickerFlags['is_suspended'] === true) {
            $tradeDisabled = true;
            $reasonCodes[] = 'GL_SUSPENDED';
        }

        $hasX = in_array('X', $tickerFlags['special_notations'], true);
        $hasE = in_array('E', $tickerFlags['special_notations'], true);

        if ($hasE) $reasonCodes[] = 'GL_SPECIAL_NOTATION_E';

        if ($tickerFlags['trading_mechanism'] === 'FULL_CALL_AUCTION') {
            $tradeDisabled = true;
            $reasonCodes[] = 'GL_MECHANISM_FCA';
        }
        if ($hasX) {
            $tradeDisabled = true;
            $reasonCodes[] = 'GL_SPECIAL_NOTATION_X';
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
                'signal_age_days' => $r['signal_age_days'] ?? null,
                'setup_type' => $setupType,
                'open_or_last_exec' => $openOrLastExec,
                'spread_pct' => $snap ? ($snap['spread_pct'] ?? null) : null,
                'div_event' => $divEventsByTicker[$tickerId] ?? null,
                'exec_trade_date' => $execTradeDate,
                'candle' => $candle,
                'prev_close' => $r['prev_close'] ?? null,
            ],
            $reasonCodes
        );

        if ($policyRes['drop'] === true) {
            return null;
        }

        $reasonCodes = $policyRes['reason_codes'];
        $score = (float)($policyRes['score'] ?? 0);
        $entryStyle = (string)($policyRes['entry_style'] ?? 'Default');

        $eligBlockCodes = (array)($policyRes['eligibility_block_codes'] ?? []);

        $levels = $this->buildLevels($setupType, $r, $openOrLastExec);
        if ($tradeDisabled) {
            $levels['entry_type'] = 'WATCH_ONLY';
        }

        // Positive (optional; clarity UI) — RR sanity for Weekly Swing
        // docs/watchlist/weekly_swing.md: emit WS_RR_OK if rr(tp1) >= 2.0
        if ($policy === 'WEEKLY_SWING') {
            $entry = $levels['entry_trigger_price'] ?? null;
            $sl   = $levels['stop_loss_price'] ?? null;
            $tp1  = $levels['tp1_price'] ?? null;
            $tick = $levels['tick_size'] ?? null;

            if ($entry !== null && $sl !== null && $tp1 !== null && $tick !== null) {
                $entryF = (float)$entry;
                $slF = (float)$sl;
                $tp1F = (float)$tp1;
                $tickF = max(1.0, (float)$tick);

                $risk = max($tickF, ($entryF - $slF));
                if ($risk > 0 && $tp1F > $entryF) {
                    $rr = ($tp1F - $entryF) / $risk;
                    if ($rr >= 2.0) {
                        $reasonCodes[] = 'WS_RR_OK';
                    }
                }
            }
        }

        $sizing = $this->buildSizing($levels);

        if ($policy === 'INTRADAY_LIGHT') {
            // docs/watchlist/intraday_light.md: levels completeness + min trade viability (RR net)
            $lvOk = true;
            foreach (['entry_trigger_price','stop_loss_price','tp1_price','tp2_price','tick_size'] as $k) {
                if (!isset($levels[$k]) || !is_numeric($levels[$k]) || (float)$levels[$k] <= 0) { $lvOk = false; break; }
            }
            if (!$lvOk) {
                $levels['entry_type'] = 'WATCH_ONLY';
                $reasonCodes[] = 'IL_LEVELS_INCOMPLETE';
                $eligBlockCodes[] = 'IL_LEVELS_INCOMPLETE';
                $eligBlockCodes = array_values(array_unique($eligBlockCodes));
            } else {
                $rrNet = $sizing['rr_tp2_net'] ?? null;
                if (($levels['entry_type'] ?? '') !== 'WATCH_ONLY') {
                    if ($rrNet === null || (float)$rrNet < 1.6) {
                        // hard DROP
                        return null;
                    }
                }
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

        // Eligibility flag (used later for group selection)
        $elig = $this->evaluateEligibilityForNewEntry($policy, $eligBlockCodes, $tradeDisabled);

        return [
            'ticker_id' => $tickerId,
            'ticker_code' => $tickerCode,
            'company_name' => $companyName,
            'policy' => $policy,
            'policy_tags' => [$policy],
            'setup_type' => $setupType,
            'entry_style' => $entryStyle,
            'score' => max(0.0, $score),
            'watchlist_score' => $watchlistScore,
            'confidence' => (string)($policyRes['confidence'] ?? 'Medium'),
            'reason_codes' => array_values(array_unique($reasonCodes)),

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
                'open_or_last_exec' => $openOrLastExec ? (int)round($openOrLastExec) : null,
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
        $openOrLast = $x['open_or_last_exec'] ?? null;
        $close = (float)($x['close'] ?? 0);

        // helper: required minimal fields (policy may choose to use this or not)
        $requiredOk = function(array $reqKeys) use ($x): bool {
            foreach ($reqKeys as $k) {
                if (!array_key_exists($k, $x) || $x[$k] === null) return false;
            }
            // ensure candle/price basis is sane
            if (($x['open'] ?? 0) <= 0) return false;
            if (($x['high'] ?? 0) <= 0) return false;
            if (($x['low'] ?? 0) <= 0) return false;
            if (($x['close'] ?? 0) <= 0) return false;
            return true;
        };

        // EOD gap risk (soft), derived from EOD open vs prev close (if available)
        $gapRiskEod = false;
        $prevClose = $x['prev_close'] ?? null;
        if ($prevClose !== null && (float)$prevClose > 0 && ($x['open'] ?? null) !== null) {
            $gap = abs(((float)$x['open'] - (float)$prevClose) / (float)$prevClose);
            if ($gap >= 0.04) $gapRiskEod = true;
        }

        if ($policy === 'WEEKLY_SWING') {
            // Hard filters
            if (!$requiredOk(['ma20','ma50','ma200','rsi14','atr14','atr_pct','vol_ratio','vol_sma20','liq_bucket','dv20'])) {
                $drop = true; $reasonCodes[] = 'WS_DATA_INCOMPLETE';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }

            if (!in_array($liq, ['A','B'], true)) {
                $drop = true; $reasonCodes[] = 'WS_LIQ_TOO_LOW';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }

            if ($atrPct !== null && (float)$atrPct > 0.10) {
                $drop = true; $reasonCodes[] = 'WS_VOL_TOO_HIGH';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }

            // Setup freshness
            $signalAge = $x['signal_age_days'] ?? null;
            $eventDriven = in_array($setup, ['Breakout','Continuation','Reversal'], true);
            if ($eventDriven) {
                if ($signalAge === null) {
                    $reasonCodes[] = 'WS_SIGNAL_AGE_UNKNOWN';
                    $blockCodes[] = 'WS_SIGNAL_AGE_UNKNOWN';
                } else {
                    if ((int)$signalAge > 5) {
                        $drop = true; $reasonCodes[] = 'WS_SIGNAL_STALE';
                        return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
                    }
                }
            }

            // Price outlier / CA risk (simple heuristic using prev_close if available)
            if ($prevClose !== null && (float)$prevClose > 0) {
                $retAbs = abs(($close - (float)$prevClose) / (float)$prevClose);
                if ($retAbs > 0.40) {
                    $drop = true; $reasonCodes[] = 'WS_PRICE_OUTLIER_CA_RISK';
                    return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
                }
            }

            // Soft filters
            if ($rsi !== null && (float)$rsi >= 75.0) {
                $score -= 6.0;
                $entryStyle = 'Pullback-wait';
                $sizeAdj *= 0.8;
                $reasonCodes[] = 'WS_RSI_OVERHEAT';
            }

            // wick distribution
            $c = $x['candle'] ?? null;
            if (is_array($c)) {
                $upper = (float)($c['upper_wick_pct'] ?? 0);
                $closeNearHigh = (bool)($c['close_near_high'] ?? false);
                if ($upper >= 0.55 && !$closeNearHigh) {
                    $score -= 8.0;
                    $reasonCodes[] = 'WS_WICK_DISTRIBUTION';
                }
            }

            if ($atrPct !== null && (float)$atrPct > 0.08 && (float)$atrPct <= 0.10) {
                $score -= 5.0;
                $sizeAdj *= 0.8;
                $reasonCodes[] = 'WS_VOL_HIGH';
            }

            if ($gapRiskEod) {
                $score -= 6.0;
                $shiftEntryWindows = 'AFTERNOON_ONLY';
                $reasonCodes[] = 'WS_GAP_RISK_EOD';
            }

            // Setup allowlist
            if (!in_array($setup, ['Breakout','Pullback','Continuation','Reversal'], true)) {
                $reasonCodes[] = 'WS_SETUP_NOT_ALLOWED';
                $blockCodes[] = 'WS_SETUP_NOT_ALLOWED';
            }

            if ($setup === 'Reversal') {
                $entryStyle = 'Reversal-confirm';
                $sizeAdj *= 0.8;
            }

            // Day-of-week lifecycle for NEW ENTRY evaluated later at payload level; here only guard codes:
            $dow = $this->dayOfWeek((string)($x['exec_trade_date'] ?? ''));
            if ($dow === 'Mon' || $dow === 'Fri') {
                $reasonCodes[] = 'WS_DOW_NO_ENTRY';
                $blockCodes[] = 'WS_DOW_NO_ENTRY';
            }

            // Preopen snapshot requirement & guards
            if ($openOrLast === null) {
                $reasonCodes[] = 'WS_PREOPEN_PRICE_MISSING';
                $blockCodes[] = 'WS_PREOPEN_PRICE_MISSING';
            } else {
                if ($openOrLast > $close * (1.0 + 0.02)) {
                    $reasonCodes[] = 'WS_CHASE_BLOCK_DISTANCE_TOO_FAR';
                    $blockCodes[] = 'WS_CHASE_BLOCK_DISTANCE_TOO_FAR';
                }
                if ($openOrLast > $close * (1.0 + 0.03)) {
                    $reasonCodes[] = 'WS_GAP_UP_BLOCK';
                    $blockCodes[] = 'WS_GAP_UP_BLOCK';
                }
            }

            // Positive codes (optional)
            if ($setup === 'Breakout') $reasonCodes[] = 'WS_SETUP_BREAKOUT';
            if ($setup === 'Pullback') $reasonCodes[] = 'WS_SETUP_PULLBACK';
            if ($setup === 'Continuation') $reasonCodes[] = 'WS_SETUP_CONTINUATION';
            if ($setup === 'Reversal') $reasonCodes[] = 'WS_SETUP_REVERSAL';

            $ma20 = (float)$x['ma20']; $ma50 = (float)$x['ma50'];
            if ($close > $ma20 && $ma20 > $ma50) $reasonCodes[] = 'WS_TREND_ALIGN_OK';
            if (($x['vol_ratio'] ?? null) !== null && (float)$x['vol_ratio'] >= 1.5) $reasonCodes[] = 'WS_VOLUME_OK';
            if ($liq === 'A') $reasonCodes[] = 'WS_LIQ_OK';

            return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
        }

        if ($policy === 'DIVIDEND_SWING') {
            if (!in_array($liq, ['A','B'], true)) {
                $drop = true; $reasonCodes[] = 'DS_LIQ_TOO_LOW';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }

            // treat missing ATR% as unsafe volatility
            if ($atrPct === null || (float)$atrPct > 0.08) {
                $drop = true; $reasonCodes[] = 'DS_VOL_TOO_HIGH';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }

            // Event gate 3..12 trading days to cum_date
            $ev = $x['div_event'] ?? null;
            if (!is_array($ev) || empty($ev['cum_date'])) {
                $drop = true; $reasonCodes[] = 'DS_EVENT_WINDOW_OUTSIDE';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }
            $cum = (string)$ev['cum_date'];
            $daysToCum = $this->tradingDaysAhead((string)$x['exec_trade_date'], $cum);
            if ($daysToCum < 3 || $daysToCum > 12) {
                $drop = true; $reasonCodes[] = 'DS_EVENT_WINDOW_OUTSIDE';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }

            // Yield sanity
            $yieldEst = isset($ev['dividend_yield_est']) ? (float)$ev['dividend_yield_est'] : null;
            if ($yieldEst !== null && $yieldEst < 0.020) {
                $score -= 10.0;
                $reasonCodes[] = 'DS_YIELD_LOW';
                $confidence = 'Med';
            }

            if ($rsi !== null && (float)$rsi >= 75.0) {
                $score -= 6.0;
                $entryStyle = 'Pullback-wait';
                $reasonCodes[] = 'DS_RSI_OVERHEAT';
            }

            if ($gapRiskEod) {
                $score -= 6.0;
                $reasonCodes[] = 'DS_GAP_RISK_EOD';
            }

            // Setup allowlist
            $allow = ['Pullback','Continuation','Breakout','Reversal'];
            if (!in_array($setup, $allow, true)) {
                $blockCodes[] = 'DS_LEVELS_INCOMPLETE'; // conservative
            }

            // Conditional setup rules
            if ($setup === 'Breakout') {
                if ($daysToCum <= 4) {
                    $reasonCodes[] = 'DS_LATE_CYCLE_BREAKOUT_BLOCK';
                    $blockCodes[] = 'DS_LATE_CYCLE_BREAKOUT_BLOCK';
                }
                $ma20 = (float)$x['ma20']; $ma50 = (float)$x['ma50'];
                if (!($close > $ma20 && $ma20 > $ma50)) {
                    $reasonCodes[] = 'DS_TREND_NOT_ALIGNED';
                    $blockCodes[] = 'DS_TREND_NOT_ALIGNED';
                }
            }

            if ($setup === 'Reversal') {
                $ma50 = (float)$x['ma50']; $ma20 = (float)$x['ma20'];
                $ok = ($close >= $ma50) || ($close > $ma20 && ($rsi !== null && (float)$rsi >= 45.0));
                if (!$ok) {
                    $reasonCodes[] = 'DS_REVERSAL_BLOCK_BEARISH';
                    $blockCodes[] = 'DS_REVERSAL_BLOCK_BEARISH';
                }
            }

            // DOW no entry on Fri
            $dow = $this->dayOfWeek((string)($x['exec_trade_date'] ?? ''));
            if ($dow === 'Fri') {
                $reasonCodes[] = 'DS_DOW_NO_ENTRY';
                $blockCodes[] = 'DS_DOW_NO_ENTRY';
            }

            // Snapshot requirement / guards
            if ($openOrLast === null) {
                $reasonCodes[] = 'DS_PREOPEN_PRICE_MISSING';
                $blockCodes[] = 'DS_PREOPEN_PRICE_MISSING';
            } else {
                if ($openOrLast > $close * (1.0 + 0.015)) {
                    $reasonCodes[] = 'DS_CHASE_BLOCK_DISTANCE_TOO_FAR';
                    $blockCodes[] = 'DS_CHASE_BLOCK_DISTANCE_TOO_FAR';
                }
                if ($openOrLast > $close * (1.0 + 0.02)) {
                    $reasonCodes[] = 'DS_GAP_UP_BLOCK';
                    $blockCodes[] = 'DS_GAP_UP_BLOCK';
                }
            }

            return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
        }

        if ($policy === 'INTRADAY_LIGHT') {
            if ($openOrLast === null) {
                // policy availability is handled in detectPolicy; here we block candidate new entry deterministically
                $reasonCodes[] = 'IL_SNAPSHOT_MISSING';
                $blockCodes[] = 'IL_SNAPSHOT_MISSING';
            }

            if ($liq !== 'A') {
                $drop = true; $reasonCodes[] = 'IL_LIQ_TOO_LOW';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }

            // treat missing ATR% as unsafe volatility
            if ($atrPct === null || (float)$atrPct > 0.06) {
                $drop = true; $reasonCodes[] = 'IL_VOL_TOO_HIGH';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }

            if ($rsi !== null && (float)$rsi >= 78.0) {
                $score -= 6.0;
                $entryStyle = 'Pullback-wait';
                $reasonCodes[] = 'IL_RSI_OVERHEAT';
            }

            // spread proxy penalty (threshold not specified in doc; use conservative 0.006)
            $spread = $x['spread_pct'] ?? null;
            if ($spread !== null && (float)$spread > 0.006) {
                $score -= 10.0;
                $reasonCodes[] = 'IL_SPREAD_WIDE';
            }

            // setup allowlist
            if (!in_array($setup, ['Breakout','Continuation'], true)) {
                $reasonCodes[] = 'IL_SETUP_NOT_ALLOWED';
                $blockCodes[] = 'IL_SETUP_NOT_ALLOWED';
            }

            if ($openOrLast !== null) {
                if ($openOrLast > $close * (1.0 + 0.010)) {
                    $reasonCodes[] = 'IL_CHASE_BLOCK_DISTANCE_TOO_FAR';
                    $blockCodes[] = 'IL_CHASE_BLOCK_DISTANCE_TOO_FAR';
                }
                if ($openOrLast > $close * (1.0 + 0.015)) {
                    $reasonCodes[] = 'IL_GAP_UP_BLOCK';
                    $blockCodes[] = 'IL_GAP_UP_BLOCK';
                }
            }

            return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
        }

        if ($policy === 'POSITION_TRADE') {
            if (!in_array($liq, ['A','B'], true)) {
                $drop = true; $reasonCodes[] = 'PT_LIQ_TOO_LOW';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
            }

            $ma200 = (float)$x['ma200']; $ma50 = (float)$x['ma50'];
            $trendOk = ($close > $ma200 && $ma50 > $ma200);
            if (!$trendOk) {
                if ($setup === 'Breakout') {
                    // docs/watchlist/position_trade.md: breakout boleh WATCH_ONLY jika trend gate belum kuat
                    $reasonCodes[] = 'PT_BREAKOUT_BLOCK_TREND_NOT_OK';
                    $blockCodes[] = 'PT_BREAKOUT_BLOCK_TREND_NOT_OK';
                    $score -= 10.0;
                    $entryStyle = 'Breakout-wait';
                } else {
                    $drop = true; $reasonCodes[] = 'PT_TREND_NOT_OK';
                    return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
                }
            }

            // treat missing ATR% as unsafe volatility
            if ($atrPct === null || (float)$atrPct > 0.07) {
                $drop = true; $reasonCodes[] = 'PT_VOL_TOO_HIGH';
                return $this->policyRes($drop, $score, $entryStyle, $confidence, $reasonCodes, $blockCodes, $sizeAdj, $shiftEntryWindows);
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

            // Setup allowlist / conditional
            if ($setup === 'Breakout') {
                // trend gate already enforced above; keep explicit positive allow
            } elseif ($setup === 'Reversal') {
                $reasonCodes[] = 'PT_REVERSAL_DISABLED_DEFAULT';
                $blockCodes[] = 'PT_REVERSAL_DISABLED_DEFAULT';
            } elseif (!in_array($setup, ['Pullback','Continuation'], true)) {
                $blockCodes[] = 'PT_LEVELS_INCOMPLETE';
            }

            // Anti-chasing (uses open_or_last_exec if available; otherwise do not evaluate optional gap rule)
            if ($openOrLast !== null) {
                if ($openOrLast > $close * (1.0 + 0.02)) {
                    $reasonCodes[] = 'PT_CHASE_BLOCK_DISTANCE_TOO_FAR';
                    $blockCodes[] = 'PT_CHASE_BLOCK_DISTANCE_TOO_FAR';
                }
                if ($openOrLast > $close * (1.0 + 0.03)) {
                    $reasonCodes[] = 'PT_GAP_UP_BLOCK';
                    $blockCodes[] = 'PT_GAP_UP_BLOCK';
                }
            }

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
     * @param array<string,mixed> $r
     * @return array<string,int|float|string|null>
     */
    private function buildLevels(string $setupType, array $r, ?float $openOrLastExec): array
    {
        $close = (float)($r['close'] ?? 0);
        $atr = (float)($r['atr14'] ?? 0);
        $support = $r['support_20d'] !== null ? (float)$r['support_20d'] : null;
        $resist = $r['resistance_20d'] !== null ? (float)$r['resistance_20d'] : null;

        $tick = $this->tickSizeByPrice($close);

        // Entry trigger (limit/stop) logic simplified but deterministic
        $entry = $close;
        if ($setupType === 'Breakout') {
            $entry = $resist !== null ? max($close + $tick, $resist) : ($close + $tick);
        }

        $risk = max($tick, $atr * 1.5);
        $sl = $close - $risk;
        if ($support !== null) $sl = min($sl, $support);
        $sl = max($tick, $sl);

        $tp1 = $entry + 2.0 * max($tick, ($entry - $sl));
        $tp2 = $entry + 3.0 * max($tick, ($entry - $sl));

        $entry = $this->roundToTick($entry, $tick, 'up');
        $sl = $this->roundToTick($sl, $tick, 'down');
        $tp1 = $this->roundToTick($tp1, $tick, 'down');
        $tp2 = $this->roundToTick($tp2, $tick, 'down');

        $entryType = 'LIMIT';
        if ($openOrLastExec === null) $entryType = 'WATCH_ONLY';

        return [
            'tick_size' => $tick,
            'entry_type' => $entryType,
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

        if (!empty($globalLockCodes)) {
            $mode = $hasOpenPositions ? 'CARRY_ONLY' : 'NO_TRADE';
            return [
                'mode' => $mode,
                'risk_per_trade_pct' => $riskPct,
                'capital_total' => $capitalTotal,
                'max_positions_today' => 0,
                'allocations' => [],
            ];
        }

        if ($capitalTotal === null || $capitalTotal <= 0) {
            // Deterministic: without capital_total we cannot size; publish but no buy.
            return [
                'mode' => 'NO_TRADE',
                'risk_per_trade_pct' => $riskPct,
                'capital_total' => null,
                'max_positions_today' => 0,
                'allocations' => [],
            ];
        }

        $n = min($maxToday, count($topPickIndices));
        if ($n <= 0) {
            return [
                'mode' => $hasOpenPositions ? 'CARRY_ONLY' : 'NO_TRADE',
                'risk_per_trade_pct' => $riskPct,
                'capital_total' => $capitalTotal,
                'max_positions_today' => 0,
                'allocations' => [],
            ];
        }

        $weights = $this->allocationWeights($n);
        $remaining = $capitalTotal;

        // Build allocations; enforce min lots viability
        $finalIdx = [];
        for ($i = 0; $i < $n; $i++) $finalIdx[] = $topPickIndices[$i];

        $allocs = [];
        foreach ($finalIdx as $k => $idx) {
            $c = $candidates[$idx];
            $w = $weights[$k] ?? (1.0 / $n);
            $budget = (int) floor($capitalTotal * $w * $sizeMult);

            $entryRef = (int)($c['levels']['entry_trigger_price'] ?? 0);
            $lotSize = 100;

            $lots = ($entryRef > 0) ? (int) floor($budget / ($entryRef * $lotSize)) : 0;
            if ($lots < (int)($policyMeta['min_lots'] ?? 1) || $budget < (int)($policyMeta['min_alloc_idr'] ?? 0)) {
                // downgrade to watch_only (policy-specific reason)
                $code = $this->policyPrefix($policy) . '_MIN_TRADE_VIABILITY_FAIL';
                $candidates[$idx]['reason_codes'][] = $code;
                $candidates[$idx]['reason_codes'] = array_values(array_unique($candidates[$idx]['reason_codes']));
                $candidates[$idx]['group'] = 'watch_only';
                $candidates[$idx]['levels']['entry_type'] = 'WATCH_ONLY';
                continue;
            }

            $shares = $lots * $lotSize;
            $rawCost = $entryRef * $shares;
            $buyFee = (int) ceil($rawCost * $this->buyFeePct);
            $slip = (int) ceil($rawCost * $this->slippagePct);
            $estCost = $rawCost + $buyFee + $slip;

            $remainingAfter = max(0, $remaining - $estCost);
            $remaining = $remainingAfter;

            $allocs[] = [
                'ticker_code' => (string)($c['ticker_code'] ?? ''),
                'alloc_pct' => round($w, 4),
                'alloc_budget' => $budget,
                'entry_price_ref' => $entryRef,
                'lots_recommended' => $lots,
                // NOTE: estimated_cost is TOTAL cost (buy + fee + slippage) so remaining_cash is consistent.
                'estimated_cost' => (int)$estCost,
                'remaining_cash' => (int)$remainingAfter,
            ];
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
            'max_positions_today' => $nAlloc,
            'allocations' => $allocs,
        ];
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

    private function hasAllowedReasonPrefix(string $code): bool
    {
        foreach (['WS_','DS_','IL_','PT_','NT_','GL_'] as $p) {
            if (strpos($code, $p) === 0) return true;
        }
        return false;
    }

    private function mapLegacyReasonCode(string $legacy, string $policyPrefix): ?string
    {
        $legacy = strtoupper(trim($legacy));
        if ($legacy === '') return null;

        // Global legacy mapping
        $global = [
            'EOD_NOT_READY' => 'GL_EOD_NOT_READY',
            'EOD_STALE' => 'GL_EOD_STALE',
            'MARKET_RISK_OFF' => 'GL_MARKET_RISK_OFF',
            'POLICY_INACTIVE' => 'GL_POLICY_INACTIVE',
        ];
        if (isset($global[$legacy])) return $global[$legacy];

        // Policy-scoped mapping
        $pp = $policyPrefix;
        if ($pp === 'GL') $pp = 'NT';

        if ($legacy === 'GAP_UP_BLOCK') return $pp . '_GAP_UP_BLOCK';
        if ($legacy === 'CHASE_BLOCK_DISTANCE_TOO_FAR') return $pp . '_CHASE_BLOCK_DISTANCE_TOO_FAR';

        if ($legacy === 'MIN_EDGE_FAIL' || $legacy === 'FEE_IMPACT_HIGH') return $pp . '_MIN_TRADE_VIABILITY_FAIL';

        if ($legacy === 'FRIDAY_EXIT_BIAS') return $pp . '_FRIDAY_EXIT_BIAS';
        if ($legacy === 'WEEKEND_RISK_BLOCK') return $pp . '_FRIDAY_EXIT_BIAS';

        if ($legacy === 'TIME_STOP_TRIGGERED' || $legacy === 'NO_FOLLOW_THROUGH') {
            if ($pp === 'PT') return 'PT_TIME_STOP_T1';
            return $pp . '_TIME_STOP_T2';
        }
        if ($legacy === 'TIME_STOP_T2') {
            if ($pp === 'PT') return 'PT_TIME_STOP_T1';
            return $pp . '_TIME_STOP_T2';
        }
        if ($legacy === 'TIME_STOP_T3') {
            if ($pp === 'PT') return 'PT_TIME_STOP_T1';
            return $pp . '_TIME_STOP_T3';
        }

        if ($legacy === 'VOLATILITY_HIGH') {
            if ($pp === 'WS') return 'WS_VOL_HIGH';
            if ($pp === 'DS') return 'DS_VOL_TOO_HIGH';
            if ($pp === 'IL') return 'IL_VOL_TOO_HIGH';
            if ($pp === 'PT') return 'PT_VOL_TOO_HIGH';
            return $pp . '_VOL_HIGH';
        }

        if ($legacy === 'SETUP_EXPIRED') return $pp . '_SIGNAL_STALE';

        return null;
    }

    private function normalizeLegacyReasonCodes(array $codes, string $policyPrefix, array &$debugRankCodes, bool &$hasAnyUnmapped): array
    {
        $out = [];
        $unmapped = false;

        foreach ($codes as $rc) {
            if (!is_string($rc) || trim($rc) === '') continue;
            $rc = strtoupper(trim($rc));

            if ($this->hasAllowedReasonPrefix($rc)) {
                $out[] = $rc;
                continue;
            }

            $mapped = $this->mapLegacyReasonCode($rc, $policyPrefix);
            if ($mapped !== null) {
                $out[] = $mapped;
                $debugRankCodes[] = $rc;
            } else {
                $debugRankCodes[] = $rc;
                $unmapped = true;
            }
        }

        $out = array_values(array_unique($out));
        $debugRankCodes = array_values(array_unique(array_filter($debugRankCodes, function($x){ return is_string($x) && trim($x) !== ''; })));

        if ($unmapped) {
            $hasAnyUnmapped = true;
            if (!in_array('GL_LEGACY_CODE_UNMAPPED', $out, true)) $out[] = 'GL_LEGACY_CODE_UNMAPPED';
        }

        return $out;
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

    private function parseCapitalTotal($val): ?int
    {
        if ($val === null) return null;
        if (is_int($val)) return $val > 0 ? $val : null;
        if (is_float($val)) return $val > 0 ? (int) round($val) : null;
        if (is_string($val)) {
            $s = preg_replace('/[^0-9]/', '', $val);
            if ($s === '') return null;
            $n = (int)$s;
            return $n > 0 ? $n : null;
        }
        return null;
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
            'be_price' => $be !== null ? (int)$be : null,
        ];
    }

    
    /**
     * Compute confidence (High/Med/Low) based on percentile of watchlist_score across universe.
     * Docs: top 20% High, middle 40% Med, bottom 40% Low.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string> map ticker_id => confidence
     */
    private function computeConfidenceMap(array $rows): array
    {
        $sorted = $rows;
        usort($sorted, function($a, $b) {
            $wa = (float)($a['watchlist_score'] ?? 0);
            $wb = (float)($b['watchlist_score'] ?? 0);
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

    /**
     * Reduce internal candidate row to strict contract schema (remove redundant fields).
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function toContractCandidate(array $row, string $policy): array
    {
        $setup = (string)($row['setup_type'] ?? 'Base');

        $timing = is_array($row['timing'] ?? null) ? $row['timing'] : [];
        $tradeDisabled = (bool)($timing['trade_disabled'] ?? false);

        // entry_style must be one of: Breakout-confirm|Pullback-wait|Reversal-confirm|No-trade
        $entryStyle = $this->normalizeEntryStyle((string)($timing['entry_style'] ?? ''));
        if ($entryStyle === '' || $entryStyle === 'Default') {
            $entryStyle = 'No-trade';
            if (!$tradeDisabled) {
                if ($setup === 'Pullback') $entryStyle = 'Pullback-wait';
                elseif ($setup === 'Reversal') $entryStyle = 'Reversal-confirm';
                elseif (in_array($setup, ['Breakout','Continuation'], true)) $entryStyle = 'Breakout-confirm';
            }
        }

        $timingOut = [
            'entry_windows' => (array)($timing['entry_windows'] ?? []),
            'avoid_windows' => (array)($timing['avoid_windows'] ?? []),
            'entry_style' => $entryStyle,
            'size_multiplier' => $tradeDisabled ? 0.0 : (float)($timing['size_multiplier'] ?? 0.0),
            'trade_disabled' => $tradeDisabled,
            'trade_disabled_reason' => $timing['trade_disabled_reason'] ?? null,
            'trade_disabled_reason_codes' => array_values((array)($timing['trade_disabled_reason_codes'] ?? [])),
        ];

        $levelsNorm = $this->normalizeLevels(is_array($row['levels'] ?? null) ? $row['levels'] : [], $setup);
        $sizingNorm = $this->normalizeSizing(is_array($row['sizing'] ?? null) ? $row['sizing'] : [], ['risk_per_trade_pct' => null]);

        // Position schema
        $pos = is_array($row['position'] ?? null) ? $row['position'] : [];
        $hasPos = (bool)($pos['has_position'] ?? false);
        $posOut = [
            'has_position' => $hasPos,
            'position_avg_price' => $pos['position_avg_price'] ?? null,
            'position_lots' => $pos['position_lots'] ?? null,
            'days_held' => $pos['days_held'] ?? null,
            'position_state' => $hasPos ? (string)($pos['position_state'] ?? 'OPEN') : null,
            'action_windows' => (array)($pos['action_windows'] ?? []),
            'updated_stop_loss_price' => $pos['updated_stop_loss_price'] ?? null,
        ];

        // Debug schema: keep only rank_reason_codes to avoid redundant payload
        $debugOut = [
            'rank_reason_codes' => array_values((array)($row['debug']['rank_reason_codes'] ?? [])),
        ];

        // Legacy reason-code mapping: never publish generic codes without prefix (docs/watchlist/watchlist.md)
        $policyPrefix = $this->policyPrefix($policy);
        $debugRank = (array)($debugOut['rank_reason_codes'] ?? []);
        $hasUnmapped = false;

        $uiReasonCodes = $this->normalizeLegacyReasonCodes((array)($row['reason_codes'] ?? []), $policyPrefix, $debugRank, $hasUnmapped);
        $timingOut['trade_disabled_reason_codes'] = $this->normalizeLegacyReasonCodes((array)($timingOut['trade_disabled_reason_codes'] ?? []), $policyPrefix, $debugRank, $hasUnmapped);

        $debugOut['rank_reason_codes'] = array_values(array_unique($debugRank));

        // Default checklist if missing/empty
        $checklist = (array)($row['checklist'] ?? []);
        if (count($checklist) === 0) {
            $checklist = [
                'Cek spread & orderbook (hindari spread lebar / antrian tipis)',
                'Pastikan tidak ada suspensi / FCA / notasi X',
                'Eksekusi hanya di window waktu policy',
            ];
        }

        return [
            'ticker_id' => (int)($row['ticker_id'] ?? 0),
            'ticker_code' => (string)($row['ticker_code'] ?? ''),
            'rank' => (int)($row['rank'] ?? 0),
            'watchlist_score' => (float)($row['watchlist_score'] ?? 0),
            'confidence' => $this->normalizeConfidence((string)($row['confidence'] ?? 'Low')),
            'setup_type' => $setup,
            'reason_codes' => array_values((array)($uiReasonCodes ?? [])),
            'debug' => $debugOut,
            'ticker_flags' => is_array($row['ticker_flags'] ?? null) ? $row['ticker_flags'] : [],
            'timing' => $timingOut,
            'levels' => $levelsNorm,
            'sizing' => $sizingNorm,
            'position' => $posOut,
            'checklist' => array_values($checklist),
        ];
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
        $from = $this->addTradingDays($execTradeDate, 3);
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
     * Policy router per docs/watchlist/watchlist.md Section 1.1.
     * AUTO precedence: DIVIDEND_SWING > INTRADAY_LIGHT > POSITION_TRADE > WEEKLY_SWING
     */
    private function selectPolicy(
        string $requestedPolicy,
        array $divEventsByTicker,
        array $intradayByTicker,
        bool $hasOpenPositions
    ): string {
        $p = strtoupper(trim($requestedPolicy));
        $allowed = ['WEEKLY_SWING','DIVIDEND_SWING','INTRADAY_LIGHT','POSITION_TRADE','NO_TRADE','AUTO'];
        if (!in_array($p, $allowed, true)) $p = 'WEEKLY_SWING';

        // Eligibility (conservative): PT is considered eligible for AUTO only
        // when there are open positions, unless explicitly enabled.
        $dsEligible = !empty($divEventsByTicker);
        $ilEligible = !empty($intradayByTicker);
        $ptEligible = $hasOpenPositions || (bool) $this->cfg->autoPositionTradeEnabled();

        $pickAuto = function() use ($dsEligible, $ilEligible, $ptEligible) {
            if ($dsEligible) return 'DIVIDEND_SWING';
            if ($ilEligible) return 'INTRADAY_LIGHT';
            if ($ptEligible) return 'POSITION_TRADE';
            return 'WEEKLY_SWING';
        };

        if ($p === 'AUTO') {
            return $pickAuto();
        }

        // Explicit request: if missing dependency, fallback to AUTO.
        if ($p === 'DIVIDEND_SWING' && !$dsEligible) return $pickAuto();
        if ($p === 'INTRADAY_LIGHT' && !$ilEligible) return $pickAuto();

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

    private function tickSizeByPrice(float $price): int
    {
        // config-driven IDX tick ladder (docs/watchlist/watchlist.md Section 3)
        return $this->tickRule->tickSize($price);
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
