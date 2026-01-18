<?php

namespace App\Services\MarketData;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\MarketData\CanonicalEodRepository;

final class MissingTradingDayService
{
    private $cal;
    private $can;

    public function __construct(MarketCalendarRepository $cal, CanonicalEodRepository $can)
    {
        $this->cal = $cal;
        $this->can = $can;
    }

    /**
     * @return array{
     *   missing_days:int,
     *   missing_dates:string[],
     *   low_coverage_days:int,
     *   low_coverage_dates:array<int,array{date:string,count:int,expected:int,pct:float}>
     * }
     */
    public function compute(
        int $runId,
        string $from,
        string $to,
        int $expectedTickers,
        float $minCoveragePerDay = 0.60,
        int $maxSamples = 5
    ): array {
        if ($expectedTickers <= 0) $expectedTickers = 1;

        $dates = $this->cal->tradingDatesBetween($from, $to);
        $counts = $this->can->countsByRunIdGroupedByDate($runId, $dates);

        $missing = [];
        $low = [];

        foreach ($dates as $d) {
            $cnt = (int) ($counts[$d] ?? 0);

            if ($cnt === 0) {
                $missing[] = $d;
                continue;
            }

            $pct = $cnt / $expectedTickers;
            if ($pct < $minCoveragePerDay) {
                $low[] = [
                    'date' => $d,
                    'count' => $cnt,
                    'expected' => $expectedTickers,
                    'pct' => $pct,
                ];
            }
        }

        // limit samples for notes/UI
        if (count($low) > $maxSamples) $low = array_slice($low, 0, $maxSamples);

        return [
            'missing_days' => count($missing),
            'missing_dates' => $missing,
            'low_coverage_days' => count($low),
            'low_coverage_dates' => $low,
        ];
    }
}
