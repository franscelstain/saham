<?php

namespace App\Services\Watchlist;

use App\DTO\Watchlist\Scorecard\EligibilityCheckDto;
use App\DTO\Watchlist\Scorecard\EligibilityResultDto;
use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\Repositories\TickerRepository;
use App\Repositories\TickerOhlcDailyRepository;
use App\Repositories\WatchlistPersistenceRepository;
use App\Repositories\IntradaySnapshotRepository;
use App\Support\Clock;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Scorecard\ExecutionEligibilityEvaluator;
use App\Trade\Watchlist\Scorecard\ScorecardMetricsCalculator;
use App\Trade\Watchlist\Scorecard\ScorecardRepository;
use App\Trade\Watchlist\Scorecard\StrategyCheckRepository;
use App\Trade\Watchlist\Scorecard\StrategyRunRepository;
use App\Trade\Watchlist\Scorecard\ExecutionSlicesSynthesizer;

class WatchlistScorecardService
{
    /** @var StrategyRunRepository */
    private $runRepo;
    /** @var StrategyCheckRepository */
    private $checkRepo;
    /** @var ScorecardRepository */
    private $scoreRepo;
    /** @var ExecutionEligibilityEvaluator */
    private $evaluator;
    /** @var ScorecardMetricsCalculator */
    private $calculator;
    /** @var TickerOhlcDailyRepository */
    private $ohlcRepo;
    /** @var WatchlistPersistenceRepository */
    private $persistRepo;
    /** @var IntradaySnapshotRepository */
    private $intradayRepo;
    /** @var TickerRepository */
    private $tickerRepo;
    /** @var ScorecardConfig */
    private $cfg;
    /** @var Clock */
    private $clock;

    public function __construct(
        StrategyRunRepository $runRepo,
        StrategyCheckRepository $checkRepo,
        ScorecardRepository $scoreRepo,
        ExecutionEligibilityEvaluator $evaluator,
        ScorecardMetricsCalculator $calculator,
        TickerOhlcDailyRepository $ohlcRepo,
        WatchlistPersistenceRepository $persistRepo,
        IntradaySnapshotRepository $intradayRepo,
        TickerRepository $tickerRepo,
        ScorecardConfig $cfg,
        Clock $clock
    ) {
        $this->runRepo = $runRepo;
        $this->checkRepo = $checkRepo;
        $this->scoreRepo = $scoreRepo;
        $this->evaluator = $evaluator;
        $this->calculator = $calculator;
        $this->ohlcRepo = $ohlcRepo;
        $this->persistRepo = $persistRepo;
        $this->intradayRepo = $intradayRepo;
        $this->tickerRepo = $tickerRepo;
        $this->cfg = $cfg;
        $this->clock = $clock;
    }

    /**
     * Save / upsert strategy run (plan) from a DTO.
     */
    public function saveStrategyRunDto(StrategyRunDto $dto, string $source = 'watchlist'): int
    {
        return $this->runRepo->upsertFromDto($dto, $source);
    }

