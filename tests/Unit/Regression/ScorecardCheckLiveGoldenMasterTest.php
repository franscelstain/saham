<?php

namespace Tests\Unit\Regression;

use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\Services\Watchlist\WatchlistScorecardService;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Scorecard\StrategyRunRepository;
use Tests\Support\UsesSqliteInMemory;
use Tests\TestCase;

/**
 * Golden master for watchlist:scorecard:check-live
 *
 * This locks the CLI contract for live eligibility output given:
 * - a stored strategy run (plan payload)
 * - a snapshot JSON
 */
class ScorecardCheckLiveGoldenMasterTest extends TestCase
{
    use UsesSqliteInMemory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSqliteInMemory();
        $this->migrateFreshSqlite();
    }

    public function testGoldenMaster_fixtureSnapshot_matchesExpected(): void
    {
        $fixtureDir = __DIR__ . '/../../Fixtures/scorecard';

        $snapshot = json_decode((string) file_get_contents($fixtureDir . '/snapshot_open.json'), true);
        $this->assertIsArray($snapshot);

        // Store strategy run (what would normally come from preopen watchlist plan)
        $runPayload = json_decode((string) file_get_contents($fixtureDir . '/strategy_run_payload.json'), true);
        $this->assertIsArray($runPayload);

        /** @var ScorecardConfig $cfg */
        $cfg = app()->make(ScorecardConfig::class);
        $dto = StrategyRunDto::fromPayloadArray($runPayload, 0, $cfg);

        /** @var StrategyRunRepository $runRepo */
        $runRepo = app()->make(StrategyRunRepository::class);
        $up = $runRepo->upsertFromDto($dto, 'WEEKLY_SWING', 'preopen_contract_weekly_swing');
        $this->assertTrue((bool) ($up['ok'] ?? false));

        /** @var WatchlistScorecardService $svc */
        $svc = app()->make(WatchlistScorecardService::class);

        // Sanity: snapshot parses
        $snapDto = LiveSnapshotDto::fromArray($snapshot);
        $this->assertSame('WEEKLY_SWING', strtoupper($snapDto->strategyCode));

        $outDto = $svc->checkLiveDto('WEEKLY_SWING', $snapshot, 'open');
        $actual = $this->normalize($outDto->toArray());

        $expected = json_decode((string) file_get_contents($fixtureDir . '/expected_open.json'), true);
        $this->assertIsArray($expected);
        $expected = $this->normalize($expected);

        $this->assertSame($expected, $actual);
    }

    /**
     * Normalize float noise and order.
     *
     * @param mixed $v
     * @return mixed
     */
    private function normalize($v)
    {
        if (is_array($v)) {
            // If this looks like results array, enforce stable order by ticker.
            if (isset($v[0]) && is_array($v[0]) && isset($v[0]['ticker'])) {
                usort($v, function ($a, $b) {
                    return strcmp((string) ($a['ticker'] ?? ''), (string) ($b['ticker'] ?? ''));
                });
            }
            $out = [];
            foreach ($v as $k => $vv) {
                $out[$k] = $this->normalize($vv);
            }
            return $out;
        }

        if (is_float($v)) {
            return round($v, 6);
        }
        return $v;
    }
}
