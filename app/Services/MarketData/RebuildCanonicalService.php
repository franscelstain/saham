<?php

namespace App\Services\MarketData;

use App\Repositories\TickerRepository;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\MarketData\CanonicalEodRepository;
use App\Repositories\MarketData\RunRepository;
use App\Repositories\MarketData\RawEodRepository;

use App\Trade\MarketData\Config\ImportPolicy;
use App\Trade\MarketData\Config\QualityRules;
use App\Trade\MarketData\Config\ProviderPriority;

use App\Trade\MarketData\DTO\EodBar;
use App\Trade\MarketData\Select\CanonicalSelector;
use App\Trade\MarketData\Validate\EodQualityGate;

use App\Trade\Support\TradeClock;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 (WAJIB): Rebuild Canonical dari RAW (tanpa refetch).
 *
 * Goal:
 * - ubah rule/prioritas/provider tanpa memanggil API provider
 * - canonical run baru (audit trail jelas)
 * - RAW run sumber tetap immutable
 */
final class RebuildCanonicalService
{
    /** @var ImportPolicy */
    private $policy;

    /** @var TickerRepository */
    private $tickers;

    /** @var MarketCalendarRepository */
    private $calendar;

    /** @var RunRepository */
    private $runs;

    /** @var RawEodRepository */
    private $rawRepo;

    /** @var CanonicalEodRepository */
    private $canRepo;

    /** @var EodQualityGate */
    private $gate;

    /** @var CanonicalSelector */
    private $selector;

    /** @var DisagreementMajorService */
    private $disagreeSvc;

    /** @var MissingTradingDayService */
    private $missingSvc;

    /** @var SoftQualityRulesService */
    private $softQualitySvc;

    public function __construct(
        ImportPolicy $policy,
        QualityRules $rules,
        ProviderPriority $priority,
        TickerRepository $tickers,
        MarketCalendarRepository $calendar,
        RunRepository $runs,
        RawEodRepository $rawRepo,
        CanonicalEodRepository $canRepo,
        DisagreementMajorService $disagreeSvc,
        MissingTradingDayService $missingSvc,
        SoftQualityRulesService $softQualitySvc
    ) {
        $this->policy = $policy;
        $this->tickers = $tickers;
        $this->calendar = $calendar;
        $this->runs = $runs;
        $this->rawRepo = $rawRepo;
        $this->canRepo = $canRepo;
        $this->disagreeSvc = $disagreeSvc;
        $this->missingSvc = $missingSvc;
        $this->softQualitySvc = $softQualitySvc;

        $this->gate = new EodQualityGate($rules);
        $this->selector = new CanonicalSelector($priority);
    }