    /**
     * Ensure strategy run exists for check-live.
     *
     * If missing, we try to bootstrap it from the persisted preopen contract in watchlist_daily
     * using the same (policy, exec_date, source) key. This keeps the operator flow simple:
     * preopen => check-live, without requiring a separate "save plan" command.
     */
    private function ensureStrategyRunExists(string $tradeDate, string $execDate, string $policy, string $source): void
    {
        $existing = $this->runRepo->getRunDto($tradeDate, $execDate, $policy, $source);
        if ($existing) return;

        // Try to hydrate from persisted preopen contract.
        $contract = $this->persistRepo->findDailyContract($execDate, $policy, $source, $tradeDate);
        if (!is_array($contract)) return;

        $meta = is_array($contract['meta'] ?? null) ? $contract['meta'] : [];
        $groups = is_array($contract['groups'] ?? null) ? $contract['groups'] : [];

        // Preopen contract (docs/watchlist/preopen.md) uses `recommendations` (plural).
        $recs = is_array($contract['recommendations'] ?? null) ? $contract['recommendations'] : [];


        $payload = [
            'trade_date' => (string)($meta['asof_eod_date'] ?? $tradeDate),
            'exec_trade_date' => (string)($meta['trade_date'] ?? $execDate),
            'policy' => (string)($meta['policy'] ?? $policy),
            // Scorecard strategy_run stores only mode (A/B). Other recommendation fields are not needed for check-live.
            'recommendations' => [
                'mode' => (string)($recs['mode'] ?? ''),
            ],
            'meta' => ['generated_at' => (string)($meta['generated_at'] ?? '')],
            'generated_at' => (string)($meta['generated_at'] ?? ''),
            'groups' => [
                'top_picks' => is_array($groups['top_picks'] ?? null) ? $groups['top_picks'] : [],
                'secondary' => is_array($groups['secondary'] ?? null) ? $groups['secondary'] : [],
                'watch_only' => is_array($groups['watch_only'] ?? null) ? $groups['watch_only'] : [],
            ],
        ];

        // Upsert plan. Fail-soft: we don't throw if persistence missing.
        try {
            $dto = StrategyRunDto::fromPayloadArray($payload, 0, $this->cfg);
            $this->runRepo->upsertFromDto($dto, $source);
        } catch (\Throwable $e) {
            // ignore: check-live will throw later if still missing
        }
    }

    /**
     * Best-effort update retry state columns on watchlist_intraday_snapshots.
     *
     * docs/watchlist/schema.md + docs/watchlist/scorecard.md: confirm_retry_count/confirm_last_checked_at/confirm_next_check_at
     */
    private function bestEffortUpdateIntradayRetryState(string $execDate, LiveSnapshotDto $snapshot, EligibilityCheckDto $resultDto): void
    {
        $codes = [];
        foreach ($resultDto->results as $r) {
            if (is_object($r) && isset($r->tickerCode)) {
                $c = strtoupper(trim((string)$r->tickerCode));
                if ($c !== '') $codes[] = $c;
            }
        }
        $codes = array_values(array_unique($codes));
        if (empty($codes)) return;

        $map = $this->tickerRepo->resolveIdsByCodes($codes);
        if (empty($map)) return;

        $checkedAt = (string)$snapshot->checkedAt;
        foreach ($resultDto->results as $r) {
            if (!$r instanceof EligibilityResultDto) continue;
            $code = strtoupper(trim((string)$r->tickerCode));
            if ($code === '' || !isset($map[$code])) continue;

            $tickerId = (int)$map[$code];
            $next = $r->nextCheckAt !== null ? (string)$r->nextCheckAt : null;

            $computed = is_array($r->computed) ? $r->computed : [];
            $retry = isset($computed['retry_count']) && is_numeric($computed['retry_count']) ? (int)$computed['retry_count'] : 0;

            // Update only if it is relevant (DELAY / next_check_at exists / retry_count advanced)
            if ($r->decision === 'DELAY' || $next !== null || $retry > 0) {
                try {
                    $this->intradayRepo->updateConfirmRetryState($execDate, $tickerId, $retry, $checkedAt, $next);
                } catch (\Throwable $e) {
                    // best-effort
                }
            }
        }
    }

