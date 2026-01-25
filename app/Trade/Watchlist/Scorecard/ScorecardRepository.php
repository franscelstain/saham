<?php

namespace App\Trade\Watchlist\Scorecard;

use App\DTO\Watchlist\Scorecard\ScorecardMetricsDto;
use Illuminate\Support\Facades\DB;

class ScorecardRepository
{
    public function upsertScorecardFromDto(int $runId, ScorecardMetricsDto $dto): void
    {
        $this->upsertScorecard($runId, $dto->feasibleRate, $dto->fillRate, $dto->outcomeRate, $dto->payload);
    }

    public function upsertScorecard(int $runId, ?float $feasibleRate, ?float $fillRate, ?float $outcomeRate, array $payload = []): void
    {
        $json = null;
        if (!empty($payload)) {
            $enc = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($enc === false) throw new \RuntimeException('failed to json_encode scorecard payload');
            $json = $enc;
        }

        DB::table('watchlist_scorecards')->upsert([
            [
                'run_id' => $runId,
                'feasible_rate' => $feasibleRate,
                'fill_rate' => $fillRate,
                'outcome_rate' => $outcomeRate,
                'payload_json' => $json,
                'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
                'created_at' => DB::raw('CURRENT_TIMESTAMP'),
            ],
        ], ['run_id'], ['feasible_rate', 'fill_rate', 'outcome_rate', 'payload_json', 'updated_at']);
    }
}