    /**
     * Rebuild canonical from an existing RAW run.
     *
     * @return array summary
     */
    public function run(
        ?int $sourceRunId,
        ?string $date,
        ?string $from,
        ?string $to,
        ?string $tickerCode
    ): array {
        $today = TradeClock::today();
        $beforeCutoff = TradeClock::isBeforeEodCutoff();
        $tz = TradeClock::tz();

        $effectiveEnd = $this->resolveEffectiveEndDate($today, $beforeCutoff);

        if ($date) {
            $fromEff = $date;
            $toEff = $date;
        } else {
            $toEff = $to ?: $effectiveEnd;
            $fromEff = $from ?: $this->calendar->lookbackStartDate($toEff, $this->policy->lookbackTradingDays());
        }

        if ($toEff > $effectiveEnd) $toEff = $effectiveEnd;

        // Resolve default source run if not provided:
        // pick the latest SUCCESS import run that covers end date.
        if (!$sourceRunId || $sourceRunId <= 0) {
            $sourceRunId = $this->runs->findLatestSuccessImportRunCoveringDate($toEff);
        }

        if (!$sourceRunId || $sourceRunId <= 0) {
            return [
                'run_id' => 0,
                'status' => 'FAILED',
                'reason' => 'source_run_not_found',
                'effective_start' => $fromEff,
                'effective_end' => $toEff,
            ];
        }

        // Basic guard: ensure RAW exists
        $rawPoints = $this->rawRepo->countRawPoints($sourceRunId);
        if ($rawPoints <= 0) {
            return [
                'run_id' => 0,
                'status' => 'FAILED',
                'reason' => 'source_run_has_no_raw',
                'source_run_id' => $sourceRunId,
            ];
        }

        $tradeDates = $this->calendar->tradingDatesBetween($fromEff, $toEff);
        $tradeDatesSet = array_fill_keys($tradeDates, true);
        $targetDays = count($tradeDates);

        $tickerRows = $this->tickers->listActive($tickerCode);
        $targetTickers = count($tickerRows);

        $runRow = [
            'job' => 'rebuild_canonical',
            'timezone' => $tz,
            'cutoff' => TradeClock::eodCutoff(),
            'effective_start_date' => $fromEff,
            'effective_end_date' => $toEff,
            'target_tickers' => $targetTickers,
            'target_days' => $targetDays,
            'status' => 'RUNNING',
            'notes' => 'rebuild_from_run=' . $sourceRunId,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Optional audit columns (Phase 6). If DB hasn't been migrated yet, keep compatible.
        if (Schema::hasColumn('md_runs', 'run_mode')) {
            $runRow['run_mode'] = 'REBUILD';
        }
        if (Schema::hasColumn('md_runs', 'raw_source_run_id')) {
            $runRow['raw_source_run_id'] = (int) $sourceRunId;
        }
        if (Schema::hasColumn('md_runs', 'parent_run_id')) {
            $runRow['parent_run_id'] = (int) $sourceRunId;
        }

        $runId = $this->runs->createRun($runRow);

        if ($targetDays === 0) {
            $this->runs->finishRun($runId, [
                'status' => 'FAILED',
                'coverage_pct' => 0,
                'fallback_pct' => 0,
                'hard_rejects' => 0,
                'soft_flags' => 0,
                'disagree_major' => 0,
                'missing_trading_day' => 0,
                'notes' => 'rebuild_from_run=' . $sourceRunId . ' | no_trading_days_in_range',
            ]);

            return [
                'run_id' => $runId,
                'status' => 'FAILED',
                'source_run_id' => $sourceRunId,
                'effective_start' => $fromEff,
                'effective_end' => $toEff,
                'target_tickers' => $targetTickers,
                'target_days' => 0,
                'expected_points' => 0,
                'canonical_points' => 0,
                'coverage_pct' => 0,
                'fallback_pct' => 0,
                'hard_rejects' => 0,
                'soft_flags' => 0,
                'notes' => ['no_trading_days_in_range'],
            ];
        }

        $allowedTickerIds = [];
        foreach ($tickerRows as $t) {
            $allowedTickerIds[(int) $t['ticker_id']] = true;
        }

        // Metrics
        $hardRejects = 0;
        $softFlags = 0;
        $softFlagsGate = 0;
        $fallbackPicks = 0;
        $totalPicks = 0;

        $batchUpsert = 2000;
        $canRowsBuf = [];

        // Streaming reduce by (ticker_id, trade_date)
        $curTid = null;
        $curDate = null;
        $curBySource = [];

        $flushGroup = function () use (
            &$curTid,
            &$curDate,
            &$curBySource,
            &$hardRejects,
            &$softFlags,
            &$fallbackPicks,
            &$totalPicks,
            &$canRowsBuf,
            $batchUpsert,
            $runId,
            $today,
            $beforeCutoff,
            $tradeDatesSet
        ) {
            if ($curTid === null || $curDate === null) return;

            // Skip non-trading date
            if (!isset($tradeDatesSet[$curDate])) {
                $curBySource = [];
                return;
            }

            // Build candidates with fresh validation (rules may change)
            $candidates = [];
            foreach ($curBySource as $src => $bar) {
                $val = $this->gate->validate($bar);
                if (!$val->hardValid) $hardRejects++;
                if ($val->flags) $softFlags += count($val->flags);
                $candidates[$src] = ['bar' => $bar, 'val' => $val];
            }

            $pick = $this->selector->select($curDate, $candidates);
            if (!$pick) {
                $curBySource = [];
                return;
            }

            // Cutoff hard rule: never canonicalize today before cutoff
            if ($beforeCutoff && $curDate === $today) {
                $curBySource = [];
                return;
            }

            $totalPicks++;
            if ($pick->reason === 'FALLBACK_USED') $fallbackPicks++;

            $canRowsBuf[] = [
                'run_id' => $runId,
                'ticker_id' => (int) $curTid,
                'trade_date' => $curDate,
                'chosen_source' => $pick->chosenSource,
                'reason' => $pick->reason,
                'flags' => $pick->flags ? implode(',', $pick->flags) : null,
                'open' => $pick->bar->open,
                'high' => $pick->bar->high,
                'low' => $pick->bar->low,
                'close' => $pick->bar->close,
                'adj_close' => $pick->bar->adjClose,
                'volume' => $pick->bar->volume,
                'built_at' => now(),
            ];

            if (count($canRowsBuf) >= $batchUpsert) {
                $this->canRepo->upsertMany($canRowsBuf, $batchUpsert);
                $canRowsBuf = [];
            }

            $curBySource = [];
        };

        $cursor = $this->rawRepo->cursorByRunAndRange($sourceRunId, $fromEff, $toEff, null);
        foreach ($cursor as $r) {
            $tid = (int) ($r->ticker_id ?? 0);
            if ($tid <= 0) continue;
            if (!isset($allowedTickerIds[$tid])) continue;

            $d = (string) ($r->trade_date ?? '');
            if ($d === '') continue;

            // Group switch
            if ($curTid !== null && ($tid !== (int) $curTid || $d !== (string) $curDate)) {
                $flushGroup();
            }

            $curTid = $tid;
            $curDate = $d;

            $src = strtolower((string) ($r->source ?? ''));
            if ($src === '') continue;

            $bar = new EodBar(
                $d,
                $r->open !== null ? (float) $r->open : null,
                $r->high !== null ? (float) $r->high : null,
                $r->low !== null ? (float) $r->low : null,
                $r->close !== null ? (float) $r->close : null,
                $r->adj_close !== null ? (float) $r->adj_close : null,
                $r->volume !== null ? (int) $r->volume : null
            );

            // last write wins per source (in case duplicates)
            $curBySource[$src] = $bar;
        }

        // Flush last group
        $flushGroup();

        // Flush remaining canonical
        if ($canRowsBuf) {
            $this->canRepo->upsertMany($canRowsBuf, $batchUpsert);
            $canRowsBuf = [];
        }

        // Gating: coverage
        $expected = $targetTickers * $targetDays;
        $coveragePct = $expected > 0 ? ($totalPicks * 100.0 / $expected) : 0.0;
        $fallbackPct = $totalPicks > 0 ? ($fallbackPicks * 100.0 / $totalPicks) : 0.0;

        $status = 'SUCCESS';
        $notes = [
            'rebuild_from_run=' . $sourceRunId,
            'priority=' . implode(',', $this->selectorPriorityNames()),
        ];

        if ($coveragePct < $this->policy->coverageMinPct()) {
            $status = 'CANONICAL_HELD';
            $notes[] = 'Coverage below threshold: ' . number_format($coveragePct, 2) .
                '% < ' . number_format($this->policy->coverageMinPct(), 2) . '%';
        }

        // Phase 2: Disagreement Major (multi-source) using RAW from source run
        $disagreeMajor = 0;
        if ($status === 'SUCCESS') {
            $canonicalPoints = $this->canRepo->countByRunId($runId);
            $dg = $this->disagreeSvc->compute($runId, $canonicalPoints, 0.03, 10, $sourceRunId);

            $disagreeMajor = (int) ($dg['disagree_major'] ?? 0);
            $ratio = (float) ($dg['disagree_major_ratio'] ?? 0.0);

            if ($disagreeMajor > 0) {
                $notes[] = 'disagree_major=' . $disagreeMajor;
                $notes[] = 'disagree_thr=3%';
                $notes[] = 'disagree_ratio=' . number_format($ratio * 100.0, 2) . '%';

                $samples = is_array($dg['samples'] ?? null) ? $dg['samples'] : [];
                $take = array_slice($samples, 0, 3);
                if ($take) {
                    $parts = [];
                    foreach ($take as $s) {
                        $parts[] =
                            ((int) ($s['ticker_id'] ?? 0)) . '@' . ((string) ($s['trade_date'] ?? '')) .
                            '=' . number_format(((float) ($s['pct'] ?? 0.0)) * 100.0, 2) . '%';
                    }
                    $notes[] = 'disagree_samples=' . implode(',', $parts);
                }

                $shouldHold = ($ratio >= 0.01) || ($disagreeMajor >= 20);
                if ($shouldHold) {
                    $status = 'CANONICAL_HELD';
                    $notes[] = 'held_reason=disagree_major';
                    $notes[] = 'held_rule=ratio>=1% OR count>=20';
                }
            }
        }

        $missingTradingDay = 0;

        if ($status === 'SUCCESS') {
            $mt = $this->missingSvc->compute(
                $runId,
                $fromEff,
                $toEff,
                $targetTickers,
                0.60,
                5
            );

            $missingTradingDay = (int) ($mt['missing_days'] ?? 0);

            if ($missingTradingDay > 0) {
                $notes[] = 'missing_trading_day=' . $missingTradingDay;
                $notes[] = 'missing_dates=' . implode(',', array_slice((array)($mt['missing_dates'] ?? []), 0, 5));
                $status = 'CANONICAL_HELD';
                $notes[] = 'held_reason=missing_trading_day';
            } else {
                $lowDays = (int) ($mt['low_coverage_days'] ?? 0);
                if ($lowDays >= 2) {
                    $notes[] = 'low_coverage_days=' . $lowDays;
                    $status = 'CANONICAL_HELD';
                    $notes[] = 'held_reason=low_coverage_days';
                }
            }
        }

        if ($status === 'SUCCESS') {
            $sq = $this->softQualitySvc->evaluate($runId, $fromEff, $toEff, $targetTickers, 0.25);

            $softFlagsGate = (int) ($sq['soft_flags'] ?? 0);
            if ($softFlagsGate > 0) {
                $notes[] = 'soft_flags_phase4=' . $softFlagsGate;
                foreach ($sq['rule_counts'] as $k => $v) {
                    if ($v > 0) $notes[] = "soft_{$k}={$v}";
                }
                foreach ($sq['samples'] as $k => $s) {
                    $notes[] = "soft_sample_{$k}={$s}";
                }
            }

            if (!empty($sq['hold'])) {
                $status = 'CANONICAL_HELD';
                $notes[] = 'held_reason=soft_quality';
                $notes[] = 'soft_hold_reason=' . ($sq['hold_reason'] ?? 'unknown');
            }
        }

        // If HELD -> delete canonical for this rebuild run
        if ($status === 'CANONICAL_HELD') {
            $this->canRepo->deleteByRunId($runId);
            $notes[] = 'held_deleted_canonical: run_id=' . $runId;
        }

        $softFlagsTotal = (int) ($softFlags + $softFlagsGate);

        $this->runs->finishRun($runId, [
            'status' => $status,
            'coverage_pct' => round($coveragePct, 2),
            'fallback_pct' => round($fallbackPct, 2),
            'hard_rejects' => (int) $hardRejects,
            'soft_flags' => $softFlagsTotal,
            'disagree_major' => (int) $disagreeMajor,
            'missing_trading_day' => (int) $missingTradingDay,
            'notes' => $notes ? implode(' | ', $notes) : null,
        ]);

        return [
            'run_id' => $runId,
            'status' => $status,
            'source_run_id' => $sourceRunId,
            'effective_start' => $fromEff,
            'effective_end' => $toEff,
            'target_tickers' => $targetTickers,
            'target_days' => $targetDays,
            'expected_points' => $expected,
            'canonical_points' => $totalPicks,
            'coverage_pct' => round($coveragePct, 2),
            'fallback_pct' => round($fallbackPct, 2),
            'hard_rejects' => (int) $hardRejects,
            'soft_flags' => $softFlagsTotal,
            'notes' => $notes,
        ];
    }

    private function resolveEffectiveEndDate(string $today, bool $beforeCutoff): string
    {
        if ($beforeCutoff) {
            return $this->calendar->previousTradingDate($today) ?: $today;
        }

        if ($this->calendar->isTradingDay($today)) return $today;
        return $this->calendar->previousTradingDate($today) ?: $today;
    }

    /**
     * Helper to expose priority names for notes (without poking internal).
     *
     * @return string[]
     */
    private function selectorPriorityNames(): array
    {
        // CanonicalSelector only stores ProviderPriority; we can re-read config.
        // Keep it simple for audit: read from config to reflect current policy.
        $list = (array) config('trade.market_data.providers_priority', ['yahoo']);
        $out = [];
        foreach ($list as $s) {
            $s = strtolower(trim((string) $s));
            if ($s !== '') $out[] = $s;
        }
        return $out ?: ['yahoo'];
    }
}