    /**
     * Normalize watchlist contract payload into the scorecard strategy_run schema.
     *
     * The watchlist contract uses keys like: ticker_code, watchlist_score, levels.entry_trigger_price,
     * sizing.slices, sizing.slice_pct (float). Scorecard expects: ticker, score, entry_trigger,
     * guards (max_chase_pct, gap_up_block_pct, spread_max_pct), slices, slice_pct (array).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalizeStrategyRunPayload(array $payload): StrategyRunDto
    {
        $tradeDate = (string)($payload['trade_date'] ?? '');
        $execDate = (string)($payload['exec_trade_date'] ?? ($payload['exec_date'] ?? ''));
        $policy = (string)(($payload['policy']['selected'] ?? '') ?: ($payload['policy'] ?? ''));

        // Prefer an explicit generated timestamp if present; otherwise derive from now.
        $generatedAt = null;
        if (isset($payload['meta']['generated_at']) && is_string($payload['meta']['generated_at']) && $payload['meta']['generated_at'] !== '') {
            $generatedAt = $payload['meta']['generated_at'];
        } elseif (isset($payload['generated_at']) && is_string($payload['generated_at']) && $payload['generated_at'] !== '') {
            $generatedAt = $payload['generated_at'];
        } else {
            $generatedAt = $this->clock->nowRfc3339();
        }
        // Contract key is `recommendations` (plural).
        $mode = strtoupper(trim((string)(($payload['recommendations']['mode'] ?? null) ?? ($payload['mode'] ?? ''))));


        $groups = (array)($payload['groups'] ?? []);
        $guardsFallback = new \App\DTO\Watchlist\Scorecard\CandidateGuardsDto(
            $this->cfg->maxChasePctDefault,
            $this->cfg->gapUpBlockPctDefault,
            $this->cfg->spreadMaxPctDefault
        );

        $top = $this->normalizeCandidateList($groups['top_picks'] ?? [], $guardsFallback);
        $sec = $this->normalizeCandidateList($groups['secondary'] ?? [], $guardsFallback);
        $wo = $this->normalizeCandidateList($groups['watch_only'] ?? [], $guardsFallback);

        return StrategyRunDto::fromNormalized($tradeDate, $execDate, $policy, $mode, $generatedAt, $top, $sec, $wo);
    }

    /**
     * @param array<string,mixed> $cand
     * @param array<string,float> $guardsDefault
     * @return array<string,mixed>
     */
    /**
     * @param mixed $rows
     * @param \App\DTO\Watchlist\Scorecard\CandidateGuardsDto $guardsFallback
     * @return \App\DTO\Watchlist\Scorecard\CandidateDto[]
     */
    private function normalizeCandidateList($rows, \App\DTO\Watchlist\Scorecard\CandidateGuardsDto $guardsFallback): array
    {
        if (!is_array($rows)) return [];
        $out = [];
        $syn = new ExecutionSlicesSynthesizer();
        $rank = 1;
        foreach ($rows as $cand) {
            if (!is_array($cand)) continue;
            $dto = \App\DTO\Watchlist\Scorecard\CandidateDto::fromArray($cand, $guardsFallback, $rank);
            $slices = $syn->synthesizeIfMissing((array)$dto->executionSlices, $dto->entryTrigger, (float)$dto->guards->maxChasePct);
            $dto = $dto->withExecutionSlices($slices);
            if ($dto->ticker !== '') {
                $out[] = $dto;
                $rank++;
            }
        }
        return $out;
    }

    /**
     * Evaluate and persist a live check.
     *
     * @return array<string,mixed> result JSON
     */
    /**
     * Evaluate and persist a live check.
     */
    public function checkLiveDto(string $tradeDate, string $execDate, string $policy, LiveSnapshotDto $snapshot, string $source = 'watchlist'): EligibilityCheckDto
    {
        // If run missing, attempt to auto-hydrate from watchlist_daily (preopen persistence)
        $this->ensureStrategyRunExists($tradeDate, $execDate, $policy, $source);

        $run = $this->runRepo->getRunDto($tradeDate, $execDate, $policy, $source);
        if (!$run) throw new \RuntimeException("strategy run not found: $tradeDate/$execDate/$policy (source=$source)");

        $resultDto = $this->evaluator->evaluate($run, $snapshot, $this->cfg);

        // Update retry state (fail-soft)
        $this->bestEffortUpdateIntradayRetryState($execDate, $snapshot, $resultDto);

        if ($run->runId > 0) {
            $this->checkRepo->insertCheckFromDto($run->runId, $snapshot, $resultDto);
        }

        return $resultDto;
    }

