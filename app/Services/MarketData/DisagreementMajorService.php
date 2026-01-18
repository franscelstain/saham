<?php

namespace App\Services\MarketData;

use App\Repositories\MarketData\RawEodRepository;

final class DisagreementMajorService
{
    /** @var RawEodRepository */
    private $rawRepo;

    public function __construct(RawEodRepository $rawRepo)
    {
        $this->rawRepo = $rawRepo;
    }

    /**
     * @return array{
     *   disagree_major:int,
     *   disagree_major_ratio:float,
     *   samples:array<int,array{ticker_id:int,trade_date:string,pct:float,min:float,max:float,sources:int}>
     * }
     */
    public function compute(int $runId, int $canonicalPoints, float $thresholdPct = 0.03, int $maxSamples = 10, ?int $rawRunId = null): array
    {
        // Jika belum ada multi-source import, hasilnya 0 (normal).
        $useRaw = ($rawRunId !== null && $rawRunId > 0) ? $rawRunId : $runId;
        $rows = $this->rawRepo->aggregateCloseRangeByRun($useRaw);

        $major = 0;
        $samples = [];

        foreach ($rows as $r) {
            $min = (float) $r->min_close;
            $max = (float) $r->max_close;

            // guard: avoid div by zero
            $mid = ($min + $max) / 2.0;
            if ($mid <= 0) continue;

            $pct = ($max - $min) / $mid; // symmetric percent diff
            if ($pct >= $thresholdPct) {
                $major++;

                if (count($samples) < $maxSamples) {
                    $samples[] = [
                        'ticker_id' => (int) $r->ticker_id,
                        'trade_date' => (string) $r->trade_date,
                        'pct' => $pct,
                        'min' => $min,
                        'max' => $max,
                        'sources' => (int) $r->sources,
                    ];
                }
            }
        }

        $den = $canonicalPoints > 0 ? $canonicalPoints : 1;
        $ratio = $major / $den;

        return [
            'disagree_major' => $major,
            'disagree_major_ratio' => $ratio,
            'samples' => $samples,
        ];
    }
}
