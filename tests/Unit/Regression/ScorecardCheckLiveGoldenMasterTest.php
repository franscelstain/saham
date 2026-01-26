<?php

namespace Tests\Unit\Regression;

use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Scorecard\ExecutionEligibilityEvaluator;
use App\Trade\Watchlist\Scorecard\ScorecardMetricsCalculator;
use App\Services\Watchlist\WatchlistScorecardService;
use App\Support\SystemClock;
use App\Trade\Watchlist\Scorecard\StrategyRunRepository;
use App\Trade\Watchlist\Scorecard\StrategyCheckRepository;
use App\Trade\Watchlist\Scorecard\ScorecardRepository;
use App\DTO\Watchlist\Scorecard\EligibilityCheckDto;
use App\DTO\Watchlist\Scorecard\ScorecardMetricsDto;
use App\DTO\Watchlist\Scorecard\StrategyCheckDto;
use App\Repositories\TickerOhlcDailyRepository;
use Tests\TestCase;

class ScorecardCheckLiveGoldenMasterTest extends TestCase
{
    public function testGoldenMasterFixtureSnapshotMatchesExpected(): void
    {
        // Be explicit: avoid relying on helper factories across versions.
        $cfg = new ScorecardConfig(false, 0.01, 0.015, 0.004, '09:00', '15:50');
        $clock = new SystemClock();

        $runPayload = json_decode(file_get_contents(__DIR__ . '/../..' . '/Fixtures/scorecard/run_payload.json'), true);
        $snapArr = json_decode(file_get_contents(__DIR__ . '/../..' . '/Fixtures/scorecard/snapshot.json'), true);
        $expected = json_decode(file_get_contents(__DIR__ . '/../..' . '/Fixtures/scorecard/expected_check.json'), true);

        $this->assertIsArray($runPayload);
        $this->assertIsArray($snapArr);
        $this->assertIsArray($expected);

        $runDto = StrategyRunDto::fromPayloadArray($runPayload, 1, $cfg);
        $snapshot = LiveSnapshotDto::fromArray($snapArr, $cfg, $snapArr['checked_at'] ?? '');

        $tradeDate = (string)($runPayload['trade_date'] ?? '');
        $execDate = (string)(($runPayload['exec_trade_date'] ?? '') ?: ($runPayload['exec_date'] ?? ''));
        $policy = (string)(($runPayload['policy']['selected'] ?? '') ?: ($runPayload['policy'] ?? ''));
        $this->assertNotSame('', $tradeDate);
        $this->assertNotSame('', $execDate);
        $this->assertNotSame('', $policy);

        $service = new WatchlistScorecardService(
            // Repos
            new class($cfg, $runDto, $tradeDate, $execDate, $policy) extends StrategyRunRepository {
                private StrategyRunDto $dto;
                private string $tradeDate;
                private string $execDate;
                private string $policy;
                public function __construct(ScorecardConfig $cfg, StrategyRunDto $dto, string $tradeDate, string $execDate, string $policy) {
                    parent::__construct($cfg);
                    $this->dto = $dto;
                    $this->tradeDate = $tradeDate;
                    $this->execDate = $execDate;
                    $this->policy = $policy;
                }
                public function upsertFromDto(StrategyRunDto $dto, string $source = 'watchlist'): int { $this->dto = $dto; return 1; }
                public function getRunDto(string $tradeDate, string $execDate, string $policy, string $source = 'watchlist'): ?StrategyRunDto {
                    if ($tradeDate === $this->tradeDate && $execDate === $this->execDate && $policy === $this->policy) {
                        return $this->dto;
                    }
                    return null;
                }
            },
            new class extends StrategyCheckRepository {
                public function insertCheckFromDto(int $runId, LiveSnapshotDto $snapshot, EligibilityCheckDto $result): int { return 1; }
                public function getLatestCheckDto(int $runId): ?StrategyCheckDto { return null; }
            },
            new class extends ScorecardRepository {
                public function upsertScorecardFromDto(int $runId, ScorecardMetricsDto $dto): void { /* no-op */ }
            },
            // Evaluator + calculator
            new ExecutionEligibilityEvaluator($cfg),
            new ScorecardMetricsCalculator(),
            // OHLC repo (not needed for check-live)
            new class extends TickerOhlcDailyRepository {
                public function mapOhlcByTickerCodesForDate(string $tradeDate, array $tickerCodes): array { return []; }
            },
            // config + clock
            $cfg,
            $clock
        );

        $dto = $service->checkLiveDto($tradeDate, $execDate, $policy, $snapshot);
        $out = $this->normalize($dto->toArray());
        $expected = $this->normalize($expected);

        $this->assertSame($expected, $out);
    }

    /**
     * Normalize output shape across minor refactors:
     * - computed.gap_pct/spread_pct/chase_pct (preferred) vs flat fields (legacy)
     * - default_recommendation{ticker,why} (preferred) vs recommended_ticker/recommended_why (legacy)
     * - cast numeric fields to float for stable diffs
     *
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function normalize(array $a): array
    {
        if (isset($a['results']) && is_array($a['results'])) {
            foreach ($a['results'] as $i => $r) {
                if (!is_array($r)) continue;

                // Prefer nested computed
                if (isset($r['computed']) && is_array($r['computed'])) {
                    foreach (['gap_pct', 'spread_pct', 'chase_pct'] as $k) {
                        if (array_key_exists($k, $r['computed'])) {
                            $a['results'][$i]['computed'][$k] = (float)$r['computed'][$k];
                        }
                    }
                } else {
                    // Legacy flat keys -> lift into computed
                    $computed = [];
                    foreach (['gap_pct', 'spread_pct', 'chase_pct'] as $k) {
                        if (array_key_exists($k, $r)) {
                            $computed[$k] = (float)$r[$k];
                            unset($a['results'][$i][$k]);
                        }
                    }
                    if (!empty($computed)) {
                        $a['results'][$i]['computed'] = $computed;
                    }
                }
            }
        }

        // Recommendation shape
        if (!isset($a['default_recommendation']) && (isset($a['recommended_ticker']) || isset($a['recommended_why']))) {
            $a['default_recommendation'] = [
                'ticker' => (string)($a['recommended_ticker'] ?? ''),
                'why' => (string)($a['recommended_why'] ?? ''),
            ];
            unset($a['recommended_ticker'], $a['recommended_why']);
        }

        return $a;
    }
}