    /**
     * Compute and persist scorecard for the latest check.
     *
     * @return array<string,mixed>
     */
    /**
     * Compute and persist scorecard metrics for the latest check.
     */
    public function computeScorecardDto(string $tradeDate, string $execDate, string $policy, string $source = 'watchlist'): \App\DTO\Watchlist\Scorecard\ScorecardMetricsDto
    {
        $run = $this->runRepo->getRunDto($tradeDate, $execDate, $policy, $source);
        if (!$run) {
            throw new \RuntimeException("strategy run not found: $tradeDate/$execDate/$policy (source=$source)");
        }

        if ($run->runId <= 0) throw new \RuntimeException('invalid run_id');

        $latest = $this->checkRepo->getLatestCheckDto($run->runId);
        $latestCheckDto = $latest ? $this->mapEligibilityCheckFromArray($latest->result) : null;

        // Repo call stays in service (orchestrator). Calculator stays pure.
        $tickers = [];
        foreach (array_merge($run->topPicks, $run->secondary) as $c) {
            if (is_object($c) && isset($c->ticker) && $c->ticker !== '') {
                $tickers[] = (string)$c->ticker;
            }
        }
        $tickers = array_values(array_unique($tickers));
        $ohlc = [];
        if ($run->execDate !== '' && !empty($tickers)) {
            $ohlc = $this->ohlcRepo->mapOhlcByTickerCodesForDate($run->execDate, $tickers);
        }

        $calc = $this->calculator->compute($run, $latestCheckDto, $ohlc);
        $this->scoreRepo->upsertScorecardFromDto($run->runId, $calc);

        return $calc;
    }

    /**
     * @param array<string,mixed> $a
     */
    private function mapEligibilityCheckFromArray(array $a): EligibilityCheckDto
    {
        $rows = isset($a['results']) && is_array($a['results']) ? $a['results'] : [];
        $results = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $computed = isset($row['computed']) && is_array($row['computed']) ? $row['computed'] : [];
            $flags = isset($row['flags']) && is_array($row['flags']) ? array_map('strval', $row['flags']) : [];
            $rawReasons = isset($row['reasons']) && is_array($row['reasons']) ? $row['reasons'] : [];
            $reasons = [];
            foreach ($rawReasons as $rr) {
                if ($rr instanceof \App\DTO\Watchlist\Scorecard\CandidateReasonDto) {
                    $reasons[] = $rr;
                    continue;
                }
                if (is_array($rr)) {
                    $reasons[] = \App\DTO\Watchlist\Scorecard\CandidateReasonDto::fromArray($rr);
                    continue;
                }

                $code = is_string($rr) ? strtoupper(trim($rr)) : (string)$rr;
                if ($code === '') continue;

                // Stable legacy mapping: keep it here (service), not inside DTO.
                $lvl = ($code === 'CF_OK') ? 'INFO' : (strpos($code, 'CF_') === 0 ? 'SOFT_BLOCK' : 'INFO');

                $reasons[] = new \App\DTO\Watchlist\Scorecard\CandidateReasonDto(
                    $code,
                    \App\Trade\Explain\ReasonCatalog::getMessage($code),
                    $lvl
                );
            }
            $results[] = new EligibilityResultDto(
                strtoupper(trim((string)($row['ticker'] ?? ''))),
                (bool)($row['eligible_now'] ?? false),
                $flags,
                isset($computed['gap_pct']) && is_numeric($computed['gap_pct']) ? (float)$computed['gap_pct'] : null,
                isset($computed['spread_pct']) && is_numeric($computed['spread_pct']) ? (float)$computed['spread_pct'] : null,
                isset($computed['chase_pct']) && is_numeric($computed['chase_pct']) ? (float)$computed['chase_pct'] : null,
                $reasons,
                (string)($row['notes'] ?? ''),
            );
        }

        $def = isset($a['default_recommendation']) && is_array($a['default_recommendation']) ? $a['default_recommendation'] : null;
        $defTicker = $def && isset($def['ticker']) ? (string)$def['ticker'] : null;
        $defWhy = $def && isset($def['why']) ? (string)$def['why'] : null;

        return new EligibilityCheckDto(
            (string)($a['policy'] ?? ''),
            (string)($a['trade_date'] ?? ''),
            (string)($a['exec_trade_date'] ?? ($a['exec_date'] ?? '')),
            (string)($a['checked_at'] ?? ''),
            (string)($a['checkpoint'] ?? ''),
            $results,
            $defTicker,
            $defWhy,
        );
    }
}
