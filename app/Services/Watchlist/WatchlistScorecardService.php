<?php

namespace App\Services\Watchlist;

use App\DTO\Watchlist\Scorecard\EligibilityCheckDto;
use App\DTO\Watchlist\Scorecard\EligibilityResultDto;
use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\Repositories\TickerOhlcDailyRepository;
use App\Support\Clock;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Scorecard\ExecutionEligibilityEvaluator;
use App\Trade\Watchlist\Scorecard\ScorecardMetricsCalculator;
use App\Trade\Watchlist\Scorecard\ScorecardRepository;
use App\Trade\Watchlist\Scorecard\StrategyCheckRepository;
use App\Trade\Watchlist\Scorecard\StrategyRunRepository;

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
        ScorecardConfig $cfg,
        Clock $clock
    ) {
        $this->runRepo = $runRepo;
        $this->checkRepo = $checkRepo;
        $this->scoreRepo = $scoreRepo;
        $this->evaluator = $evaluator;
        $this->calculator = $calculator;
        $this->ohlcRepo = $ohlcRepo;
        $this->cfg = $cfg;
        $this->clock = $clock;
    }

    /**
     * Save / upsert strategy run (plan) from watchlist contract payload.
     */
    public function saveStrategyRun(array $payload, string $source = 'watchlist'): int
    {
        $dto = $this->normalizeStrategyRunPayload($payload);
        return $this->runRepo->upsertFromDto($dto, $source);
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
        $mode = strtoupper(trim((string)($payload['recommendation']['mode'] ?? ($payload['mode'] ?? ''))));

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
        $rank = 1;
        foreach ($rows as $cand) {
            if (!is_array($cand)) continue;
            $dto = \App\DTO\Watchlist\Scorecard\CandidateDto::fromArray($cand, $guardsFallback, $rank);
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
    public function checkLive(string $tradeDate, string $execDate, string $policy, array $snapshot, string $source = 'watchlist'): array
    {
        $run = $this->runRepo->getRunDto($tradeDate, $execDate, $policy, $source);
        if (!$run) {
            throw new \RuntimeException("strategy run not found: $tradeDate/$execDate/$policy (source=$source)");
        }

        $checkedAtFallback = $this->clock->nowRfc3339();
        $snapDto = LiveSnapshotDto::fromArray($snapshot, $this->cfg, $checkedAtFallback);
        $resultDto = $this->evaluator->evaluate($run, $snapDto, $this->cfg);

        if ($run->runId > 0) $this->checkRepo->insertCheckFromDto($run->runId, $snapDto, $resultDto);

        return $resultDto->toArray();
    }

    /**
     * Compute and persist scorecard for the latest check.
     *
     * @return array<string,mixed>
     */
    public function computeScorecard(string $tradeDate, string $execDate, string $policy, string $source = 'watchlist'): array
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

        return [
            'run_id' => $run->runId,
            'trade_date' => $tradeDate,
            'exec_trade_date' => $execDate,
            'exec_date' => $execDate,
            'policy' => $policy,
            'feasible_rate' => $calc->feasibleRate,
            'fill_rate' => $calc->fillRate,
            'outcome_rate' => $calc->outcomeRate,
            'details' => $calc->payload,
            'latest_check' => $latest ? [
                'check_id' => $latest->checkId,
                'checked_at' => $latest->checkedAt,
                'snapshot' => $latest->snapshot,
                'result' => $latest->result,
            ] : null,
        ];
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
            $reasons = isset($row['reasons']) && is_array($row['reasons']) ? array_map('strval', $row['reasons']) : [];
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
